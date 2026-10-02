<?php

namespace App\Support;

use App\Enums\RouteType;
use App\Models\CountryRoute;
use App\Models\JourneyEvent;
use App\Models\RoutePoint;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RouteGeometry
{
    /** Map detail levels: simplification tolerance in degrees (~0.001° ≈ 100 m). */
    public const DETAILED = 0.0003;

    public const OVERVIEW = 0.005;

    /** Zoomed in closely: (almost) every bend of a path, like in the route planner. */
    public const FINE = 0.00003;

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

    /** Compass bearing in degrees (0 = north, 90 = east) from one [lat, lng] to another. */
    public static function bearing(array $from, array $to): float
    {
        [$φ1, $φ2] = [deg2rad($from[0]), deg2rad($to[0])];
        $Δλ = deg2rad($to[1] - $from[1]);

        return fmod(rad2deg(atan2(sin($Δλ) * cos($φ2), cos($φ1) * sin($φ2) - sin($φ1) * cos($φ2) * cos($Δλ))) + 360, 360);
    }

    public static function isPrivate(float $lat, float $lng, bool $evenWhenLoggedIn = false): bool
    {
        $zone = self::privacyZone($evenWhenLoggedIn);

        return $zone !== null && self::haversine([$zone['lat'], $zone['lng']], [$lat, $lng]) < $zone['km'];
    }

    /** Where the planned route ends so far (the last point of the last planned piece of the last published country). */
    public static function lastPlannedPoint(): ?RoutePoint
    {
        return CountryRoute::where('type', RouteType::Planned)
            ->join('countries', 'countries.id', '=', 'country_routes.country_id')
            ->where('countries.is_published', true)
            ->orderByDesc('countries.sort_order')
            ->orderByDesc('country_routes.sort_order')
            ->select('country_routes.*')
            ->get()
            ->map(fn (CountryRoute $route) => $route->points()->reorder('sequence', 'desc')->first())
            ->filter()
            ->first();
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

        $lastPlanned = self::lastPlannedPoint();
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
    /**
     * The route pieces (all published ones, or those through one country) in more detail, only the stretches
     * within a bounding box [west, south, east, north]: maps fetch these when zooming in (GET /api/track).
     */
    public static function routesIn(array $bbox, ?int $countryId, float $tolerance): array
    {
        $routes = $countryId
            ? (\App\Models\Country::published()->find($countryId)?->routesThrough() ?? collect())
            : CountryRoute::whereHas('country', fn ($query) => $query->where('is_published', true))->get();
        [$west, $south, $east, $north] = $bbox;
        // A stretch between two points counts when it may cross the box: on a straight road both ends can lie far outside it.
        $crosses = fn (array $a, array $b) => max($a[0], $b[0]) >= $west && min($a[0], $b[0]) <= $east && max($a[1], $b[1]) >= $south && min($a[1], $b[1]) <= $north;

        $features = [];
        foreach (self::featureCollection($routes, $tolerance, $countryId)['features'] as $feature) {
            $lines = $feature['geometry']['type'] === 'LineString' ? [$feature['geometry']['coordinates']] : $feature['geometry']['coordinates'];
            $parts = [];
            foreach ($lines as $line) {
                $run = [];
                for ($i = 1; $i < count($line); $i++) {
                    if ($crosses($line[$i - 1], $line[$i])) {
                        $run = $run ? [...$run, $line[$i]] : [$line[$i - 1], $line[$i]];
                    } elseif ($run) {
                        $parts[] = $run;
                        $run = [];
                    }
                }
                $parts[] = $run;
            }
            $parts = array_values(array_filter($parts, fn ($part) => count($part) > 1));
            if ($parts) {
                $features[] = ['geometry' => ['type' => 'MultiLineString', 'coordinates' => $parts]] + $feature;
            }
        }

        return $features;
    }

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
    public static function withTracking(array $collection, ?User $user, ?int $countryId, int $level = 2): array
    {
        // The walked route at this level of detail (stored ready-made; never all GPS points at once).
        array_push($collection['features'], ...WalkedTrack::features($user, $level, null, $countryId));

        // Last position: one point, and never near home for visitors.
        $last = TrackingPoint::visibleTo($user)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->latest('recorded_at')
            ->first(['latitude', 'longitude', 'recorded_at']);
        // A country already left: an arrow where the border was crossed, pointing the way I went on.
        // Direction towards a point a few hours later (the very next point is too close: GPS jitter).
        $next = $countryId && $last
            ? (TrackingPoint::visibleTo($user)->where('recorded_at', '>=', $last->recorded_at->copy()->addHours(3))->oldest('recorded_at')->first(['latitude', 'longitude'])
                ?? TrackingPoint::visibleTo($user)->where('recorded_at', '>', $last->recorded_at)->latest('recorded_at')->first(['latitude', 'longitude']))
            : null;
        if ($last && $next) {
            $collection['features'][] = self::point('exit', $last->latitude, $last->longitude, [
                'bearing' => round(self::bearing([$last->latitude, $last->longitude], [$next->latitude, $next->longitude])),
                'label' => __('site.map.exit').': '.$last->recorded_at->translatedFormat('j F Y'),
            ]);
        } elseif ($last && ! self::isPrivate($last->latitude, $last->longitude)) {
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

    /** Straight-line ("as the crow flies") distance in km between two [lat, lng] points. */
    public static function haversine(array $a, array $b): float
    {
        [$lat1, $lat2] = [deg2rad($a[0]), deg2rad($b[0])];
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b[1] - $a[1]);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371.0088 * 2 * asin(min(1, sqrt($h)));
    }
}
