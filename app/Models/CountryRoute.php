<?php

namespace App\Models;

use App\Enums\RouteType;
use App\Services\GpxImporter;
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
#[Fillable(['country_id', 'type', 'name', 'sort_order', 'gpx_path', 'waypoints', 'routing', 'notes'])]
class CountryRoute extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RouteType::class,
            'distance_km' => 'float',
            'waypoints' => 'array',
        ];
    }

    protected static function booted(): void
    {
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

            foreach (array_chunk($points, 500, preserve_keys: true) as $chunk) {
                RoutePoint::insert(array_map(fn (array $p, int $i) => [
                    'country_route_id' => $this->id,
                    'sequence' => $i,
                    'latitude' => $p['lat'],
                    'longitude' => $p['lng'],
                    'elevation' => $p['ele'] ?? null,
                    'recorded_at' => $p['time'] ?? null,
                ], $chunk, array_keys($chunk)));
            }

            $this->forceFill([
                'distance_km' => round(RouteGeometry::distanceKm(array_map(fn ($p) => [$p['lat'], $p['lng']], $points)), 2),
            ])->saveQuietly();
        });
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
