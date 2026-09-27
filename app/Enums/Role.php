<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case TrustedViewer = 'trusted_viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::TrustedViewer => 'Trusted viewer (family)',
        };
    }
}
