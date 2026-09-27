<?php

namespace App\Filament\Resources\TrackingPoints;

use App\Filament\Resources\TrackingPoints\Pages\CreateTrackingPoint;
use App\Filament\Resources\TrackingPoints\Pages\EditTrackingPoint;
use App\Filament\Resources\TrackingPoints\Pages\ListTrackingPoints;
use App\Filament\Resources\TrackingPoints\Schemas\TrackingPointForm;
use App\Filament\Resources\TrackingPoints\Tables\TrackingPointsTable;
use App\Models\TrackingPoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrackingPointResource extends Resource
{
    protected static ?string $model = TrackingPoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Journey';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TrackingPointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrackingPointsTable::configure($table);
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
            'index' => ListTrackingPoints::route('/'),
            'create' => CreateTrackingPoint::route('/create'),
            'edit' => EditTrackingPoint::route('/{record}/edit'),
        ];
    }
}
