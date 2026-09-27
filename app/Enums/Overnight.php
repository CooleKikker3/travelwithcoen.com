<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum Overnight: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'overnight';

    case WildCamping = 'wild_camping';
    case Campsite = 'campsite';
    case Hotel = 'hotel';
    case Hostel = 'hostel';
    case Host = 'host';
    case Other = 'other';

    public function isTent(): bool
    {
        return in_array($this, [self::WildCamping, self::Campsite], true);
    }
}
