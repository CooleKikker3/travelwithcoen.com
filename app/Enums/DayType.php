<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum DayType: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'day_type';

    case Walk = 'walk';
    case Rest = 'rest';
    case Transport = 'transport';
}
