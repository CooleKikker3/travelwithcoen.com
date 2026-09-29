<?php

namespace App\Support;

use App\Models\CountryRoute;

/** A route piece as a GPX track, for Garmin (Connect, BaseCamp, Explore) and other GPS apps. */
class GpxExport
{
    public static function route(CountryRoute $route): string
    {
        $name = e($route->label());
        $points = $route->points()->get(['latitude', 'longitude', 'elevation'])
            ->map(fn ($p) => sprintf('<trkpt lat="%.6F" lon="%.6F">%s</trkpt>', $p->latitude, $p->longitude, $p->elevation !== null ? sprintf('<ele>%.1F</ele>', $p->elevation) : ''))
            ->join("\n      ");

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="Travel with Coen" xmlns="http://www.topografix.com/GPX/1/1">
              <metadata><name>{$name}</name></metadata>
              <trk>
                <name>{$name}</name>
                <trkseg>
                  {$points}
                </trkseg>
              </trk>
            </gpx>
            XML;
    }

    public static function filename(CountryRoute $route): string
    {
        return (\Illuminate\Support\Str::slug($route->label()) ?: 'route-'.$route->id).'.gpx';
    }
}