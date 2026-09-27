<?php

namespace App\Filament\Resources\JourneyEvents\Pages;

use App\Filament\Resources\JourneyEvents\JourneyEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJourneyEvent extends EditRecord
{
    protected static string $resource = JourneyEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
