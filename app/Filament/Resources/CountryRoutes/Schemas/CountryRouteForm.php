<?php

namespace App\Filament\Resources\CountryRoutes\Schemas;

use App\Enums\RouteType;
use App\Models\Country;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
                                ->mapWithKeys(fn (Country $country) => [$country->id => $country->flag().' '.$country->translate('name', 'nl')]))
                            ->required()
                            ->searchable(),
                        Select::make('type')->label('Soort')
                            ->options(RouteType::class)
                            ->default(RouteType::Planned)
                            ->required()
                            ->helperText('Gepland = het plan. Gelopen = wat ik echt gelopen heb.'),
                        Textarea::make('notes')->label('Notitie bij het hele stuk (privé)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                // Private notes per part (GPX file or drawn piece); the parts themselves are edited in the route planner.
                Repeater::make('segments')
                    ->label('Notities per deel (privé, niet op de website)')
                    ->relationship()
                    ->schema([
                        // Only for the item title; not saved from here.
                        Hidden::make('label')->dehydrated(false),
                        Hidden::make('kind')->dehydrated(false),
                        Textarea::make('notes')->hiddenLabel()->rows(2)->placeholder('bijv. slaapplek, water, twijfels over dit deel'),
                    ])
                    ->itemLabel(fn (array $state) => (($state['label'] ?? null) ?: 'Deel zonder naam').' · '.(($state['kind'] ?? null) === 'gpx' ? 'GPX' : 'getekend'))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
