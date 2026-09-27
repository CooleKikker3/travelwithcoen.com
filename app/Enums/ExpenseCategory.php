<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Admin-only, so labels are not part of the public website texts. */
enum ExpenseCategory: string implements HasLabel
{
    case Food = 'food';
    case Accommodation = 'accommodation';
    case Transport = 'transport';
    case Gear = 'gear';
    case Visas = 'visas';
    case Insurance = 'insurance';
    case Health = 'health';
    case Communication = 'communication';
    case Other = 'other';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
