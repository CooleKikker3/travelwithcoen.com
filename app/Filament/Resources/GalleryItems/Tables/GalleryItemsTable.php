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
                TextColumn::make('kind')->label('Soort')->badge()->color(fn (string $state) => match ($state) {
                    'youtube' => 'danger',
                    'video' => 'warning',
                    default => 'success',
                }),
                TextColumn::make('caption')->label('Bijschrift')->state(fn (GalleryItem $record) => $record->translate('caption', 'nl'))->placeholder('—')->wrap()->limit(80),
                TextColumn::make('article')->label('Artikel')
                    ->state(fn (GalleryItem $record) => $record->article?->translate('title', 'nl'))
                    ->description(fn (GalleryItem $record) => $record->source === 'article' ? 'uit artikeltekst' : null)
                    ->placeholder('—'),
                TextColumn::make('taken_at')->label('Datum')->dateTime('j M Y')->sortable(),
                IconColumn::make('is_public')->label('Openbaar')->boolean(),
                IconColumn::make('is_sensitive')->label('Gevoelig')->boolean()->trueIcon('heroicon-o-eye-slash')->trueColor('warning')->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Soort')->options(['image' => 'Photos', 'video' => 'Geüploade video\'s', 'youtube' => 'YouTube']),
                SelectFilter::make('source')->label('Bron')->options(['upload' => 'Uploaded', 'article' => 'From articles', 'youtube' => 'YouTube']),
            ])
            ->headerActions([
                Action::make('bulkUpload')
                    ->label('Meerdere tegelijk uploaden')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->schema([
                        GalleryItemForm::upload('files')->label('Foto\'s en video\'s')->multiple()->maxFiles(50)->required(),
                        ...GalleryItemForm::links(),
                    ])
                    ->action(function (array $data) {
                        foreach ($data['files'] as $path) {
                            GalleryItem::create(['path' => $path] + collect($data)->except('files')->all());
                        }

                        Notification::make()->success()->title(count($data['files']).' items geüpload')->body('Voeg per item een bijschrift toe via Bewerken.')->send();
                    }),
                Action::make('syncYoutube')
                    ->label('YouTube nu ophalen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn () => filled(config('travel.youtube_channel')))
                    ->action(function () {
                        try {
                            Notification::make()->success()->title(app(YouTubeSync::class)->sync().' nieuwe YouTube-video\'s toegevoegd')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('YouTube ophalen mislukt')->body($e->getMessage())->send();
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
