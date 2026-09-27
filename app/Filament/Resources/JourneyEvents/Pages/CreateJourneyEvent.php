<?php

namespace App\Filament\Resources\JourneyEvents\Pages;

use App\Filament\Resources\JourneyEvents\JourneyEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJourneyEvent extends CreateRecord
{
    protected static string $resource = JourneyEventResource::class;
}
