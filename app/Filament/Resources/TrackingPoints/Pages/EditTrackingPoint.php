<?php

namespace App\Filament\Resources\TrackingPoints\Pages;

use App\Filament\Resources\TrackingPoints\TrackingPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrackingPoint extends EditRecord
{
    protected static string $resource = TrackingPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
