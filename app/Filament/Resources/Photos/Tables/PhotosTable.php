<?php

namespace App\Filament\Resources\Photos\Tables;

use App\Filament\Resources\Photos\Schemas\PhotoForm;
use App\Models\Photo;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('path')->label('')->disk('public')->square(),
                TextColumn::make('caption')->state(fn (Photo $record) => $record->translate('caption', 'en'))->placeholder('—')->wrap(),
                TextColumn::make('taken_at')->dateTime('j M Y')->sortable(),
                TextColumn::make('country.iso_code')->label('Country'),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->headerActions([
                Action::make('bulkUpload')
                    ->label('Upload photos')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        PhotoForm::upload('files')->label('Photos')->multiple()->maxFiles(50)->required(),
                        ...PhotoForm::links(),
                    ])
                    ->action(function (array $data) {
                        foreach ($data['files'] as $path) {
                            Photo::create(['path' => $path] + collect($data)->except('files')->all());
                        }

                        Notification::make()->success()->title(count($data['files']).' photos uploaded')->send();
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
