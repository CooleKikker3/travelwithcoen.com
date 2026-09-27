<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum EquipmentCategory: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'gear_category';

    case Shelter = 'shelter';
    case Sleep = 'sleep';
    case Pack = 'pack';
    case Clothing = 'clothing';
    case Footwear = 'footwear';
    case Cooking = 'cooking';
    case Water = 'water';
    case Electronics = 'electronics';
    case Navigation = 'navigation';
    case Safety = 'safety';
    case Hygiene = 'hygiene';
    case Other = 'other';
}
