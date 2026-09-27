<?php

namespace App\Services;

use App\Models\CountryRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Replaces a route's points with the track/route points of its GPX file
 * (e.g. exported from Komoot, gpx.studio or a GPS watch).
 */
class GpxImporter
{
    public function import(CountryRoute $route): int
    {
        $points = $this->parse(Storage::disk('local')->get($route->gpx_path) ?? '');

        $route->replacePoints($points);

        return count($points);
    }

    /** @return array<int, array{lat: float, lng: float, ele: ?float, time: ?string}> */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new InvalidArgumentException('The file is not valid GPX/XML.');
        }

        // Track points, else route points. local-name() ignores the GPX 1.0/1.1 namespace.
        $nodes = $doc->xpath("//*[local-name()='trkpt']") ?: $doc->xpath("//*[local-name()='rtept']") ?: [];

        return array_values(array_filter(array_map(function (SimpleXMLElement $node) {
            [$lat, $lng] = [(float) $node['lat'], (float) $node['lon']];

            if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
                return null;
            }

            $children = $node->children($node->getNamespaces()[''] ?? '');

            return [
                'lat' => $lat,
                'lng' => $lng,
                'ele' => isset($children->ele) ? (float) $children->ele : null,
                'time' => isset($children->time) ? Carbon::parse((string) $children->time)->utc()->toDateTimeString() : null,
            ];
        }, $nodes)));
    }
}
