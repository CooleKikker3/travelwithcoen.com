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
                    TextInput::make("title.{$locale}")->label('Title')->required($isDefault)->maxLength(150),
                    Textarea::make("description.{$locale}")->label('Description')->rows(3),
                ]),
                DateTimePicker::make('occurred_at')->label('When')->required()->default(now()),
                Select::make('type')->options(EventType::class)->required(),
                Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable(),
                TextInput::make('latitude')->numeric()->minValue(-90)->maxValue(90)->helperText('Optional: shows the event on the maps.'),
                TextInput::make('longitude')->numeric()->minValue(-180)->maxValue(180),
                Toggle::make('is_public')->label('Public')->default(true)
                    ->helperText('Public events follow the same delay as tracking for normal visitors.'),
            ]);
    }
}
