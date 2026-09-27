<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum EquipmentStatus: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'gear_status';

    case Planned = 'planned';
    case Testing = 'testing';
    case Carried = 'carried';
    case Replaced = 'replaced';
    case Retired = 'retired';

    /** Counts towards the pack weight. */
    public function isInPack(): bool
    {
        return in_array($this, [self::Testing, self::Carried], true);
    }
}
