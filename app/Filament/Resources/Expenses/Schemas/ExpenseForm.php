<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use App\Filament\Support\Options;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')->label('Datum')->required()->default(today()),
                TextInput::make('amount_eur')->label('Bedrag')->numeric()->prefix('€')->required(),
                Select::make('category')->label('Categorie')->options(ExpenseCategory::class)->required(),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable(),
                TextInput::make('description')->label('Beschrijving')->maxLength(255)->columnSpanFull(),
            ]);
    }
}
