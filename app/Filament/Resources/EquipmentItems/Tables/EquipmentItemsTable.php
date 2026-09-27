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
                TextColumn::make('name')->state(fn (EquipmentItem $record) => $record->translate('name', 'en'))
                    ->description(fn (EquipmentItem $record) => trim($record->brand.' '.$record->model) ?: null),
                TextColumn::make('category')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('weight_g')->label('Weight')->suffix(' g')->numeric()->summarize(Sum::make()->label('Total g')),
                TextColumn::make('price')->money('EUR')->summarize(Sum::make()->money('EUR')),
                IconColumn::make('is_worn')->label('Worn')->boolean(),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->options(EquipmentCategory::class),
                SelectFilter::make('status')->options(EquipmentStatus::class),
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
