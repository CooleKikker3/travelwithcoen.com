<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** Drives the home page focus: Road to Hanoi → journey progress → complete archive. */
enum JourneyPhase: string implements HasLabel
{
    use TranslatedLabel;

    private const LANG_KEY = 'phase';

    case Preparation = 'preparation';
    case Journey = 'journey';
    case Archive = 'archive';
}
