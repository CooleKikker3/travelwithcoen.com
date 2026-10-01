<?php

namespace App\Filament\Resources\GalleryItems\Schemas;

use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use App\Models\GalleryItem;
use App\Support\MediaStorage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GalleryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        $isYoutube = fn (?GalleryItem $record) => $record?->isYoutube() ?? false;

        return $schema
            ->components([
                self::upload()
                    ->required(fn (?GalleryItem $record) => ! $isYoutube($record))
                    ->hidden($isYoutube)
                    ->columnSpanFull(),
                TextInput::make('youtube_id')
                    ->label('YouTube video')
                    ->disabled()
                    ->visible($isYoutube)
                    ->helperText('Opgehaald van je YouTube-kanaal. Het Engelse bijschrift volgt de YouTube-titel.')
                    ->columnSpanFull(),
                self::caption(),
                ...self::links(),
                DateTimePicker::make('taken_at')->label('Datum')->helperText('Wordt uit de foto zelf gehaald als je het leeg laat.'),
            ]);
    }

    public static function upload(string $name = 'path'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Foto of video')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v'])
            ->disk(MediaStorage::diskName())
            // Resize in the browser first: much smaller uploads on a weak connection.
            ->imageResizeTargetWidth('2400')
            ->imageResizeTargetHeight('2400')
            ->imageResizeMode('contain')
            ->imageResizeUpscale(false)
            ->directory('gallery')
            ->maxSize(512000)
            ->helperText('Foto\'s worden verkleind en locatiegegevens (GPS) worden verwijderd.');
    }

    /** One or two sentences about the photo or video, per language. */
    public static function caption()
    {
        return TranslatableTabs::make(fn (string $locale) => [
            Textarea::make("caption.{$locale}")
                ->label('Over deze foto of video')
                ->placeholder('Een of twee zinnen (optioneel)')
                ->rows(2)
                ->maxLength(300),
        ], main: 'nl');
    }

    /** Links to country/article/day, shared with the bulk upload action. */
    public static function links(): array
    {
        return [
            Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable(),
            Select::make('article_id')->label('Artikel')->options(fn () => Options::articles())->searchable(),
            Select::make('journey_day_id')->label('Reisdag')->options(fn () => Options::days())->searchable(),
            Toggle::make('is_public')->label('Openbaar')->default(true),
            Toggle::make('is_sensitive')
                ->label('Gevoelige inhoud')
                ->helperText('Bijv. een blessure: vervaagd met een waarschuwing tot de bezoeker hem wil zien.'),
        ];
    }
}
