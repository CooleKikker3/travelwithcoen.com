<?php

if (! function_exists('lroute')) {
    /**
     * Generate a URL for a named public route in the given (or current) locale.
     * The default locale has no prefix; other locales use a "{locale}." route name prefix.
     */
    function lroute(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === config('app.fallback_locale')
            ? route($name, $parameters)
            : route("{$locale}.{$name}", $parameters);
    }
}
