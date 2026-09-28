<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Naam')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')->label('E-mail')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('role')->label('Rol')
                    ->options(Role::class)
                    ->default(Role::TrustedViewer)
                    ->required(),
                TextInput::make('password')->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('Laat leeg om het huidige wachtwoord te houden.'),
            ]);
    }
}
