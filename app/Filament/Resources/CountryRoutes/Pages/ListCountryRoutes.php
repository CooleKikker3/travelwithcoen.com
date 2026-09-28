<?php

namespace App\Filament\Resources\CountryRoutes\Pages;

use App\Filament\Resources\CountryRoutes\CountryRouteResource;
use App\Models\Country;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

/** Route pieces per country (a tab each), in the order of the route. New pieces are made in the route planner. */
class ListCountryRoutes extends ListRecords
{
    protected static string $resource = CountryRouteResource::class;

    public function getTabs(): array
    {
        return Country::orderBy('sort_order')->withCount('routes')->get()
            ->filter(fn (Country $country) => $country->routes_count > 0)
            ->mapWithKeys(fn (Country $country) => [$country->iso_code => Tab::make($country->flag().' '.$country->translate('name', 'nl'))
                ->badge($country->routes_count)
                ->modifyQueryUsing(fn ($query) => $query->where('country_id', $country->id))])
            ->all();
    }
}