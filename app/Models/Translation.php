<?php

namespace App\Models;

use App\Services\AutoTranslation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A Dutch text with its automatic English translation, waiting to be checked (see AutoTranslation). */
#[Fillable(['translatable_type', 'translatable_id', 'field', 'source', 'suggestion', 'status', 'error', 'attempts'])]
class Translation extends Model
{
    private const TYPES = [
        Article::class => 'Verhaal',
        EquipmentItem::class => 'Uitrusting',
        Country::class => 'Land',
        CountryRoute::class => 'Routestuk',
        JourneyEvent::class => 'Gebeurtenis',
        GalleryItem::class => 'Galerij',
        AutoTranslation::SITE_TEXT => 'Websitetekst',
    ];

    private const FIELDS = [
        'title' => 'Titel', 'name' => 'Naam', 'excerpt' => 'Samenvatting', 'body' => 'Tekst', 'intro' => 'Intro',
        'story' => 'Verhaal', 'description' => 'Beschrijving', 'caption' => 'Bijschrift',
    ];

    /** The translated model (also concepts and hidden items), or null for a website text. */
    public function record(): ?Model
    {
        return $this->translatable_type === AutoTranslation::SITE_TEXT
            ? null
            : $this->translatable_type::withoutGlobalScopes()->find($this->translatable_id);
    }

    public function isHtml(): bool
    {
        return in_array($this->field, AutoTranslation::HTML_FIELDS, true);
    }

    /** e.g. "Verhaal · Over de dijken naar de Duitse grens" */
    public function itemLabel(): string
    {
        $type = self::TYPES[$this->translatable_type] ?? class_basename($this->translatable_type);
        if ($this->translatable_type === AutoTranslation::SITE_TEXT) {
            return "{$type} · {$this->field}";
        }
        $record = $this->record();
        $name = $record?->translate('title', 'nl') ?? $record?->translate('name', 'nl') ?? Str::limit((string) $record?->translate('caption', 'nl'), 50) ?: "#{$this->translatable_id}";

        return "{$type} · {$name}";
    }

    public function fieldLabel(): string
    {
        return self::FIELDS[$this->field] ?? 'Tekst';
    }
}
