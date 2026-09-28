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
            ->modifyQueryUsing(fn ($query) => $query->select('journey_days.*')->withNumber())
            ->columns([
                TextColumn::make('name')->label('Dag')->state(fn (\App\Models\JourneyDay $record) => $record->name())->weight('bold'),
                TextColumn::make('date')->label('Datum')->date('D j M Y')->sortable(),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('country.iso_code')->label('Land'),
                TextColumn::make('end_location')->label('Naar')->placeholder('—'),
                TextColumn::make('distance_km')->label('Km')->numeric(1)->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Totaal')),
                TextColumn::make('overnight')->label('Overnachting')->badge()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Soort')->options(DayType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
