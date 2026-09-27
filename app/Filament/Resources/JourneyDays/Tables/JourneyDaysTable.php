<?php

namespace App\Filament\Resources\JourneyDays\Tables;

use App\Enums\DayType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JourneyDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('D j M Y')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('country.iso_code')->label('Country'),
                TextColumn::make('end_location')->label('To')->placeholder('—'),
                TextColumn::make('distance_km')->label('Km')->numeric(1)->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')),
                TextColumn::make('overnight')->badge()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->options(DayType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
