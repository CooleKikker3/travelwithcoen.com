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

if (! function_exists('stories_url')) {
    /**
     * The stories section on the Journey page, optionally filtered by article type and/or tag.
     */
    function stories_url(?string $type = null, ?string $tag = null, ?string $locale = null): string
    {
        return lroute('stories', array_filter(['type' => $type, 'tag' => $tag]), $locale);
    }
}
