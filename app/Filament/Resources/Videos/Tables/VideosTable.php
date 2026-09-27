<?php

namespace App\Filament\Resources\Videos\Tables;

use App\Models\Video;
use App\Services\YouTubeSync;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

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
            ->headerActions([
                Action::make('sync')
                    ->label('Sync with YouTube now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        try {
                            Notification::make()->success()->title(app(YouTubeSync::class)->sync().' videos synced')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('YouTube sync failed')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
