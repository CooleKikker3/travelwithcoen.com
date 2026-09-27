<?php

namespace App\Filament\Resources\Photos\Schemas;

use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PhotoForm
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

    public static function upload(string $name = 'path'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Photo')
            ->image()
            ->disk('public')
            ->directory('photos')
            ->maxSize(20480)
            ->helperText('Resized on upload; all metadata including GPS location is removed.');
    }

    /** Links to country/article/day/event, shared with the bulk upload action. */
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
