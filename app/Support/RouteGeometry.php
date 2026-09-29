<?php

namespace App\Support;

use App\Enums\RouteType;
use App\Models\CountryRoute;
use App\Models\JourneyEvent;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RouteGeometry
{
    /** Map detail levels: simplification tolerance in degrees (~0.001° ≈ 100 m). */
    public const DETAILED = 0.0003;

    public const OVERVIEW = 0.005;

    /**
     * Outline of a country as GeoJSON MultiPolygon coordinates ([lng, lat]), from Natural Earth 1:50m
     * (public domain, resources/data/country-borders/{ISO}.json). Null when unknown.
     */
    /**
     * Start and destination markers, plus a straight "still to be planned" line from the last point
     * of the planned route (the last published country that has one) — or from the start — to the destination.
     */
    /**
     * The start of the journey is the first point of the first planned route piece (countries and pieces
     * in their order). That is home, so the website hides everything within privacy_radius_m of it.
     *
     * @return array{lat: float, lng: float, km: float}|null
     */
    public static function privacyZone(bool $evenWhenLoggedIn = false): ?array
    {
        // Family and admins (logged in) know where home is: no privacy zone for them.
        if (! $evenWhenLoggedIn && auth()->user()?->canSeeLiveTracking()) {
            return null;
        }

        return once(function () {
            $first = CountryRoute::where('type', RouteType::Planned)
                ->join('countries', 'countries.id', '=', 'country_routes.country_id')
                ->where('countries.is_published', true)
                ->orderBy('countries.sort_order')
                ->orderBy('country_routes.sort_order')
                ->select('country_routes.*')
                ->first()
                ?->points()->first(['latitude', 'longitude']);

            return $first ? ['lat' => (float) $first->latitude, 'lng' => (float) $first->longitude, 'km' => config('travel.privacy_radius_m') / 1000] : null;
        });
    }

    /** The route piece the journey starts with (see privacyZone()). */
    private static function privacyZoneRoute(): ?CountryRoute
    {
        return CountryRoute::where('type', RouteType::Planned)
            ->join('countries', 'countries.id', '=', 'country_routes.country_id')
            ->where('countries.is_published', true)
            ->orderBy('countries.sort_order')
            ->orderBy('country_routes.sort_order')
            ->select('country_routes.*')
            ->first();
    }

    public static function isPrivate(float $lat, float $lng, bool $evenWhenLoggedIn = false): bool
    {
        $zone = self::privacyZone($evenWhenLoggedIn);

        return $zone !== null && self::haversine([$zone['lat'], $zone['lng']], [$lat, $lng]) < $zone['km'];
    }

    public static function withOpenPlan(array $collection): array
    {
        [$start, $destination] = [config('travel.start'), config('travel.destination')];

        // Start marker where the visible route begins (just outside the privacy zone), named after the town.
        if ($zone = self::privacyZone()) {
            $firstVisible = collect(self::privacyZoneRoute()?->points()->get(['latitude', 'longitude']) ?? [])
                ->first(fn ($p) => ! self::isPrivate($p->latitude, $p->longitude));
            if ($firstVisible) {
                $start = ['lat' => (float) $firstVisible->latitude, 'lng' => (float) $firstVisible->longitude] + $start;
            }
        }

        $lastPlanned = CountryRoute::where('type', RouteType::Planned)
            ->join('countries', 'countries.id', '=', 'country_routes.country_id')
            ->where('countries.is_published', true)
            ->orderByDesc('countries.sort_order')
            ->orderByDesc('country_routes.sort_order')
            ->select('country_routes.*')
            ->get()
            ->map(fn (CountryRoute $route) => $route->points()->reorder('sequence', 'desc')->first())
            ->filter()
            ->first();
        $from = $lastPlanned ? [$lastPlanned->latitude, $lastPlanned->longitude] : [$start['lat'], $start['lng']];

        $collection['features'][] = self::line('open', [$from, [$destination['lat'], $destination['lng']]]);
        $collection['features'][] = self::point('start', $start['lat'], $start['lng'], ['label' => $start['name']]);
        $collection['features'][] = self::point('destination', $destination['lat'], $destination['lng'], ['label' => $destination['name']]);

        return $collection;
    }
    public static function border(?string $isoCode): ?array
    {
        $file = resource_path('data/country-borders/'.strtoupper((string) $isoCode).'.json');

        return preg_match('/^[A-Za-z]{2}$/', (string) $isoCode) && is_file($file)
            ? json_decode(file_get_contents($file), true)
            : null;
    }

    /** @param array<int, array{0: float, 1: float}> $points [lat, lng] */
    public static function distanceKm(array $points): float
    {
        $total = 0.0;

        for ($i = 1; $i < count($points); $i++) {
            $total += self::haversine($points[$i - 1], $points[$i]);
        }

        return $total;
    }

    /**
     * GeoJSON FeatureCollection of the given routes, simplified for display. Cached until a route changes.
     *
     * @param  Collection<int, CountryRoute>  $routes
     */
    public static function featureCollection(Collection $routes, float $tolerance, ?int $countryId = null): array
    {
        $key = 'geojson:'.md5($routes->map(fn ($r) => $r->id.'@'.$r->updated_at?->timestamp)->join(',').":{$tolerance}:".app()->getLocale().':'.$countryId.':'.json_encode(self::privacyZone()));

        return Cache::remember($key, now()->addDay(), fn () => [
            'type' => 'FeatureCollection',
            'features' => $routes->map(function (CountryRoute $route) use ($tolerance, $countryId) {
                // For one country: only the stretches of the piece inside that country (a piece can cross borders).
                $lines = [[]];
                foreach ($route->points()->get(['latitude', 'longitude', 'country_id']) as $point) {
                    // Outside this country, or inside the privacy zone around home: not drawn.
                    if (($countryId && $point->country_id !== null && $point->country_id !== $countryId) || self::isPrivate($point->latitude, $point->longitude)) {
                        $lines[] = [];

                        continue;
                    }
                    $lines[array_key_last($lines)][] = [$point->latitude, $point->longitude];
                }
                $lines = array_values(array_filter($lines, fn ($line) => count($line) > 1));

                if (! $lines) {
                    return null;
                }

                // GeoJSON order is [lng, lat]; 5 decimals ≈ 1 m.
                $coordinates = array_map(fn ($line) => array_map(fn ($p) => [round($p[1], 5), round($p[0], 5)], self::simplify($line, $tolerance)), $lines);

                return [
                    'type' => 'Feature',
                    'properties' => array_filter(['type' => $route->type->value, 'route' => $route->id, 'country' => $route->country_id, 'label' => $route->translate('title')]),
                    'geometry' => count($coordinates) === 1
                        ? ['type' => 'LineString', 'coordinates' => $coordinates[0]]
                        : ['type' => 'MultiLineString', 'coordinates' => $coordinates],
                ];
            })->filter()->values()->all(),
        ]);
    }

    /**
     * Add tracking (as actual route + last position) and journey events to a route collection,
     * limited to what the user may see. Not cached: visibility depends on the user and time.
     */
    public static function withTracking(array $collection, ?User $user, ?int $countryId, float $tolerance): array
    {
        $points = TrackingPoint::visibleTo($user)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'recorded_at'])
            // Never show locations near home.
            ->reject(fn ($p) => self::isPrivate($p->latitude, $p->longitude))
            ->values();

        // A new line after gaps longer than 12 hours (e.g. transport or no signal).
        $segments = [];
        $previous = null;
        foreach ($points as $point) {
            if (! $previous || $previous->recorded_at->diffInHours($point->recorded_at) > 12) {
                $segments[] = [];
            }
            $segments[array_key_last($segments)][] = [$point->latitude, $point->longitude];
            $previous = $point;
        }

        foreach ($segments as $segment) {
            if (count($segment) > 1) {
                $collection['features'][] = self::line('actual', self::simplify($segment, $tolerance));
            }
        }

        if ($last = $points->last()) {
            $collection['features'][] = self::point('position', $last->latitude, $last->longitude, [
                'label' => __('site.live.last_location').': '.$last->recorded_at->translatedFormat('j F Y, H:i'),
            ]);
        }

        JourneyEvent::visibleTo($user)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->whereNotNull('latitude')
            ->get()
            ->each(function (JourneyEvent $event) use (&$collection) {
                $collection['features'][] = self::point('event', $event->latitude, $event->longitude, [
                    'label' => $event->occurred_at->translatedFormat('j F Y').' — '.$event->translate('title'),
                ]);
            });

        return $collection;
    }

    private static function line(string $type, array $points): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['type' => $type],
            'geometry' => ['type' => 'LineString', 'coordinates' => array_map(fn ($p) => [round($p[1], 5), round($p[0], 5)], $points)],
        ];
    }

    private static function point(string $type, float $lat, float $lng, array $properties = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['type' => $type] + $properties,
            'geometry' => ['type' => 'Point', 'coordinates' => [round($lng, 5), round($lat, 5)]],
        ];
    }

    /** Ramer–Douglas–Peucker line simplification (planar approximation, fine for display). */
    public static function simplify(array $points, float $tolerance): array
    {
        if (count($points) < 3) {
            return $points;
        }

        $keep = array_fill(0, count($points), false);
        $keep[0] = $keep[count($points) - 1] = true;
        $stack = [[0, count($points) - 1]];

        while ($stack) {
            [$start, $end] = array_pop($stack);
            $maxDistance = 0.0;
            $index = null;

            for ($i = $start + 1; $i < $end; $i++) {
                $distance = self::perpendicularDistance($points[$i], $points[$start], $points[$end]);
                if ($distance > $maxDistance) {
                    [$maxDistance, $index] = [$distance, $i];
                }
            }

            if ($index !== null && $maxDistance > $tolerance) {
                $keep[$index] = true;
                $stack[] = [$start, $index];
                $stack[] = [$index, $end];
            }
        }

        return array_values(array_filter($points, fn ($i) => $keep[$i], ARRAY_FILTER_USE_KEY));
    }

    private static function perpendicularDistance(array $p, array $a, array $b): float
    {
        [$dx, $dy] = [$b[1] - $a[1], $b[0] - $a[0]];

        if ($dx == 0 && $dy == 0) {
            return hypot($p[1] - $a[1], $p[0] - $a[0]);
        }

        return abs($dy * $p[1] - $dx * $p[0] + $b[1] * $a[0] - $b[0] * $a[1]) / hypot($dx, $dy);
    }

    private static function haversine(array $a, array $b): float
    {
        [$lat1, $lat2] = [deg2rad($a[0]), deg2rad($b[0])];
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b[1] - $a[1]);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371.0088 * 2 * asin(min(1, sqrt($h)));
    }
}
