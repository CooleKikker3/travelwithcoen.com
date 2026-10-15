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

if (! function_exists('page_title')) {
    /**
     * The browser title, the same everywhere (site, admin, coming-soon page): "Travel with Coen | <page>".
     * The pattern is the website text site.title_format (editable in the CMS); without a page the tagline is used.
     */
    function page_title(?string $page = null, ?string $locale = null): string
    {
        return __('site.title_format', [
            'site' => __('site.name', [], $locale),
            'page' => filled($page) ? trim(strip_tags($page)) : __('site.tagline', [], $locale),
        ], $locale);
    }
}
