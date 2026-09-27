<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PreparationTopic: string implements HasLabel
{
    case RoutePlanning = 'route_planning';
    case Gear = 'gear';
    case Training = 'training';
    case Budget = 'budget';
    case Visas = 'visas';
    case Safety = 'safety';
    case Camping = 'camping';
    case Food = 'food';
    case Technology = 'technology';

    public function getLabel(): string
    {
        return __("articles.topic.{$this->value}");
    }
}
