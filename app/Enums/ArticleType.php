<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ArticleType: string implements HasLabel
{
    case Diary = 'diary';
    case Preparation = 'preparation';

    public function getLabel(): string
    {
        return __("articles.type.{$this->value}");
    }

    /** Name prefix of the public routes that list/show this type. */
    public function routePrefix(): string
    {
        return $this->value;
    }
}
