<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Blocks\ImageBlock;
use App\Models\Concerns\HasTranslations;
use App\Services\ArticleGallerySync;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['type', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'country_id', 'journey_day_id', 'cover_image', 'tags'])]
class Article extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'slug', 'excerpt', 'body'];

    protected string $slugSource = 'title';

    protected function casts(): array
    {
        return [
            'type' => ArticleType::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Images in the text also appear in the gallery.
        static::saved(fn (Article $article) => app(ArticleGallerySync::class)->sync($article));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function journeyDay(): BelongsTo
    {
        return $this->belongsTo(JourneyDay::class);
    }

    /** Gallery items linked to this article: uploads and YouTube videos (images in the text are shown there already). */
    public function gallery(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->where('is_public', true)->where('source', '!=', 'article')->oldest('taken_at');
    }

    /** Published and not scheduled for the future, newest first. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published)
            ->where('published_at', '<=', now())
            ->latest('published_at');
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return lroute("{$this->type->routePrefix()}.show", $this->translate('slug', $locale), $locale);
    }

    /** Body HTML with image blocks rendered. HTML comes from the admin-only editor. */
    public function bodyHtml(?string $locale = null): string
    {
        return RichContentRenderer::make($this->translate('body', $locale) ?? '')
            ->customBlocks([ImageBlock::class])
            ->toUnsafeHtml();
    }

    /** Tags as a flat list. */
    public static function allTags(): array
    {
        return static::whereNotNull('tags')->pluck('tags')->flatten()->filter()->unique()->sort()->values()->all();
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
