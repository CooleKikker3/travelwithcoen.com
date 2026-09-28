<?php

namespace App\Filament\Resources\EquipmentItems\Schemas;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Filament\Support\TranslatableTabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EquipmentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(fn (string $locale, bool $isDefault) => [
                    TextInput::make("name.{$locale}")->label('Naam')->required($isDefault)->maxLength(150),
                    Textarea::make("reason.{$locale}")->label('Waarom ik het koos')->rows(2),
                    Textarea::make("review.{$locale}")->label('Beoordeling na gebruik')->rows(3),
                ]),
                Select::make('category')->label('Categorie')->options(EquipmentCategory::class)->required(),
                Select::make('status')->label('Status')->options(EquipmentStatus::class)->default(EquipmentStatus::Planned)->required()
                    ->helperText('"Testen" en "In de rugzak" tellen mee voor het rugzakgewicht.'),
                TextInput::make('brand')->label('Merk')->maxLength(100),
                TextInput::make('model')->label('Model')->maxLength(100),
                TextInput::make('weight_g')->label('Gewicht')->numeric()->suffix('g'),
                TextInput::make('price')->label('Prijs')->numeric()->prefix('€'),
                TextInput::make('sort_order')->label('Volgorde')->numeric()->default(0),
                Toggle::make('is_worn')->label('Gedragen, niet in de rugzak'),
                Toggle::make('is_public')->label('Tonen op de website')->default(true),
            ]);
    }
}
