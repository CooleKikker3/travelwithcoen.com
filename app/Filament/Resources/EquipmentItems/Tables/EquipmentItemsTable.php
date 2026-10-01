<?php

namespace App\Filament\Resources\EquipmentItems\Tables;

use App\Enums\EquipmentCategory;
use App\Models\EquipmentItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
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
                IconColumn::make('cover')->label('Foto')->state(fn (EquipmentItem $record) => filled($record->cover_image))->boolean(),
                IconColumn::make('english')->label('EN')->state(fn (EquipmentItem $record) => $record->isTranslated('en'))->boolean(),
                IconColumn::make('is_public')->label('Openbaar')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categorie')->options(EquipmentCategory::class),
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
