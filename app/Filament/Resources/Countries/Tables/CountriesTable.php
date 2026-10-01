<?php

namespace App\Filament\Resources\Countries\Tables;

use App\Models\Country;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CountriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            // Order of the countries on the route: drag and drop.
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn ($action, bool $isReordering) => $action->button()->label($isReordering ? 'Klaar' : 'Volgorde slepen'))
            ->columns([
                TextColumn::make('name')->label('Naam')
                    ->state(fn (Country $record) => $record->flag().' '.$record->translate('name', 'nl')),
                TextColumn::make('iso_code')->label('ISO'),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('is_published')->label('Zichtbaar')->boolean(),
                TextColumn::make('articles_count')->counts('articles')->label('Artikelen'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
