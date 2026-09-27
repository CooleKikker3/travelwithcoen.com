<?php

namespace App\Filament\Resources\JourneyDays\Schemas;

use App\Enums\DayType;
use App\Enums\Overnight;
use App\Filament\Support\Options;
use App\Models\TrackingPoint;
use App\Support\RouteGeometry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
                DatePicker::make('date')->required()->default(today())->unique(ignoreRecord: true),
                Select::make('type')->options(DayType::class)->default(DayType::Walk)->required(),
                Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable(),
                Select::make('overnight')->label('Where I slept')->options(Overnight::class),
                TextInput::make('start_location')->maxLength(150),
                TextInput::make('end_location')->maxLength(150),
                TextInput::make('distance_km')
                    ->label('Distance')
                    ->numeric()
                    ->suffix('km')
                    ->suffixAction(Action::make('fromTracking')
                        ->icon('heroicon-o-map')
                        ->tooltip('Calculate from the tracking points of this date')
                        ->action(fn (Get $get, Set $set) => $set('distance_km', self::trackedDistance($get('date'))))),
                TextInput::make('walking_minutes')->label('Walking time')->numeric()->suffix('min'),
                Textarea::make('notes')->label('Private notes')->rows(3)->columnSpanFull(),
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
