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
            ->modifyQueryUsing(fn ($query) => $query->with('country')->withCount('points'))
            ->defaultSort('country_id')
            ->columns([
                TextColumn::make('country')->label('Land')
                    ->state(fn (CountryRoute $record) => $record->country->flag().' '.$record->country->translate('name', 'en')),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('name')->label('Naam')->placeholder('—')->searchable(),
                TextColumn::make('points_count')->label('Punten')->numeric(),
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
                Action::make('draw')->label('Teken een route op de kaart')->icon('heroicon-o-pencil-square')->url(RoutePlanner::getUrl()),
            ])
            ->recordActions([
                Action::make('planner')->label('Kaart')->icon('heroicon-o-map')->url(fn (CountryRoute $record) => RoutePlanner::getUrl(['route' => $record->id])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
