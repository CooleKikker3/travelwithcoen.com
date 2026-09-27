<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CountryStatus: string implements HasColor, HasLabel
{
    case Tentative = 'tentative';
    case Current = 'current';
    case Visited = 'visited';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __("countries.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tentative => 'gray',
            self::Current => 'warning',
            self::Visited => 'success',
            self::Skipped => 'danger',
        };
    }
}
