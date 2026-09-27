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
                    TextInput::make("name.{$locale}")->label('Name')->required($isDefault)->maxLength(150),
                    Textarea::make("reason.{$locale}")->label('Why I chose it')->rows(2),
                    Textarea::make("review.{$locale}")->label('Review after use')->rows(3),
                ]),
                Select::make('category')->options(EquipmentCategory::class)->required(),
                Select::make('status')->options(EquipmentStatus::class)->default(EquipmentStatus::Planned)->required()
                    ->helperText('"Testing" and "In the pack" count towards the pack weight.'),
                TextInput::make('brand')->maxLength(100),
                TextInput::make('model')->maxLength(100),
                TextInput::make('weight_g')->label('Weight')->numeric()->suffix('g'),
                TextInput::make('price')->numeric()->prefix('€'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_worn')->label('Worn, not in the pack'),
                Toggle::make('is_public')->label('Show on the website')->default(true),
            ]);
    }
}
