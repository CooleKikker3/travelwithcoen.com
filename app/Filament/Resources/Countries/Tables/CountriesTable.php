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
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#'),
                TextColumn::make('name')
                    ->state(fn (Country $record) => $record->flag().' '.$record->translate('name', 'en')),
                TextColumn::make('iso_code')->label('ISO'),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_published')->label('Visible')->boolean(),
                TextColumn::make('articles_count')->counts('articles')->label('Articles'),
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
