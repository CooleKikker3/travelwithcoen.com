<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Stores translatable fields as JSON objects keyed by locale ({"en": "...", "nl": "..."}).
 *
 * Models define:
 *  - $translatable: the translatable attributes
 *  - $slugSource:   the attribute a missing slug is generated from
 *  - $mainLocale:   optional, the language the content is written in first (default: the site's default locale)
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(array_fill_keys($this->translatable, 'array'));
    }

    public static function bootHasTranslations(): void
    {
        static::saving(fn ($model) => $model->fillMissingSlugs());
    }

    /**
     * The value in the given locale; when it is empty, the value in the main language, then in any other language.
     */
    public function translate(string $attribute, ?string $locale = null, bool $fallback = true): ?string
    {
        $values = $this->getAttribute($attribute) ?? [];
        $locales = $fallback ? [$locale ?? app()->getLocale(), ...$this->fallbackLocales()] : [$locale ?? app()->getLocale()];

        foreach ($locales as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return $values[$candidate];
            }
        }

        return null;
    }

    /** The language the content was written in: the main language if filled, otherwise the first filled one. */
    public function originalLocale(): string
    {
        return collect($this->fallbackLocales())->first(fn (string $locale) => $this->isTranslated($locale)) ?? $this->fallbackLocales()[0];
    }

    /** @return list<string> main language first, then the site default, then the rest */
    protected function fallbackLocales(): array
    {
        $main = property_exists($this, 'mainLocale') ? $this->mainLocale : config('app.fallback_locale');

        return array_values(array_unique([$main, config('app.fallback_locale'), ...array_keys(config('travel.locales'))]));
    }

    /**
     * Whether the model has its own (non-fallback) content in the given locale.
     */
    public function isTranslated(string $locale): bool
    {
        return filled($this->translate($this->slugSource, $locale, fallback: false));
    }

    public function scopeWhereSlug(Builder $query, string $slug, string $locale): Builder
    {
        return $query->where("slug->{$locale}", $slug);
    }

    protected function fillMissingSlugs(): void
    {
        // Models without their own page (e.g. route pieces) have no slug.
        if (! property_exists($this, 'slugSource')) {
            return;
        }

        $slugs = $this->slug ?? [];

        foreach (array_keys(config('travel.locales')) as $locale) {
            $source = $this->translate($this->slugSource, $locale);

            if (blank($slugs[$locale] ?? null) && filled($source)) {
                $slugs[$locale] = $this->uniqueSlug(Str::slug($source), $locale);
            }
        }

        $this->slug = $slugs;
    }

    protected function uniqueSlug(string $base, string $locale): string
    {
        $slug = $base;

        for ($i = 2; static::whereSlug($slug, $locale)->whereKeyNot($this->getKey())->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
