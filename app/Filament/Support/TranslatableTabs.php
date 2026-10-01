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
    /** $main: the language that comes first and is required (default: the site's default locale). */
    public static function make(Closure $fields, ?string $main = null): Tabs
    {
        $default = $main ?? config('app.fallback_locale');

        return Tabs::make('Translations')
            ->tabs(collect(config('travel.locales'))
                ->sortBy(fn (string $label, string $locale) => $locale === $default ? 0 : 1)
                ->map(fn (string $label, string $locale) => Tab::make($label)
                    ->schema($fields($locale, $locale === $default)))
                ->values()
                ->all())
            ->columnSpanFull();
    }
}
