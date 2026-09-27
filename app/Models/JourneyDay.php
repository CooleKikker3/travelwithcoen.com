<?php

namespace App\Models;

use App\Enums\DayType;
use App\Enums\Overnight;
use App\Support\TrackingPrivacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['date', 'country_id', 'type', 'start_location', 'end_location', 'distance_km', 'walking_minutes', 'overnight', 'notes'])]
class JourneyDay extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => DayType::class,
            'overnight' => Overnight::class,
            'distance_km' => 'float',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /** Days reveal locations, so guests only see days older than the tracking delay. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $cutoff = TrackingPrivacy::cutoff($user);

        return $cutoff ? $query->whereDate('date', '<', $cutoff->toDateString()) : $query;
    }

    /** Day number counted from the first recorded day. */
    public function number(): int
    {
        return static::whereDate('date', '<=', $this->date)->count();
    }
}
