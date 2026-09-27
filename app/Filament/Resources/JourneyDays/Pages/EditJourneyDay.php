<?php

namespace App\Filament\Resources\JourneyDays\Pages;

use App\Filament\Resources\JourneyDays\JourneyDayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJourneyDay extends EditRecord
{
    protected static string $resource = JourneyDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
