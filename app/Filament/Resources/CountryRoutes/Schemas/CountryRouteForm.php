<?php

namespace App\Filament\Resources\CountryRoutes\Schemas;

use App\Enums\RouteType;
use App\Models\Country;
use App\Services\GpxImporter;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

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
                        TextInput::make('name')
                            ->label('Naam (intern)')
                            ->placeholder('bijv. Etappe 1: Lisse → Den Helder')
                            ->maxLength(150),
                        TextInput::make('sort_order')
                            ->label('Volgorde binnen het land')
                            ->numeric()
                            ->default(0),
                        FileUpload::make('gpx_path')
                            ->label('GPX-bestand')
                            ->helperText('Optioneel: export uit Komoot, gpx.studio, een GPS-horloge enz. Geen GPX? Teken de route in de Routeplanner.')
                            ->disk('local')
                            ->directory('gpx')
                            ->preserveFilenames()
                            ->maxSize(20480)
                            ->rules(['extensions:gpx', fn () => self::validGpx()])
                            ->columnSpanFull(),
                        Textarea::make('notes')->label('Notities')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function validGpx(): Closure
    {
        return function (string $attribute, mixed $file, Closure $fail) {
            try {
                if (count(app(GpxImporter::class)->parse(file_get_contents($file->getRealPath()))) < 2) {
                    $fail('Dit GPX-bestand bevat geen route- of trackpunten.');
                }
            } catch (Throwable) {
                $fail('Dit is geen geldig GPX-bestand.');
            }
        };
    }
}
