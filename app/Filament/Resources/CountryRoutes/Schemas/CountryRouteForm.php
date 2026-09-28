<?php

namespace App\Filament\Resources\CountryRoutes\Schemas;

use App\Enums\RouteType;
use App\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CountryRouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('country_id')
                            ->label('Land')
                            ->options(fn () => Country::orderBy('sort_order')->get()
                                ->mapWithKeys(fn (Country $country) => [$country->id => $country->flag().' '.$country->translate('name', 'en')]))
                            ->required()
                            ->searchable(),
                        Select::make('type')->label('Soort')
                            ->options(RouteType::class)
                            ->default(RouteType::Planned)
                            ->required()
                            ->helperText('Gepland = het plan. Gelopen = wat ik echt gelopen heb.'),
                        Textarea::make('notes')->label('Notities')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
