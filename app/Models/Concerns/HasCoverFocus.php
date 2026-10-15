<?php

namespace App\Models\Concerns;

/**
 * The spot of the cover photo that stays in view when the photo is cropped (CSS object-position), chosen by clicking
 * on the photo in the CMS (App\Filament\Support\CoverFocus). A new cover starts in the middle again.
 */
trait HasCoverFocus
{
    public static function bootHasCoverFocus(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('cover_image') && ! $model->isDirty('cover_focus')) {
                $model->cover_focus = null;
            }
        });
    }

    /** "x% y%" for object-position; the middle when nothing (valid) was chosen. */
    public function coverPosition(): string
    {
        return preg_match('/^\d{1,3}% \d{1,3}%$/', (string) $this->cover_focus) ? $this->cover_focus : '50% 50%';
    }
}
