<?php

namespace App\Filament\Resources\JourneyDays\Pages;

use App\Filament\Resources\JourneyDays\JourneyDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJourneyDays extends ListRecords
{
    protected static string $resource = JourneyDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
