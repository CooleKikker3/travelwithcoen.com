<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\ExpenseCategory;
use App\Filament\Support\Options;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('j M Y')->sortable(),
                TextColumn::make('amount_eur')->label('Amount')->money('EUR')->sortable()->summarize(Sum::make()->money('EUR')),
                TextColumn::make('category')->badge(),
                TextColumn::make('country.iso_code')->label('Country'),
                TextColumn::make('description')->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('category')->options(ExpenseCategory::class),
                SelectFilter::make('country_id')->label('Country')->options(fn () => Options::countries()),
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
