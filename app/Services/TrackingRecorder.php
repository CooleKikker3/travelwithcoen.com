<?php

namespace App\Services;

use App\Enums\CountryStatus;
use App\Models\Country;
use App\Models\TrackingPoint;
use App\Support\CountryLocator;
use Illuminate\Support\Carbon;

/**
 * Stores incoming location points from any source (GPX import, phone app, later Garmin).
 * Duplicates (same source + time) are ignored, so re-importing is safe.
 * Each point gets the country it lies in, and the country of the newest point becomes "walking here now".
 */
class TrackingRecorder
{
    /**
     * @param  iterable<array{lat: float, lng: float, time: string|Carbon, ele?: ?float}>  $points
     * @param  int|null  $countryId  only for points outside every known country; defaults to the previous point's country
     */
    public function store(iterable $points, string $source, ?int $countryId = null): int
    {
        $now = now();
        $points = collect($points)->filter(fn ($p) => ! empty($p['time']))
            ->sortBy(fn ($p) => Carbon::parse($p['time'])->getTimestamp())->values();

        if ($points->isEmpty()) {
            return 0;
        }

        // Fallback for points in the sea or on a coarse border: the country of the last known point before them.
        $fallback = $countryId
            ?? TrackingPoint::where('recorded_at', '<', Carbon::parse($points->first()['time'])->utc())->whereNotNull('country_id')->latest('recorded_at')->value('country_id')
            ?? Country::where('status', CountryStatus::Current)->value('id');
        $countries = app(CountryLocator::class)->locateAll($points->map(fn ($p) => [(float) $p['lat'], (float) $p['lng']])->all(), $fallback);

        $rows = $points->map(fn ($p, $i) => [
            'latitude' => $p['lat'],
            'longitude' => $p['lng'],
            'altitude' => $p['ele'] ?? null,
            'recorded_at' => Carbon::parse($p['time'])->utc(),
            'received_at' => $now,
            'source' => $source,
            'country_id' => $countries[$i],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stored = $rows->chunk(500)->sum(fn ($chunk) => TrackingPoint::insertOrIgnore($chunk->values()->all()));
        $this->updateCurrentCountry();

        return $stored;
    }

    /** The country of the newest point is "walking here now"; the previous one becomes "visited". */
    public function updateCurrentCountry(): void
    {
        $countryId = TrackingPoint::whereNotNull('country_id')->latest('recorded_at')->value('country_id');
        $current = Country::where('status', CountryStatus::Current)->first();

        if (! $countryId || $current?->id === $countryId) {
            return;
        }

        $current?->update(['status' => CountryStatus::Visited]);
        Country::whereKey($countryId)->update(['status' => CountryStatus::Current]);
    }
}
