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
                Section::make('Inhoud')
                    ->description('Nederlands is verplicht. Laat je Engels leeg, dan toont de Engelse site de Nederlandse versie met een melding.')
                    ->columnSpan(2)
                    ->schema([
                        TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                            TextInput::make("title.{$locale}")
                                ->label('Titel')
                                ->required($isDefault)
                                ->maxLength(200),
                            TextInput::make("slug.{$locale}")
                                ->label('URL-naam')
                                ->helperText('Laat leeg om hem uit de titel te maken.')
                                ->alphaDash()
                                ->maxLength(200),
                            Textarea::make("excerpt.{$locale}")
                                ->label('Samenvatting')
                                ->rows(2)
                                ->maxLength(400),
                            RichEditor::make("body.{$locale}")
                                ->label('Tekst')
                                ->helperText('Gebruik het blok "Afbeelding met bijschrift" (werkbalk, blokken-icoon) om foto\'s in de tekst te zetten. Ze komen ook in de galerij.')
                                ->customBlocks([ImageBlock::class])
                                ->fileAttachments(false),
                        ], main: 'nl'),
                    ]),

                Section::make('Publicatie')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('type')->label('Soort')
                            ->options(ArticleType::class)
                            ->default(ArticleType::Diary)
                            ->required(),
                        Select::make('status')->label('Status')
                            ->options(ArticleStatus::class)
                            ->default(ArticleStatus::Published)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publicatiedatum')
                            ->default(now())
                            ->helperText('Een datum in de toekomst plant het artikel in. Dagboekverhalen zien bezoekers pas na de vertraging van je locatie.'),
                        TagsInput::make('tags')->label('Tags')
                            ->suggestions(fn () => Article::allTags())
                            ->helperText('Bijv. uitrusting, training, kamperen, visa. Bezoekers kunnen op tags filteren.'),
                        Select::make('country_id')
                            ->label('Land')
                            ->options(fn () => Options::countries())
                            ->searchable(),
                        Select::make('journey_day_id')
                            ->label('Reisdag')
                            ->options(fn () => Options::days())
                            ->searchable(),
                        FileUpload::make('cover_image')->label('Omslagfoto')
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
