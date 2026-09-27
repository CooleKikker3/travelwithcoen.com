<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['youtube_id', 'title', 'description', 'published_on', 'country_id', 'article_id', 'is_public'])]
class Video extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'description'];

    protected string $slugSource = 'title';

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'is_public' => 'boolean',
        ];
    }

    /** Accepts a full YouTube URL or a bare video id. */
    public static function extractYoutubeId(string $input): ?string
    {
        if (preg_match('~^[\w-]{11}$~', $input)) {
            return $input;
        }

        return preg_match('~(?:youtu\.be/|v=|embed/|shorts/|live/)([\w-]{11})~', $input, $m) ? $m[1] : null;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->latest('published_on');
    }

    public function embedUrl(): string
    {
        return "https://www.youtube-nocookie.com/embed/{$this->youtube_id}";
    }

    public function thumbnailUrl(): string
    {
        return "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg";
    }

    protected function fillMissingSlugs(): void {}
}
