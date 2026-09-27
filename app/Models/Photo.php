<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path', 'caption', 'taken_at', 'country_id', 'article_id', 'journey_day_id', 'journey_event_id', 'is_public'])]
class Photo extends Model
{
    use HasTranslations;

    protected array $translatable = ['caption'];

    protected string $slugSource = 'caption';

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Resize and strip EXIF (incl. GPS location) from every new upload.
        static::saving(function (Photo $photo) {
            if ($photo->isDirty('path') && $photo->path) {
                $takenAt = app(ImageProcessor::class)->process($photo->path);
                $photo->taken_at ??= $takenAt;
            }
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->latest('taken_at');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected function fillMissingSlugs(): void {}
}
