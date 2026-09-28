<?php

namespace App\Filament\Resources\TrackingPoints\Schemas;

use App\Filament\Support\Options;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/** For manual points and corrections; normal input comes from imports or the ingest API. */
class TrackingPointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('latitude')->label('Breedtegraad')->numeric()->required()->minValue(-90)->maxValue(90),
                TextInput::make('longitude')->label('Lengtegraad')->numeric()->required()->minValue(-180)->maxValue(180),
                DateTimePicker::make('recorded_at')->label('Tijdstip')->required()->seconds(),
                DateTimePicker::make('received_at')->label('Ontvangen')->required()->default(now())->seconds(),
                TextInput::make('altitude')->label('Hoogte')->numeric()->suffix('m'),
                TextInput::make('source')->label('Bron')->required()->default('manual')->maxLength(30),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable(),
            ]);
    }
}
