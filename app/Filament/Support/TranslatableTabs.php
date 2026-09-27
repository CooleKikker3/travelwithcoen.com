<?php

namespace App\Filament\Support;

use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * One tab per locale for translatable (JSON) fields. The closure receives the locale
 * and whether it is the default locale, and returns fields named "{attribute}.{locale}".
 */
class TranslatableTabs
{
    public static function make(Closure $fields): Tabs
    {
        $default = config('app.fallback_locale');

        return Tabs::make('Translations')
            ->tabs(collect(config('travel.locales'))
                ->map(fn (string $label, string $locale) => Tab::make($label)
                    ->schema($fields($locale, $locale === $default)))
                ->values()
                ->all())
            ->columnSpanFull();
    }
}
