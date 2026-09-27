<?php

namespace App\Filament\Resources\GalleryItems\Tables;

use App\Filament\Resources\GalleryItems\Schemas\GalleryItemForm;
use App\Models\GalleryItem;
use App\Services\YouTubeSync;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class GalleryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('taken_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')->label('')->square()
                    ->state(fn (GalleryItem $record) => $record->thumbnailUrl()),
                TextColumn::make('kind')->badge()->color(fn (string $state) => match ($state) {
                    'youtube' => 'danger',
                    'video' => 'warning',
                    default => 'success',
                }),
                TextColumn::make('caption')->state(fn (GalleryItem $record) => $record->translate('caption', 'en'))->placeholder('—')->wrap()->limit(80),
                TextColumn::make('article')
                    ->state(fn (GalleryItem $record) => $record->article?->translate('title', 'en'))
                    ->description(fn (GalleryItem $record) => $record->source === 'article' ? 'from article text' : null)
                    ->placeholder('—'),
                TextColumn::make('taken_at')->label('Date')->dateTime('j M Y')->sortable(),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->options(['image' => 'Photos', 'video' => 'Uploaded videos', 'youtube' => 'YouTube']),
                SelectFilter::make('source')->options(['upload' => 'Uploaded', 'article' => 'From articles', 'youtube' => 'YouTube']),
            ])
            ->headerActions([
                Action::make('bulkUpload')
                    ->label('Upload several at once')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->schema([
                        GalleryItemForm::upload('files')->label('Photos and videos')->multiple()->maxFiles(50)->required(),
                        ...GalleryItemForm::links(),
                    ])
                    ->action(function (array $data) {
                        foreach ($data['files'] as $path) {
                            GalleryItem::create(['path' => $path] + collect($data)->except('files')->all());
                        }

                        Notification::make()->success()->title(count($data['files']).' items uploaded')->body('Add a caption per item via Edit.')->send();
                    }),
                Action::make('syncYoutube')
                    ->label('Sync YouTube now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn () => filled(config('travel.youtube_channel')))
                    ->action(function () {
                        try {
                            Notification::make()->success()->title(app(YouTubeSync::class)->sync().' YouTube videos synced')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('YouTube sync failed')->body($e->getMessage())->send();
                        }
                    }),
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
