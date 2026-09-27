<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\PreparationTopic;
use App\Filament\Support\TranslatableTabs;
use App\Models\Country;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Content')
                    ->description('English is required. Leave Dutch empty and the site shows the English version with a notice.')
                    ->columnSpan(2)
                    ->schema([
                        TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                            TextInput::make("title.{$locale}")
                                ->label('Title')
                                ->required($isDefault)
                                ->maxLength(200),
                            TextInput::make("slug.{$locale}")
                                ->label('Slug')
                                ->helperText('Leave empty to generate it from the title.')
                                ->alphaDash()
                                ->maxLength(200),
                            Textarea::make("excerpt.{$locale}")
                                ->label('Excerpt')
                                ->rows(2)
                                ->maxLength(400),
                            RichEditor::make("body.{$locale}")
                                ->label('Body')
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('articles/attachments'),
                        ]),
                    ]),

                Section::make('Publication')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('type')
                            ->options(ArticleType::class)
                            ->default(ArticleType::Preparation)
                            ->required()
                            ->live(),
                        Select::make('topic')
                            ->label('Preparation topic')
                            ->options(PreparationTopic::class)
                            ->visible(fn (Get $get) => self::isPreparation($get('type'))),
                        Select::make('status')
                            ->options(ArticleStatus::class)
                            ->default(ArticleStatus::Draft)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->default(now())
                            ->helperText('A future date schedules the article.'),
                        Select::make('country_id')
                            ->label('Country')
                            ->options(fn () => Country::orderBy('sort_order')->get()
                                ->mapWithKeys(fn (Country $country) => [$country->id => $country->flag().' '.$country->translate('name', 'en')]))
                            ->searchable(),
                        FileUpload::make('cover_image')
                            ->image()
                            ->disk('public')
                            ->directory('articles/covers')
                            ->maxSize(8192),
                        TagsInput::make('tags'),
                    ]),
            ]);
    }

    private static function isPreparation(mixed $type): bool
    {
        return ($type instanceof ArticleType ? $type : ArticleType::tryFrom((string) $type)) === ArticleType::Preparation;
    }
}
