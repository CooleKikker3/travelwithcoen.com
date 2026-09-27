<?php

namespace App\Filament\Resources\CountryRoutes\Pages;

use App\Filament\Resources\CountryRoutes\CountryRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCountryRoutes extends ListRecords
{
    protected static string $resource = CountryRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
