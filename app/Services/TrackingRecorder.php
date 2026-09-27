<?php

namespace App\Services;

use App\Enums\CountryStatus;
use App\Models\Country;
use App\Models\TrackingPoint;
use Illuminate\Support\Carbon;

/**
 * Stores incoming location points from any source (GPX import, phone app, later Garmin).
 * Duplicates (same source + time) are ignored, so re-importing is safe.
 */
class TrackingRecorder
{
    /**
     * @param  iterable<array{lat: float, lng: float, time: string|Carbon, ele?: ?float}>  $points
     * @param  int|null  $countryId  defaults to the country marked "walking here now"
     */
    public function store(iterable $points, string $source, ?int $countryId = null): int
    {
        $countryId ??= Country::where('status', CountryStatus::Current)->value('id');
        $now = now();

        $rows = collect($points)
            ->filter(fn ($p) => ! empty($p['time']))
            ->map(fn ($p) => [
                'latitude' => $p['lat'],
                'longitude' => $p['lng'],
                'altitude' => $p['ele'] ?? null,
                'recorded_at' => Carbon::parse($p['time'])->utc(),
                'received_at' => $now,
                'source' => $source,
                'country_id' => $countryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        return $rows->chunk(500)->sum(fn ($chunk) => TrackingPoint::insertOrIgnore($chunk->values()->all()));
    }
}
