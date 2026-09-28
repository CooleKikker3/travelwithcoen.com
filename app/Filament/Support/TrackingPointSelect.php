<?php

namespace App\Filament\Support;

use App\Models\TrackingPoint;
use Filament\Forms\Components\Select;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Select for a GPS location (tracking point), light on data for a weak connection: the latest 5 points,
 * more only when searching by date (e.g. "12-10" or "12 okt"). Defaults to the latest point.
 */
class TrackingPointSelect
{
    private const LIMIT = 5;

    public static function make(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn () => self::labels(TrackingPoint::latest('recorded_at')->limit(self::LIMIT)->get(['id', 'recorded_at', 'latitude', 'longitude'])))
            ->searchable()
            ->searchPrompt('Zoek op datum, bijv. 12-10')
            ->getSearchResultsUsing(function (string $search) {
                try {
                    $date = Carbon::parse(str_replace(' ', '-', trim($search)), FilamentTimezone::get());
                } catch (Throwable) {
                    return [];
                }

                return self::labels(TrackingPoint::whereBetween('recorded_at', [$date->copy()->startOfDay()->utc(), $date->copy()->endOfDay()->utc()])
                    ->latest('recorded_at')->limit(20)->get(['id', 'recorded_at', 'latitude', 'longitude']));
            })
            ->getOptionLabelUsing(fn ($value) => self::labels(TrackingPoint::whereKey($value)->get(['id', 'recorded_at', 'latitude', 'longitude']))[$value] ?? null)
            ->default(fn () => TrackingPoint::latest('recorded_at')->value('id'));
    }

    /** "ma 5 okt, 08:30 (52.26, 4.56)" in the time zone of the device. */
    private static function labels($points): array
    {
        return $points->mapWithKeys(fn (TrackingPoint $point) => [
            $point->id => $point->recorded_at->copy()->setTimezone(FilamentTimezone::get())->translatedFormat('D j M, H:i')
                .' ('.round($point->latitude, 2).', '.round($point->longitude, 2).')',
        ])->all();
    }
}
