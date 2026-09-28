<?php

namespace App\Support;

use App\Models\Country;

/**
 * Which of our countries a point lies in, from the Natural Earth outlines (RouteGeometry::border()).
 * The outlines are coarse (1:50m, a few hundred metres up to ~1 km at borders): good for kilometres
 * per country, not for the exact border. Points outside every outline (sea, border slivers) get null;
 * callers fall back to the previous point's country.
 */
class CountryLocator
{
    /** @var array<int, array{bbox: array{0: float, 1: float, 2: float, 3: float}, rings: list<list<array{0: float, 1: float}>>}> */
    private array $shapes = [];

    public function __construct()
    {
        foreach (Country::get(['id', 'iso_code']) as $country) {
            $polygons = RouteGeometry::border($country->iso_code) ?? [];
            $rings = array_merge(...array_values($polygons) ?: [[]]);
            if (! $rings) {
                continue;
            }
            $lngs = array_merge(...array_map(fn ($ring) => array_column($ring, 0), $rings));
            $lats = array_merge(...array_map(fn ($ring) => array_column($ring, 1), $rings));
            $this->shapes[$country->id] = ['bbox' => [min($lats), max($lats), min($lngs), max($lngs)], 'rings' => $rings];
        }
    }

    public function locate(float $lat, float $lng): ?int
    {
        foreach ($this->shapes as $id => ['bbox' => [$south, $north, $west, $east], 'rings' => $rings]) {
            if ($lat < $south || $lat > $north || $lng < $west || $lng > $east) {
                continue;
            }
            // Even-odd ray casting over all rings (holes included).
            $inside = false;
            foreach ($rings as $ring) {
                for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                    [$xi, $yi] = $ring[$i];
                    [$xj, $yj] = $ring[$j];
                    if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                        $inside = ! $inside;
                    }
                }
            }
            if ($inside) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Country per point, filling gaps (points outside every outline) with the previous country, else $fallback.
     *
     * @param  list<array{0: float, 1: float}>  $points  [lat, lng]
     * @return list<?int>
     */
    public function locateAll(array $points, ?int $fallback = null): array
    {
        $previous = null;
        $found = array_map(function ($point) use (&$previous) {
            return $previous = $this->locate($point[0], $point[1]) ?? $previous;
        }, $points);
        // Leading points outside every outline: the first country found, else the fallback.
        $first = current(array_filter($found)) ?: $fallback;

        return array_map(fn ($id) => $id ?? $first, $found);
    }
}
