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
                TextInput::make('latitude')->numeric()->required()->minValue(-90)->maxValue(90),
                TextInput::make('longitude')->numeric()->required()->minValue(-180)->maxValue(180),
                DateTimePicker::make('recorded_at')->required()->seconds(),
                DateTimePicker::make('received_at')->required()->default(now())->seconds(),
                TextInput::make('altitude')->numeric()->suffix('m'),
                TextInput::make('source')->required()->default('manual')->maxLength(30),
                Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable(),
            ]);
    }
}
