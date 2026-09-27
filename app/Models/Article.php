<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\PreparationTopic;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['type', 'topic', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'country_id', 'cover_image', 'tags'])]
class Article extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'slug', 'excerpt', 'body'];

    protected string $slugSource = 'title';

    protected function casts(): array
    {
        return [
            'type' => ArticleType::class,
            'topic' => PreparationTopic::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
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

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
