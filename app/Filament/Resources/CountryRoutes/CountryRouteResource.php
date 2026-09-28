<?php

namespace App\Filament\Resources\CountryRoutes;

use App\Filament\Resources\CountryRoutes\Pages\CreateCountryRoute;
use App\Filament\Resources\CountryRoutes\Pages\EditCountryRoute;
use App\Filament\Resources\CountryRoutes\Pages\ListCountryRoutes;
use App\Filament\Resources\CountryRoutes\Schemas\CountryRouteForm;
use App\Filament\Resources\CountryRoutes\Tables\CountryRoutesTable;
use App\Models\CountryRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CountryRouteResource extends Resource
{
    protected static ?string $model = CountryRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Reis';

    protected static ?string $modelLabel = 'route';

    protected static ?string $pluralModelLabel = 'Routes';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CountryRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CountryRoutesTable::configure($table);
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
            'index' => ListCountryRoutes::route('/'),
            'create' => CreateCountryRoute::route('/create'),
            'edit' => EditCountryRoute::route('/{record}/edit'),
        ];
    }
}
