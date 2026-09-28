<?php

namespace App\Filament\Resources\JourneyEvents;

use App\Filament\Resources\JourneyEvents\Pages\CreateJourneyEvent;
use App\Filament\Resources\JourneyEvents\Pages\EditJourneyEvent;
use App\Filament\Resources\JourneyEvents\Pages\ListJourneyEvents;
use App\Filament\Resources\JourneyEvents\Schemas\JourneyEventForm;
use App\Filament\Resources\JourneyEvents\Tables\JourneyEventsTable;
use App\Models\JourneyEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JourneyEventResource extends Resource
{
    protected static ?string $model = JourneyEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Reis';

    protected static ?string $modelLabel = 'gebeurtenis';

    protected static ?string $pluralModelLabel = 'Gebeurtenissen';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return JourneyEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JourneyEventsTable::configure($table);
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
            'index' => ListJourneyEvents::route('/'),
            'create' => CreateJourneyEvent::route('/create'),
            'edit' => EditJourneyEvent::route('/{record}/edit'),
        ];
    }
}
