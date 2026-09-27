<?php

namespace App\Filament\Resources\GalleryItems\Tables;

use App\Filament\Resources\GalleryItems\Schemas\GalleryItemForm;
use App\Models\GalleryItem;
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

class GalleryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('path')->label('')->disk('public')->square()
                    ->state(fn (GalleryItem $record) => $record->isVideo() ? null : $record->path),
                TextColumn::make('kind')->badge(),
                TextColumn::make('caption')->state(fn (GalleryItem $record) => $record->translate('caption', 'en'))->placeholder('—')->wrap(),
                TextColumn::make('article')
                    ->state(fn (GalleryItem $record) => $record->article?->translate('title', 'en'))
                    ->description(fn (GalleryItem $record) => $record->source === 'article' ? 'from article text' : null)
                    ->placeholder('—'),
                TextColumn::make('taken_at')->dateTime('j M Y')->sortable(),
                TextColumn::make('country.iso_code')->label('Country'),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->options(['image' => 'Photos', 'video' => 'Videos']),
                SelectFilter::make('source')->options(['upload' => 'Uploaded', 'article' => 'From articles']),
            ])
            ->headerActions([
                Action::make('bulkUpload')
                    ->label('Upload photos & videos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        GalleryItemForm::upload('files')->label('Photos and videos')->multiple()->maxFiles(50)->required(),
                        ...GalleryItemForm::links(),
                    ])
                    ->action(function (array $data) {
                        foreach ($data['files'] as $path) {
                            GalleryItem::create(['path' => $path] + collect($data)->except('files')->all());
                        }

                        Notification::make()->success()->title(count($data['files']).' items uploaded')->send();
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
