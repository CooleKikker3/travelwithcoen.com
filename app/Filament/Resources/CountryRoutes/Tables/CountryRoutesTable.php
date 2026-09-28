<?php

namespace App\Filament\Resources\CountryRoutes\Tables;

use App\Enums\RouteType;
use App\Filament\Pages\RoutePlanner;
use App\Models\Country;
use App\Models\CountryRoute;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CountryRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('country')->withCount('segments'))
            ->defaultSort('sort_order')
            // Order of the route pieces within a country: drag and drop (per country tab).
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn ($action, bool $isReordering) => $action->button()->label($isReordering ? 'Klaar' : 'Volgorde slepen'))
            // A route piece is edited in the route planner (parts, GPX, story).
            ->recordUrl(fn (CountryRoute $record) => RoutePlanner::getUrl(['route' => $record->id]))
            ->columns([
                TextColumn::make('country')->label('Land')
                    ->state(fn (CountryRoute $record) => $record->country->flag().' '.$record->country->translate('name', 'en')),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('name')->label('Titel')->state(fn (CountryRoute $record) => $record->label())->searchable(),
                TextColumn::make('segments_count')->label('Stukken')->numeric(),
                TextColumn::make('distance_km')->label('Afstand')->suffix(' km')->numeric(1)->sortable(),
                TextColumn::make('updated_at')->label('Bijgewerkt')->since(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Soort')->options(RouteType::class),
                SelectFilter::make('country_id')
                    ->label('Land')
                    ->options(fn () => Country::orderBy('sort_order')->get()->mapWithKeys(fn (Country $c) => [$c->id => $c->translate('name', 'en')])),
            ])
            ->headerActions([
                Action::make('draw')->label('Nieuw routestuk')->icon('heroicon-o-plus')->url(RoutePlanner::getUrl()),
            ])
            ->recordActions([
                Action::make('planner')->label('Bewerken')->icon('heroicon-o-map')->url(fn (CountryRoute $record) => RoutePlanner::getUrl(['route' => $record->id])),
                EditAction::make()->label('Notities'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
