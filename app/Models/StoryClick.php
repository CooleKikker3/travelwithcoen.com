<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One click on the tracking link of an Instagram story (see StoryLinkController). */
#[Fillable(['article_id', 'locale', 'clicked_at'])]
class StoryClick extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
