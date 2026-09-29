<?php

namespace App\Services;

use App\Models\TrackingPoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * Garmin inReach → website, via the MapShare KML feed (share.garmin.com/Feed/Share/{name}).
 * Garmin puts every tracking point of the device on MapShare; this reads the feed and stores new points
 * through TrackingRecorder (duplicates are ignored, so reading the same period twice is safe).
 * Settings: GARMIN_MAPSHARE_URL and, if the MapShare page has a password, GARMIN_MAPSHARE_PASSWORD.
 */
class GarminMapShare
{
    public const SOURCE = 'garmin';

    public function __construct(private TrackingRecorder $recorder) {}

    /** @return int number of new points */
    public function sync(): int
    {
        $url = config('travel.garmin.mapshare_url');
        if (blank($url)) {
            return 0;
        }

        // From shortly before the last point we have (catches late satellite messages), else the last 30 days.
        $last = TrackingPoint::where('source', self::SOURCE)->max('recorded_at');
        $from = $last ? Carbon::parse($last)->subHours(6) : now()->subDays(30);

        $response = Http::timeout(30)
            ->when(filled(config('travel.garmin.mapshare_password')), fn ($http) => $http->withBasicAuth('', config('travel.garmin.mapshare_password')))
            ->get($url, ['d1' => $from->utc()->format('Y-m-d\TH:i\z')])
            ->throw();

        return $this->recorder->store($this->parse($response->body()), self::SOURCE);
    }

    /** @return list<array{lat: float, lng: float, ele: ?float, time: string}> */
    public function parse(string $kml): array
    {
        if (trim($kml) === '') {
            return [];
        }
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($kml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            return [];
        }

        $points = [];
        // Every placemark with a time and a point is a tracking point (the feed also has one line of the whole track).
        foreach ($doc->xpath("//*[local-name()='Placemark']") ?: [] as $placemark) {
            $when = $placemark->xpath(".//*[local-name()='TimeStamp']/*[local-name()='when']")[0] ?? null;
            $coordinates = $placemark->xpath(".//*[local-name()='Point']/*[local-name()='coordinates']")[0] ?? null;
            if (! $when || ! $coordinates) {
                continue;
            }
            [$lng, $lat, $ele] = array_map('floatval', explode(',', trim((string) $coordinates)) + [2 => null]);
            if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
                continue;
            }
            $points[] = ['lat' => $lat, 'lng' => $lng, 'ele' => $ele ?: null, 'time' => Carbon::parse((string) $when)->utc()->toIso8601String()];
        }

        return $points;
    }
}
