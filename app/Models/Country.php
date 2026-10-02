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

    /** Written in Dutch first; English is optional (falls back to Dutch). */
    protected string $mainLocale = 'nl';

    /** Translated automatically to English, checked on the "Vertalingen" page. */
    protected array $autoTranslate = ['name', 'intro', 'story'];

    protected string $slugSource = 'name';

    protected function casts(): array
    {
        return [
            'status' => CountryStatus::class,
            'is_published' => 'boolean',
        ];
    }

    /** The country Coen is in now (set from the newest GPS point), if any. */
    public static function current(): ?self
    {
        return static::where('status', CountryStatus::Current)->first();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    protected static function booted(): void
    {
        // A new country (or a changed country code) changes which points lie where: re-assign after the response.
        static::saved(function (Country $country) {
            if ($country->wasRecentlyCreated || $country->wasChanged('iso_code')) {
                dispatch(fn () => \Illuminate\Support\Facades\Artisan::call('geo:countries'))->afterResponse();
            }
        });
    }

    /** Route pieces made for this country (route planner, admin tabs). */
    public function routes(): HasMany
    {
        return $this->hasMany(CountryRoute::class)->orderBy('sort_order');
    }

    /** Every route piece with a part in this country (also pieces made for a neighbour that cross the border). */
    /** The status for visitors; a country not walked yet says how far its route is planned. */
    public function statusLabel(): string
    {
        if ($this->status !== CountryStatus::Tentative) {
            return $this->status->getLabel();
        }
        $planned = RoutePoint::where('country_id', $this->id)->whereHas('route', fn ($query) => $query->where('type', \App\Enums\RouteType::Planned))->exists();

        return match (true) {
            ! $planned => __('countries.planning.none'),
            \App\Support\RouteGeometry::lastPlannedPoint()?->country_id === $this->id => __('countries.planning.partly'),
            default => __('countries.planning.full'),
        };
    }

    public function routesThrough(): \Illuminate\Support\Collection
    {
        return CountryRoute::whereHas('points', fn ($q) => $q->where('country_id', $this->id))
            ->orWhere(fn ($q) => $q->where('country_id', $this->id)->whereNull('country_km'))
            ->orderBy('sort_order')->get();
    }
    public function days(): HasMany
    {
        return $this->hasMany(JourneyDay::class);
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
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
