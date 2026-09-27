<?php

namespace App\Filament\Resources\TrackingPoints\Pages;

use App\Filament\Resources\TrackingPoints\TrackingPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrackingPoints extends ListRecords
{
    protected static string $resource = TrackingPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
