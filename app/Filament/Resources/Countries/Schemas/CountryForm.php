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
                Section::make('Content')
                    ->columnSpan(2)
                    ->schema([
                        TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                            TextInput::make("name.{$locale}")
                                ->label('Name')
                                ->required($isDefault)
                                ->maxLength(100),
                            TextInput::make("slug.{$locale}")
                                ->label('Slug')
                                ->helperText('Leave empty to generate it from the name.')
                                ->alphaDash()
                                ->maxLength(100),
                            Textarea::make("intro.{$locale}")
                                ->label('Short intro')
                                ->rows(3),
                            RichEditor::make("story.{$locale}")
                                ->label('My story about this country'),
                        ]),
                    ]),

                Section::make('Settings')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('iso_code')
                            ->label('ISO code')
                            ->helperText('Two letters, e.g. DE. Used for the flag.')
                            ->required()
                            ->length(2)
                            ->alpha()
                            ->dehydrateStateUsing(fn (string $state) => strtoupper($state))
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(CountryStatus::class)
                            ->default(CountryStatus::Tentative)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Order on the route')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_published')
                            ->label('Visible on the website')
                            ->default(true),
                    ]),
            ]);
    }
}
