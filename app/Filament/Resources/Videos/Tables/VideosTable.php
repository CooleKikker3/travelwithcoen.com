<?php

namespace App\Filament\Resources\Videos\Tables;

use App\Models\Video;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_on', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')->label('')->state(fn (Video $record) => $record->thumbnailUrl()),
                TextColumn::make('title')->state(fn (Video $record) => $record->translate('title', 'en'))->wrap(),
                TextColumn::make('published_on')->date('j M Y')->sortable(),
                TextColumn::make('country.iso_code')->label('Country'),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
