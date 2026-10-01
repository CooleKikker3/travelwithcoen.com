<?php

namespace App\Filament\Resources\EquipmentItems\Schemas;

use App\Enums\EquipmentCategory;
use App\Filament\Blocks\GearImageBlock;
use App\Filament\Support\TranslatableTabs;
use App\Support\MediaStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Like the article form: content per language on the left, the details on the right. */
class EquipmentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Inhoud')
                    ->description('Nederlands is verplicht. Laat je Engels leeg, dan toont de Engelse site de Nederlandse tekst.')
                    ->columnSpan(2)
                    ->schema([
                        TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                            TextInput::make("name.{$locale}")->label('Naam')->required($isDefault)->maxLength(150),
                            TextInput::make("slug.{$locale}")->label('URL-naam')
                                ->helperText('Laat leeg om hem uit de naam te maken.')
                                ->alphaDash()
                                ->maxLength(150),
                            Textarea::make("excerpt.{$locale}")->label('Korte beschrijving')
                                ->helperText('Staat op de kaart in het overzicht.')
                                ->rows(2)
                                ->maxLength(400),
                            RichEditor::make("body.{$locale}")->label('Lange beschrijving')
                                ->helperText('Waarom je het koos, hoe het bevalt… Foto\'s tussen de tekst met het blok "Afbeelding met bijschrift" (werkbalk, blokken-icoon).')
                                ->customBlocks([GearImageBlock::class])
                                ->fileAttachments(false),
                        ], main: 'nl'),
                    ]),

                Section::make('Details')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('category')->label('Categorie')->options(EquipmentCategory::class)->required(),
                        TextInput::make('brand')->label('Merk')->maxLength(100),
                        TextInput::make('model')->label('Model')->maxLength(100),
                        FileUpload::make('cover_image')->label('Omslagfoto')
                            ->image()
                            ->disk(MediaStorage::diskName())
                            // Resize in the browser first: much smaller uploads on a weak connection.
                            ->imageResizeTargetWidth('2400')
                            ->imageResizeTargetHeight('2400')
                            ->imageResizeMode('contain')
                            ->imageResizeUpscale(false)
                            ->directory('equipment/covers')
                            ->maxSize(8192),
                        Repeater::make('specs')->label('Specificaties')
                            ->schema([
                                TextInput::make('label')->label('Wat')->placeholder('bijv. Gewicht')->required()->maxLength(60),
                                TextInput::make('value')->label('Waarde')->placeholder('bijv. 1150 g')->required()->maxLength(120),
                            ])
                            ->columns(2)
                            ->addActionLabel('Specificatie toevoegen')
                            ->reorderable()
                            ->defaultItems(0),
                        Toggle::make('is_public')->label('Tonen op de website')->default(true),
                    ]),
            ]);
    }
}
