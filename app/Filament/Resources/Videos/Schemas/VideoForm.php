<?php

namespace App\Filament\Resources\Videos\Schemas;

use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('youtube_id')
                    ->label('YouTube video id')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Videos are synced from YouTube automatically (Settings → YouTube channel). Here you can hide a video or link it to a country/article, and add a Dutch title.')
                    ->columnSpanFull(),
                TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                    TextInput::make("title.{$locale}")->label('Title')->required($isDefault)->maxLength(200),
                    Textarea::make("description.{$locale}")->label('Description')->rows(3),
                ]),
                DatePicker::make('published_on')->default(today()),
                Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable(),
                Select::make('article_id')->label('Article')->options(fn () => Options::articles())->searchable(),
                Toggle::make('is_public')->label('Public')->default(true),
            ]);
    }
}
