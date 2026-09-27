<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum EventType: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'event_type';

    case BorderCrossing = 'border_crossing';
    case Milestone = 'milestone';
    case RestDay = 'rest_day';
    case Meeting = 'meeting';
    case SpecialWalk = 'special_walk';
    case Other = 'other';
}
