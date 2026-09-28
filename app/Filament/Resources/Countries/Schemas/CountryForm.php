<?php

namespace App\Filament\Resources\Countries\Schemas;

use App\Enums\CountryStatus;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Inhoud')
                    ->columnSpan(2)
                    ->schema([
                        TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                            TextInput::make("name.{$locale}")
                                ->label('Naam')
                                ->required($isDefault)
                                ->maxLength(100),
                            TextInput::make("slug.{$locale}")
                                ->label('URL-naam')
                                ->helperText('Laat leeg om hem uit de naam te maken.')
                                ->alphaDash()
                                ->maxLength(100),
                            Textarea::make("intro.{$locale}")
                                ->label('Korte intro')
                                ->rows(3),
                            RichEditor::make("story.{$locale}")
                                ->label('Mijn verhaal over dit land'),
                        ]),
                    ]),

                Section::make('Instellingen')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('iso_code')
                            ->label('Landcode')
                            ->helperText('Twee letters, bijv. DE. Voor de vlag.')
                            ->required()
                            ->length(2)
                            ->alpha()
                            ->dehydrateStateUsing(fn (string $state) => strtoupper($state))
                            ->unique(ignoreRecord: true),
                        Select::make('status')->label('Status')
                            ->options(CountryStatus::class)
                            ->default(CountryStatus::Tentative)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Volgorde op de route')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_published')
                            ->label('Zichtbaar op de website')
                            ->default(true),
                    ]),
            ]);
    }
}
