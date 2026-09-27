<?php

namespace App\Models;

use App\Enums\RouteType;
use App\Services\GpxImporter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A planned or actual route (segment) through one country, stored as ordered points.
 * Country pages and the global overview both read from here: one source of truth.
 */
#[Fillable(['country_id', 'type', 'name', 'sort_order', 'gpx_path', 'notes'])]
class CountryRoute extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RouteType::class,
            'distance_km' => 'float',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }
}
