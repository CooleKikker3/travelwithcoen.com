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

#[Fillable(['date', 'country_id', 'type', 'started_at', 'ended_at', 'start_point_id', 'end_point_id', 'start_location', 'end_location', 'distance_km', 'walking_minutes', 'overnight', 'notes'])]
class JourneyDay extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => DayType::class,
            'overnight' => Overnight::class,
            'distance_km' => 'float',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    /**
     * Day number: position in the list of all days (walk, rest and transport days alike), oldest first.
     * Computed, never stored; lists load it in one query with withNumber().
     */
    public function number(): int
    {
        return (int) ($this->attributes['day_number'] ?? static::whereDate('date', '<=', $this->date)->count());
    }

    /** "Day 3" / "Dag 3". */
    public function name(): string
    {
        return __('site.day_name', ['number' => $this->number()]);
    }

    /** Adds the day number to each row with a subquery, so a list needs no query per day. */
    public function scopeWithNumber(Builder $query): Builder
    {
        return $query->addSelect(['day_number' => static::from('journey_days as earlier')
            ->selectRaw('count(*)')
            ->whereColumn('earlier.date', '<=', 'journey_days.date')]);
    }
}
