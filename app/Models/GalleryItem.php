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
 * One item in the gallery, all mixed together:
 *  - kind "image" / "video": uploaded files (source "upload"), or images from an article's text (source "article")
 *  - kind "youtube": synced from the YouTube channel (source "youtube", see YouTubeSync)
 */
#[Fillable(['path', 'youtube_id', 'kind', 'source', 'caption', 'taken_at', 'country_id', 'article_id', 'journey_day_id', 'journey_event_id', 'is_public'])]
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
                $takenAt = $item->kind === 'video'
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

    /** Public items, newest first; images from articles only once that article is published. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)
            ->where(fn ($q) => $q->where('source', '!=', 'article')->orWhereHas('article', fn ($a) => $a
                ->where('status', ArticleStatus::Published)
                ->where('published_at', '<=', now())))
            ->latest('taken_at');
    }

    /** Photos, or videos (uploaded and YouTube). */
    public function scopeOfKind(Builder $query, ?string $kind): Builder
    {
        return match ($kind) {
            'image' => $query->where('kind', 'image'),
            'video' => $query->whereIn('kind', ['video', 'youtube']),
            default => $query,
        };
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function isYoutube(): bool
    {
        return $this->kind === 'youtube';
    }

    public function url(): string
    {
        return $this->isYoutube()
            ? "https://www.youtube.com/watch?v={$this->youtube_id}"
            : Storage::disk('public')->url($this->path);
    }

    /** Image to show in a grid: the photo itself or the YouTube thumbnail. */
    public function thumbnailUrl(): ?string
    {
        return match ($this->kind) {
            'youtube' => "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg",
            'image' => $this->url(),
            default => null,
        };
    }

    protected function fillMissingSlugs(): void {}
}
