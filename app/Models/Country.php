<?php

namespace App\Models;

use App\Enums\CountryStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['iso_code', 'name', 'slug', 'intro', 'story', 'status', 'sort_order', 'is_published'])]
class Country extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'slug', 'intro', 'story'];

    protected string $slugSource = 'name';

    protected function casts(): array
    {
        return [
            'status' => CountryStatus::class,
            'is_published' => 'boolean',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(CountryRoute::class)->orderBy('sort_order');
    }

    public function days(): HasMany
    {
        return $this->hasMany(JourneyDay::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order');
    }

    /** Flag emoji derived from the ISO 3166-1 alpha-2 code. */
    public function flag(): string
    {
        return implode('', array_map(
            fn ($char) => mb_chr(0x1F1E6 + ord($char) - ord('A')),
            str_split(strtoupper($this->iso_code))
        ));
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return lroute('countries.show', $this->translate('slug', $locale), $locale);
    }
}
