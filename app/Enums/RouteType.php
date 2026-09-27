<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Planned and actual routes are separate datasets and are never merged. */
enum RouteType: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case Actual = 'actual';

    public function getLabel(): string
    {
        return __("site.map.{$this->value}");
    }

    public function getColor(): string
    {
        return $this === self::Actual ? 'success' : 'gray';
    }
}
