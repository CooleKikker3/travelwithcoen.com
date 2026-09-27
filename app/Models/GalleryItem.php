<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Models\Concerns\HasTranslations;
use App\Services\ImageProcessor;
use App\Services\VideoProcessor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo or (non-YouTube) video in the gallery. Items with source "article" are
 * created automatically from the images in an article (see ArticleGallerySync).
 */
#[Fillable(['path', 'kind', 'source', 'caption', 'taken_at', 'country_id', 'article_id', 'journey_day_id', 'journey_event_id', 'is_public'])]
class GalleryItem extends Model
{
    use HasTranslations;

    public const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mov', 'webm'];

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
        // Every new file: images are resized and stripped of EXIF (incl. GPS); videos of metadata where possible.
        static::saving(function (GalleryItem $item) {
            if ($item->isDirty('path') && $item->path) {
                $item->kind = in_array(strtolower(pathinfo($item->path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true) ? 'video' : 'image';
                $takenAt = $item->isVideo()
                    ? app(VideoProcessor::class)->stripMetadata($item->path)
                    : app(ImageProcessor::class)->process($item->path);
                $item->taken_at ??= $takenAt ?? now();
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

    /** Public items; images from articles only once that article is published. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)
            ->where(fn ($q) => $q->whereNull('article_id')->orWhereHas('article', fn ($a) => $a
                ->where('status', ArticleStatus::Published)
                ->where('published_at', '<=', now())))
            ->latest('taken_at');
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected function fillMissingSlugs(): void {}
}
