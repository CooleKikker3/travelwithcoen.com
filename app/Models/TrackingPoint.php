<?php

namespace App\Models;

use App\Support\TrackingPrivacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['latitude', 'longitude', 'altitude', 'recorded_at', 'received_at', 'source', 'country_id'])]
class TrackingPoint extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'altitude' => 'float',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** Only points the given user may see (null user = guest). */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->recordedBefore(TrackingPrivacy::cutoff($user));
    }

    public function scopeRecordedBefore(Builder $query, ?Carbon $cutoff): Builder
    {
        return $cutoff ? $query->where('recorded_at', '<=', $cutoff) : $query;
    }

    /** When this point becomes visible to the public. */
    public function publicFrom(): Carbon
    {
        return $this->recorded_at->copy()->addHours(TrackingPrivacy::delayHours());
    }
}
