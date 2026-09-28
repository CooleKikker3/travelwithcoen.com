<?php

namespace App\Models;

use App\Support\Polyline;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One part of a route piece: an imported GPX file or a drawn piece (route planner). */
#[Fillable(['country_route_id', 'sequence', 'kind', 'label', 'routing', 'waypoints', 'line'])]
class RouteSegment extends Model
{
    protected function casts(): array
    {
        return ['waypoints' => 'array'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(CountryRoute::class, 'country_route_id');
    }

    /** @return array<int, array{0: float, 1: float}> [lat, lng] */
    public function points(): array
    {
        return Polyline::decode($this->line);
    }
}
