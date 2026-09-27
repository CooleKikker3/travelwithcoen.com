<?php

namespace App\Support;

use App\Models\SiteText;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Fixed website texts: defaults live in lang/{locale}/{group}.php, the CMS stores overrides
 * in site_texts. Only the groups below are editable (URL segments in routes.php are not).
 */
class SiteTexts
{
    public const GROUPS = ['site', 'articles', 'countries'];

    private const CACHE_KEY = 'site_texts';

    /** @return array<string, string> dotted key (without group) => default text */
    public static function defaults(string $group, string $locale): array
    {
        $file = lang_path("{$locale}/{$group}.php");

        return is_file($file) ? Arr::dot(require $file) : [];
    }

    /** @return array<string, array<string, string>> "group.key" => [locale => text] */
    public static function overrides(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => SiteText::pluck('value', 'key')->all());
        } catch (QueryException) {
            return []; // Table not migrated yet.
        }
    }

    /**
     * Store only the texts that differ from the defaults; clearing a field restores the default.
     *
     * @param  array<string, array<string, ?string>>  $texts  "group.key" => [locale => text]
     */
    public static function save(array $texts): void
    {
        foreach ($texts as $key => $values) {
            [$group, $path] = explode('.', $key, 2);

            $changed = collect($values)
                ->filter(fn (?string $text, string $locale) => filled($text) && $text !== (self::defaults($group, $locale)[$path] ?? null))
                ->all();

            $changed
                ? SiteText::updateOrCreate(['key' => $key], ['value' => $changed])
                : SiteText::where('key', $key)->delete();
        }

        Cache::forget(self::CACHE_KEY);
    }
}
