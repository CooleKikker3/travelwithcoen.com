<?php

namespace App\Filament\Resources\EquipmentItems\Tables;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\EquipmentItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquipmentItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Naam')->state(fn (EquipmentItem $record) => $record->translate('name', 'nl'))
                    ->description(fn (EquipmentItem $record) => trim($record->brand.' '.$record->model) ?: null),
                TextColumn::make('category')->label('Categorie')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('weight_g')->label('Gewicht')->suffix(' g')->numeric()->summarize(Sum::make()->label('Totaal g')),
                TextColumn::make('price')->label('Prijs')->money('EUR')->summarize(Sum::make()->money('EUR')),
                IconColumn::make('is_worn')->label('Gedragen')->boolean(),
                IconColumn::make('is_public')->label('Openbaar')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categorie')->options(EquipmentCategory::class),
                SelectFilter::make('status')->label('Status')->options(EquipmentStatus::class),
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
