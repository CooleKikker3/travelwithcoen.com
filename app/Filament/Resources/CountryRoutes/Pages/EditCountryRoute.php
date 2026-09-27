<?php

namespace App\Filament\Resources\CountryRoutes\Pages;

use App\Filament\Resources\CountryRoutes\CountryRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCountryRoute extends EditRecord
{
    protected static string $resource = CountryRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
