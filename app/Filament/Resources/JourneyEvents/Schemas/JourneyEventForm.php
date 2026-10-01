<?php

namespace App\Filament\Resources\JourneyEvents\Schemas;

use App\Enums\EventType;
use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class JourneyEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                    TextInput::make("title.{$locale}")->label('Titel')->required($isDefault)->maxLength(150),
                    Textarea::make("description.{$locale}")->label('Beschrijving')->rows(3),
                ], main: 'nl'),
                DateTimePicker::make('occurred_at')->label('Wanneer')->required()->default(now()),
                Select::make('type')->label('Soort')->options(EventType::class)->required(),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable(),
                TextInput::make('latitude')->label('Breedtegraad')->numeric()->minValue(-90)->maxValue(90)->helperText('Optioneel: toont de gebeurtenis op de kaarten.'),
                TextInput::make('longitude')->label('Lengtegraad')->numeric()->minValue(-180)->maxValue(180),
                Toggle::make('is_public')->label('Openbaar')->default(true)
                    ->helperText('Openbare gebeurtenissen krijgen voor bezoekers dezelfde vertraging als je locatie.'),
            ]);
    }
}
