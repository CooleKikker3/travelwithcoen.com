<?php

namespace App\Filament\Support;

use App\Models\Article;
use App\Models\Country;
use App\Models\JourneyDay;

/** Select options shared by several CMS forms. */
class Options
{
    public static function countries(): array
    {
        return Country::orderBy('sort_order')->get()
            ->mapWithKeys(fn (Country $country) => [$country->id => $country->flag().' '.$country->translate('name', 'en')])
            ->all();
    }

    public static function articles(): array
    {
        return Article::latest('published_at')->get()
            ->mapWithKeys(fn (Article $article) => [$article->id => $article->translate('title', 'en')])
            ->all();
    }

    public static function days(): array
    {
        return JourneyDay::orderByDesc('date')->get()
            ->mapWithKeys(fn (JourneyDay $day) => [$day->id => $day->date->format('D j M Y').($day->end_location ? " — {$day->end_location}" : '')])
            ->all();
    }
}
