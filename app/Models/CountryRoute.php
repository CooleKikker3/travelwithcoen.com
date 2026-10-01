<?php

namespace App\Models;

use App\Enums\RouteType;
use App\Models\Concerns\HasTranslations;
use App\Services\GpxImporter;
use App\Support\CountryLocator;
use App\Support\RouteGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A planned or actual route (segment) through one country, stored as ordered points.
 * Country pages and the global overview both read from here: one source of truth.
 */
#[Fillable(['country_id', 'type', 'is_draft', 'name', 'title', 'description', 'sort_order', 'gpx_path', 'waypoints', 'routing', 'notes'])]
class CountryRoute extends Model
{
    use HasTranslations;

    /** Public title and story of this route piece (shown on the country page). */
    protected array $translatable = ['title', 'description'];

    /** Written in Dutch first; English is optional (falls back to Dutch). */
    protected string $mainLocale = 'nl';

    /** Translated automatically to English, checked on the "Vertalingen" page. */
    protected array $autoTranslate = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'type' => RouteType::class,
            'is_draft' => 'boolean',
            'distance_km' => 'float',
            'waypoints' => 'array',
            'country_km' => 'array',
        ];
    }

    /** Name of the global scope that hides concepts; the CMS removes it with withoutGlobalScope(). */
    public const PUBLISHED = 'published';

    protected static function booted(): void
    {
        // Concept pieces never reach the website; only the CMS sees them (withoutGlobalScope(self::PUBLISHED)).
        static::addGlobalScope(self::PUBLISHED, fn ($query) => $query->where('country_routes.is_draft', false));

        // A new or replaced GPX file replaces the route's points.
        static::saved(function (CountryRoute $route) {
            if ($route->gpx_path && ($route->wasRecentlyCreated || $route->wasChanged('gpx_path'))) {
                app(GpxImporter::class)->import($route);
            }
        });
    }

    /**
     * Replace all points and recalculate the distance.
     *
     * @param  array<int, array{lat: float, lng: float, ele?: ?float, time?: ?string}>  $points
     */
    public function replacePoints(array $points): void
    {
        DB::transaction(function () use ($points) {
            $this->points()->delete();

            // The country each point is really in: a piece crossing a border counts for both countries.
            $latLngs = array_map(fn ($p) => [$p['lat'], $p['lng']], array_values($points));
            $countries = app(CountryLocator::class)->locateAll($latLngs, $this->country_id);
            $points = array_values($points);

            foreach (array_chunk($points, 500, preserve_keys: true) as $chunk) {
                RoutePoint::insert(array_map(fn (array $p, int $i) => [
                    'country_route_id' => $this->id,
                    'country_id' => $countries[$i],
                    'sequence' => $i,
                    'latitude' => $p['lat'],
                    'longitude' => $p['lng'],
                    'elevation' => $p['ele'] ?? null,
                    'recorded_at' => $p['time'] ?? null,
                ], $chunk, array_keys($chunk)));
            }

            // Kilometres per country; a leg across a border counts for the country it ends in.
            $perCountry = [];
            for ($i = 1; $i < count($latLngs); $i++) {
                $key = (string) ($countries[$i] ?? 0);
                $perCountry[$key] = ($perCountry[$key] ?? 0) + RouteGeometry::distanceKm([$latLngs[$i - 1], $latLngs[$i]]);
            }

            $this->forceFill([
                'distance_km' => round(array_sum($perCountry), 2),
                'country_km' => array_map(fn ($km) => round($km, 2), $perCountry),
            ])->saveQuietly();
        });
    }

    /** Parts (GPX files and drawn pieces) in order; the route's points are built from these. */
    public function segments(): HasMany
    {
        return $this->hasMany(RouteSegment::class)->orderBy('sequence');
    }

    /**
     * Replace the parts and rebuild the route's points from them, in order. Where two parts already touch
     * (within ~20 m) the duplicate point is dropped.
     *
     * @param  array<int, array{kind: string, label?: ?string, routing?: ?string, waypoints?: ?array, line: string}>  $segments
     */
    public function replaceSegments(array $segments): void
    {
        DB::transaction(function () use ($segments) {
            $this->segments()->delete();
            $points = [];

            foreach (array_values($segments) as $i => $segment) {
                $part = $this->segments()->create(['sequence' => $i] + $segment);
                foreach ($part->points() as $j => [$lat, $lng]) {
                    if ($j === 0 && $points && RouteGeometry::distanceKm([[end($points)['lat'], end($points)['lng']], [$lat, $lng]]) < 0.02) {
                        continue;
                    }
                    $points[] = ['lat' => $lat, 'lng' => $lng];
                }
            }

            $this->replacePoints($points);
        });
    }

    /** Kilometres of this piece within a country (a piece can cross borders). */
    public function kmIn(int $countryId): float
    {
        return (float) ($this->country_km[(string) $countryId] ?? ($this->country_km === null && $this->country_id === $countryId ? $this->distance_km : 0));
    }

    /** Title for lists: the public title, else the internal name. */
    public function label(): string
    {
        return $this->translate('title') ?: ($this->name ?: '—');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }
}
