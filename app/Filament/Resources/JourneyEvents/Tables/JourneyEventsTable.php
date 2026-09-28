<?php

namespace App\Filament\Resources\JourneyEvents\Tables;

use App\Models\JourneyEvent;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JourneyEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('Wanneer')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('title')->label('Titel')->state(fn (JourneyEvent $record) => $record->translate('title', 'en'))->wrap(),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('country.iso_code')->label('Land'),
                IconColumn::make('is_public')->label('Openbaar')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
