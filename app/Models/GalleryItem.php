<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Models\Concerns\HasTranslations;
use App\Services\ImageProcessor;
use App\Services\VideoProcessor;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item in the gallery, all mixed together:
 *  - kind "image" / "video": uploaded files (source "upload"), or images from an article's text (source "article")
 *  - kind "youtube": synced from the YouTube channel (source "youtube", see YouTubeSync)
 */
#[Fillable(['path', 'width', 'height', 'youtube_id', 'youtube_channel_id', 'kind', 'source', 'caption', 'taken_at', 'country_id', 'article_id', 'journey_day_id', 'journey_event_id', 'is_public'])]
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
                if ($item->kind === 'video') {
                    app(VideoProcessor::class)->stripMetadata($item->path);
                } else {
                    $image = app(ImageProcessor::class)->process($item->path);
                    [$item->width, $item->height] = [$image['width'], $image['height']];
                    $item->taken_at ??= $image['taken_at'];
                }
                $item->taken_at ??= now();
            }
        });

        // Upload details as metadata on the file in R2.
        static::saved(function (GalleryItem $item) {
            if ($item->path && ($item->wasRecentlyCreated || $item->wasChanged('path'))) {
                MediaStorage::describe($item->path, $item->storageMetadata());
            }
        });
    }

    /** @return array<string, scalar|null> */
    public function storageMetadata(): array
    {
        return [
            'uploaded-at' => ($this->created_at ?? now())->toIso8601String(),
            'uploaded-by' => auth()->user()?->name,
            'gallery-item-id' => $this->id,
            'kind' => $this->kind,
            'source' => $this->source,
            'taken-at' => $this->taken_at?->toIso8601String(),
            'width' => $this->width,
            'height' => $this->height,
            'country' => $this->country?->iso_code,
            'article-id' => $this->article_id,
            'caption' => $this->translate('caption', 'en'),
        ];
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
            : MediaStorage::url($this->path);
    }

    /** Image to show in a grid: the photo itself or the YouTube thumbnail. */
    public function thumbnailUrl(): ?string
    {
        return match ($this->kind) {
            // mqdefault is 16:9 without the black bars of hqdefault.
            'youtube' => "https://i.ytimg.com/vi/{$this->youtube_id}/mqdefault.jpg",
            'image' => $this->url(),
            default => null,
        };
    }

    /** Width / height, so the wall can reserve space before loading. Videos default to 16:9. */
    public function ratio(): float
    {
        return round($this->width && $this->height ? $this->width / $this->height : ($this->kind === 'image' ? 4 / 3 : 16 / 9), 4);
    }

    protected function fillMissingSlugs(): void {}
}
