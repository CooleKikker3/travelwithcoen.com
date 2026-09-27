<?php

namespace App\Filament\Resources\JourneyDays;

use App\Filament\Resources\JourneyDays\Pages\CreateJourneyDay;
use App\Filament\Resources\JourneyDays\Pages\EditJourneyDay;
use App\Filament\Resources\JourneyDays\Pages\ListJourneyDays;
use App\Filament\Resources\JourneyDays\Schemas\JourneyDayForm;
use App\Filament\Resources\JourneyDays\Tables\JourneyDaysTable;
use App\Models\JourneyDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JourneyDayResource extends Resource
{
    protected static ?string $model = JourneyDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Journey';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return JourneyDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JourneyDaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJourneyDays::route('/'),
            'create' => CreateJourneyDay::route('/create'),
            'edit' => EditJourneyDay::route('/{record}/edit'),
        ];
    }
}
