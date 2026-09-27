<?php

namespace App\Models;

use App\Enums\EventType;
use App\Models\Concerns\HasTranslations;
use App\Support\TrackingPrivacy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['occurred_at', 'type', 'title', 'description', 'country_id', 'latitude', 'longitude', 'is_public'])]
class JourneyEvent extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'description'];

    protected string $slugSource = 'title';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'type' => EventType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'is_public' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** Admins see all; others see public events, guests only after the tracking delay. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        $cutoff = TrackingPrivacy::cutoff($user);

        return $query->where('is_public', true)->when($cutoff, fn ($q) => $q->where('occurred_at', '<=', $cutoff));
    }

    // Events have no slug column.
    protected function fillMissingSlugs(): void {}
}
