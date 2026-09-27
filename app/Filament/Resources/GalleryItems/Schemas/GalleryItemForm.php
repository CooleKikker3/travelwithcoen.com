<?php

namespace App\Filament\Resources\GalleryItems\Schemas;

use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GalleryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::upload()->required()->columnSpanFull(),
                TranslatableTabs::make(fn (string $locale) => [
                    TextInput::make("caption.{$locale}")->label('Caption')->maxLength(300),
                ]),
                ...self::links(),
                DateTimePicker::make('taken_at')->helperText('Filled from the photo itself when empty.'),
            ]);
    }

    /** Photos and videos that don't go to YouTube. */
    public static function upload(string $name = 'path'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Photo or video')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v'])
            ->disk('public')
            ->directory('gallery')
            ->maxSize(512000)
            ->helperText('Photos are resized; location data (GPS) is removed from photos, and from videos when ffmpeg is installed.');
    }

    /** Links to country/article/day, shared with the bulk upload action. */
    public static function links(): array
    {
        return [
            Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable(),
            Select::make('article_id')->label('Article')->options(fn () => Options::articles())->searchable(),
            Select::make('journey_day_id')->label('Journey day')->options(fn () => Options::days())->searchable(),
            Toggle::make('is_public')->label('Public')->default(true),
        ];
    }
}
