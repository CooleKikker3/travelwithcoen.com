<?php

namespace App\Support;

use App\Enums\DayType;
use App\Enums\RouteType;
use App\Models\Country;
use App\Models\CountryRoute;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Models\User;

/**
 * Statistics calculated from journey days, routes and tracking — never entered by hand.
 * Respects the tracking delay: guests get statistics over the data they may see.
 */
class JourneyStats
{
    public static function for(?User $user, ?Country $country = null): array
    {
        $days = JourneyDay::visibleTo($user)
            ->when($country, fn ($q) => $q->where('country_id', $country->id))
            ->orderBy('date')
            ->get();

        $walks = $days->where('type', DayType::Walk);
        $longest = $walks->sortByDesc('distance_km')->first();

        $altitudes = TrackingPoint::visibleTo($user)
            ->when($country, fn ($q) => $q->where('country_id', $country->id))
            ->whereNotNull('altitude')
            ->selectRaw('max(altitude) as highest, min(altitude) as lowest')
            ->first();

        $planned = $country
            ? $country->routesThrough()->where('type', RouteType::Planned)->sum(fn ($route) => $route->kmIn($country->id))
            : CountryRoute::where('type', RouteType::Planned)->sum('distance_km');

        $walked = (float) $days->sum('distance_km');

        return [
            'days' => $days->count(),
            'walking_days' => $walks->count(),
            'rest_days' => $days->where('type', DayType::Rest)->count(),
            'transport_days' => $days->where('type', DayType::Transport)->count(),
            'distance_km' => $walked,
            'average_km' => $walks->count() ? $walked / $walks->count() : null,
            'longest' => $longest?->distance_km ? $longest : null,
            'walking_hours' => $days->sum('walking_minutes') / 60,
            'countries' => $walks->pluck('country_id')->filter()->unique()->count(),
            'tent_nights' => $days->filter(fn ($d) => $d->overnight?->isTent())->count(),
            'nights' => $days->whereNotNull('overnight')->countBy(fn ($d) => $d->overnight->value)->all(),
            'highest_m' => $altitudes?->highest,
            'lowest_m' => $altitudes?->lowest,
            'planned_km' => (float) $planned,
            'first_day' => $days->first()?->date,
            'last_day' => $days->last()?->date,
        ];
    }
}
