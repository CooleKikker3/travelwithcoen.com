<?php

namespace App\Filament\Resources\JourneyEvents\Pages;

use App\Filament\Resources\JourneyEvents\JourneyEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJourneyEvents extends ListRecords
{
    protected static string $resource = JourneyEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
