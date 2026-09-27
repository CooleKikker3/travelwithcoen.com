<?php

namespace App\Enums\Concerns;

/** Label from lang/{locale}/site.php (editable under "Website texts"); enums define LANG_KEY. */
trait TranslatedLabel
{
    public function getLabel(): string
    {
        return __('site.'.self::LANG_KEY.'.'.$this->value);
    }
}
