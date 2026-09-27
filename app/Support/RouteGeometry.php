<?php

namespace App\Support;

use App\Models\CountryRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RouteGeometry
{
    /** Map detail levels: simplification tolerance in degrees (~0.001° ≈ 100 m). */
    public const DETAILED = 0.0003;

    public const OVERVIEW = 0.005;

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
    public static function featureCollection(Collection $routes, float $tolerance): array
    {
        $key = 'geojson:'.md5($routes->map(fn ($r) => $r->id.'@'.$r->updated_at?->timestamp)->join(',').":{$tolerance}");

        return Cache::remember($key, now()->addDay(), fn () => [
            'type' => 'FeatureCollection',
            'features' => $routes->map(function (CountryRoute $route) use ($tolerance) {
                $points = $route->points()->get(['latitude', 'longitude'])
                    ->map(fn ($p) => [$p->latitude, $p->longitude])
                    ->all();

                if (count($points) < 2) {
                    return null;
                }

                return [
                    'type' => 'Feature',
                    'properties' => ['type' => $route->type->value, 'country' => $route->country_id],
                    'geometry' => [
                        'type' => 'LineString',
                        // GeoJSON order is [lng, lat]; 5 decimals ≈ 1 m.
                        'coordinates' => array_map(fn ($p) => [round($p[1], 5), round($p[0], 5)], self::simplify($points, $tolerance)),
                    ],
                ];
            })->filter()->values()->all(),
        ]);
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
