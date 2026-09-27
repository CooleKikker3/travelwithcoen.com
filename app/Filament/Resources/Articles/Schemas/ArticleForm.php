<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Blocks\ImageBlock;
use App\Filament\Support\Options;
use App\Filament\Support\TranslatableTabs;
use App\Models\Article;
use App\Support\MediaStorage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                                ->helperText('Use the "Image with caption" block (toolbar, blocks icon) to place photos in the text. They also appear in the gallery.')
                                ->customBlocks([ImageBlock::class])
                                ->fileAttachments(false),
                        ]),
                    ]),

                Section::make('Publication')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('type')
                            ->options(ArticleType::class)
                            ->default(ArticleType::Preparation)
                            ->required(),
                        Select::make('status')
                            ->options(ArticleStatus::class)
                            ->default(ArticleStatus::Draft)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->default(now())
                            ->helperText('A future date schedules the article.'),
                        TagsInput::make('tags')
                            ->suggestions(fn () => Article::allTags())
                            ->helperText('E.g. gear, training, camping, visas. Visitors can filter on tags.'),
                        Select::make('country_id')
                            ->label('Country')
                            ->options(fn () => Options::countries())
                            ->searchable(),
                        Select::make('journey_day_id')
                            ->label('Journey day')
                            ->options(fn () => Options::days())
                            ->searchable(),
                        FileUpload::make('cover_image')
                            ->image()
                            ->disk(MediaStorage::diskName())
                            // Resize in the browser first: much smaller uploads on a weak connection.
                            ->imageResizeTargetWidth('2400')
                            ->imageResizeTargetHeight('2400')
                            ->imageResizeMode('contain')
                            ->imageResizeUpscale(false)
                            ->directory('articles/covers')
                            ->maxSize(8192),
                    ]),
            ]);
    }
}
