<?php

namespace App\Support;

use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The walked route for maps, by level of detail — like map apps: the further zoomed out, the less detail.
 *
 * Levels 1–3 are stored ready-made per day in `walked_lines` (rebuilt when GPS points come in);
 * level 4 is the raw points, only for the part of the map in view. Maps first get a coarse level embedded
 * in the page; zooming in fetches more detail for the viewport (GET /api/track, resources/js/map.js).
 * The tracking delay and the privacy zone around home are applied here, on the server, for every request.
 */
class WalkedTrack
{
    /** Simplification tolerance in degrees per stored level (≈ 1 km, 200 m, 40 m). */
    public const LEVELS = [1 => 0.01, 2 => 0.002, 3 => 0.0004];

    public const RAW = 4;

    /** At most this many raw points per request (level 4); a larger area gets level 3. */
    private const RAW_LIMIT = 20000;

    public static function levelForZoom(float $zoom): int
    {
        return match (true) {
            $zoom <= 5 => 1,
            $zoom <= 8 => 2,
            $zoom <= 11 => 3,
            default => self::RAW,
        };
    }

    /** Rebuild the stored lines of the given days ("Y-m-d", UTC). */
    public static function rebuild(iterable $days): void
    {
        foreach (collect($days)->unique() as $day) {
            $points = TrackingPoint::query()->toBase()
                ->whereBetween('recorded_at', [Carbon::parse($day)->startOfDay(), Carbon::parse($day)->endOfDay()])
                ->orderBy('recorded_at')
                ->get(['latitude', 'longitude', 'recorded_at', 'country_id']);

            DB::transaction(function () use ($day, $points) {
                DB::table('walked_lines')->where('day', $day)->delete();
                if ($points->count() < 2) {
                    return;
                }

                $line = $points->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all();
                $lats = array_column($line, 0);
                $lngs = array_column($line, 1);
                $common = [
                    'day' => $day,
                    // The country the most points of the day lie in.
                    'country_id' => $points->pluck('country_id')->filter()->countBy()->sortDesc()->keys()->first(),
                    'min_lat' => min($lats), 'max_lat' => max($lats), 'min_lng' => min($lngs), 'max_lng' => max($lngs),
                    'first_at' => $points->first()->recorded_at,
                    'last_at' => $points->last()->recorded_at,
                    'created_at' => now(), 'updated_at' => now(),
                ];

                foreach (self::LEVELS as $level => $tolerance) {
                    $simple = RouteGeometry::simplify($line, $tolerance);
                    DB::table('walked_lines')->insert($common + ['level' => $level, 'line' => Polyline::encode($simple), 'points' => count($simple)]);
                }
            });
        }
    }

    /** Rebuild everything (after importing or removing many points). */
    public static function rebuildAll(): int
    {
        DB::table('walked_lines')->delete();
        $days = DB::table('tracking_points')->selectRaw('date(recorded_at) as day')->distinct()->pluck('day');
        self::rebuild($days);

        return $days->count();
    }

    /**
     * Lines ([lat, lng] lists) the user may see, at a level, optionally only within a bounding box
     * [west, south, east, north] and/or a country.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function lines(?User $user, int $level, ?array $bbox = null, ?int $countryId = null): array
    {
        $cutoff = TrackingPrivacy::cutoff($user);

        if ($level === self::RAW) {
            $lines = self::rawLines($cutoff, $bbox, $countryId);
            if ($lines !== null) {
                return self::withoutPrivateParts($lines, $user);
            }
            $level = 3; // too many points in view: the finest stored level instead
        }

        $query = DB::table('walked_lines')->where('level', $level)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->when($bbox, fn ($q) => $q->where('max_lng', '>=', $bbox[0])->where('min_lat', '<=', $bbox[3])->where('min_lng', '<=', $bbox[2])->where('max_lat', '>=', $bbox[1]))
            ->orderBy('day');

        // Fully visible days from the store; the day the delay cuts through is calculated from its points.
        $lines = (clone $query)->when($cutoff, fn ($q) => $q->where('last_at', '<=', $cutoff))->pluck('line')->map(fn ($l) => Polyline::decode($l))->all();
        if ($cutoff && ($partial = (clone $query)->where('first_at', '<=', $cutoff)->where('last_at', '>', $cutoff)->first())) {
            $points = TrackingPoint::query()->toBase()->whereBetween('recorded_at', [$partial->first_at, $cutoff])->orderBy('recorded_at')->get(['latitude', 'longitude'])
                ->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all();
            if (count($points) > 1) {
                $lines[] = RouteGeometry::simplify($points, self::LEVELS[$level]);
            }
        }

        return self::withoutPrivateParts($lines, $user);
    }

    /** Raw points in the viewport as lines (a new line after half an hour without points), or null when too many. */
    private static function rawLines(?Carbon $cutoff, ?array $bbox, ?int $countryId): ?array
    {
        $query = TrackingPoint::query()->toBase()
            ->when($cutoff, fn ($q) => $q->where('recorded_at', '<=', $cutoff))
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->when($bbox, fn ($q) => $q->whereBetween('longitude', [$bbox[0], $bbox[2]])->whereBetween('latitude', [$bbox[1], $bbox[3]]));
        if ((clone $query)->count() > self::RAW_LIMIT) {
            return null;
        }

        $lines = [[]];
        $previous = null;
        foreach ($query->orderBy('recorded_at')->get(['latitude', 'longitude', 'recorded_at']) as $point) {
            $time = Carbon::parse($point->recorded_at);
            if ($previous && $previous->diffInMinutes($time) > 30) {
                $lines[] = [];
            }
            $lines[array_key_last($lines)][] = [(float) $point->latitude, (float) $point->longitude];
            $previous = $time;
        }

        return array_values(array_filter($lines, fn ($line) => count($line) > 1));
    }

    /** Visitors never see the stretch near home: lines are cut where they enter the privacy zone. */
    private static function withoutPrivateParts(array $lines, ?User $user): array
    {
        if (! RouteGeometry::privacyZone(evenWhenLoggedIn: ! $user?->canSeeLiveTracking())) {
            return $lines;
        }

        $result = [];
        foreach ($lines as $line) {
            $part = [];
            foreach ($line as $point) {
                if (RouteGeometry::isPrivate($point[0], $point[1], evenWhenLoggedIn: ! $user?->canSeeLiveTracking())) {
                    count($part) > 1 && $result[] = $part;
                    $part = [];

                    continue;
                }
                $part[] = $point;
            }
            count($part) > 1 && $result[] = $part;
        }

        return $result;
    }

    /** GeoJSON features ("actual" lines) for the map. */
    public static function features(?User $user, int $level, ?array $bbox = null, ?int $countryId = null): array
    {
        return array_map(fn ($line) => [
            'type' => 'Feature',
            'properties' => ['type' => 'actual'],
            'geometry' => ['type' => 'LineString', 'coordinates' => array_map(fn ($p) => [round($p[1], 5), round($p[0], 5)], $line)],
        ], self::lines($user, $level, $bbox, $countryId));
    }
}
