<?php

namespace App\Filament\Resources\CountryRoutes\Pages;

use App\Filament\Resources\CountryRoutes\CountryRouteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCountryRoute extends CreateRecord
{
    protected static string $resource = CountryRouteResource::class;
}
