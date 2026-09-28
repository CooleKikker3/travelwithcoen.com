<?php

namespace App\Filament\Resources\JourneyDays\Schemas;

use App\Enums\DayType;
use App\Enums\Overnight;
use App\Filament\Support\Options;
use App\Filament\Support\TrackingPointSelect;
use App\Models\TrackingPoint;
use App\Support\RouteGeometry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class JourneyDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')->label('Datum')->required()->default(fn () => now(\Filament\Support\Facades\FilamentTimezone::get())->toDateString())->unique(ignoreRecord: true),
                Select::make('type')->label('Soort')->options(DayType::class)->default(DayType::Walk)->required(),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable(),
                DateTimePicker::make('started_at')->label('Gestart')->seconds(false),
                DateTimePicker::make('ended_at')->label('Beëindigd')->seconds(false),
                Select::make('overnight')->label('Waar ik sliep')->options(Overnight::class),
                TrackingPointSelect::make('start_point_id', 'GPS-locatie bij vertrek')->default(null),
                TrackingPointSelect::make('end_point_id', 'GPS-locatie bij aankomst')->default(null),
                TextInput::make('start_location')->label('Plaatsnaam vertrek (zichtbaar op de website)')->placeholder('bijv. Lisse')->maxLength(150),
                TextInput::make('end_location')->label('Plaatsnaam aankomst (zichtbaar op de website)')->placeholder('bijv. Haarlem')->maxLength(150),
                TextInput::make('distance_km')
                    ->label('Afstand')
                    ->numeric()
                    ->suffix('km')
                    ->suffixAction(Action::make('fromTracking')
                        ->icon('heroicon-o-map')
                        ->tooltip('Berekenen uit de trackingpunten van deze dag')
                        ->action(fn (Get $get, Set $set) => $set('distance_km', self::trackedDistance($get('date'))))),
                TextInput::make('walking_minutes')->label('Looptijd')->numeric()->suffix('min'),
                Textarea::make('notes')->label('Privénotities')->rows(3)->columnSpanFull(),
            ]);
    }

    private static function trackedDistance(?string $date): ?float
    {
        if (! $date) {
            return null;
        }

        $points = TrackingPoint::whereDate('recorded_at', $date)->orderBy('recorded_at')->get(['latitude', 'longitude'])
            ->map(fn ($p) => [$p->latitude, $p->longitude])->all();

        return round(RouteGeometry::distanceKm($points), 1);
    }
}
