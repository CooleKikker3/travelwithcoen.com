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
                            ->label('Country')
                            ->options(fn () => Country::orderBy('sort_order')->get()
                                ->mapWithKeys(fn (Country $country) => [$country->id => $country->flag().' '.$country->translate('name', 'en')]))
                            ->required()
                            ->searchable(),
                        Select::make('type')
                            ->options(RouteType::class)
                            ->default(RouteType::Planned)
                            ->required()
                            ->helperText('Planned = the plan. Actual = what I really walked. They are never merged.'),
                        TextInput::make('name')
                            ->label('Name (internal)')
                            ->placeholder('e.g. Stage 1: Lisse → Den Helder')
                            ->maxLength(150),
                        TextInput::make('sort_order')
                            ->label('Order within the country')
                            ->numeric()
                            ->default(0),
                        FileUpload::make('gpx_path')
                            ->label('GPX file')
                            ->helperText('Export from Komoot, gpx.studio, a GPS watch, etc. Uploading a new file replaces the route.')
                            ->disk('local')
                            ->directory('gpx')
                            ->preserveFilenames()
                            ->maxSize(20480)
                            ->rules(['extensions:gpx', fn () => self::validGpx()])
                            ->columnSpanFull(),
                        Textarea::make('notes')
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
                    $fail('This GPX file contains no track or route points.');
                }
            } catch (Throwable) {
                $fail('This is not a valid GPX file.');
            }
        };
    }
}
