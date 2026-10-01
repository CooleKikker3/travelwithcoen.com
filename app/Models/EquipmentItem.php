<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Filament\Blocks\GearImageBlock;
use App\Models\Concerns\HasTranslations;
use App\Services\ArticleGallerySync;
use App\Services\ImageProcessor;
use App\Support\MediaStorage;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A piece of gear, with its own page like an article: cover photo, short and long description (photos between
 * the text via GearImageBlock) and free specifications ([{label, value}], e.g. "Gewicht: 1150 g").
 */
#[Fillable(['category', 'name', 'slug', 'brand', 'model', 'excerpt', 'cover_image', 'specs', 'body', 'is_public', 'sort_order'])]
class EquipmentItem extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'slug', 'excerpt', 'body'];

    /** Written in Dutch first; English is optional (falls back to Dutch). */
    protected string $mainLocale = 'nl';

    protected string $slugSource = 'name';

    protected function casts(): array
    {
        return [
            'category' => EquipmentCategory::class,
            'specs' => 'array',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The cover and photos new in the long description are re-encoded (resized, EXIF/GPS removed), like gallery photos.
        static::saving(function (EquipmentItem $item) {
            if ($item->isDirty('cover_image') && $item->cover_image) {
                app(ImageProcessor::class)->process($item->cover_image);
            }
        });
        static::saved(function (EquipmentItem $item) {
            $paths = fn (?array $body) => collect($body ?? [])->filter(fn ($html) => is_string($html))
                ->flatMap(fn (string $html) => app(ArticleGallerySync::class)->imageBlocks($html))
                ->map(fn (array $config) => GearImageBlock::path($config))->filter()->unique();
            $old = $item->getOriginal('body');
            $paths($item->body)->diff($paths(is_array($old) ? $old : json_decode((string) $old, true)))
                ->each(fn (string $path) => app(ImageProcessor::class)->process($path));
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->orderBy('sort_order');
    }

    public function url(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return lroute('equipment.show', $this->translate('slug', $locale), $locale);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? MediaStorage::url($this->cover_image) : null;
    }

    /** The long description with its photos as HTML (from the admin-only editor). */
    public function bodyHtml(?string $locale = null): string
    {
        $body = $this->translate('body', $locale);

        return blank($body) ? '' : RichContentRenderer::make($body)->customBlocks([GearImageBlock::class])->toUnsafeHtml();
    }

    /** @return list<array{label: string, value: string}> filled specifications */
    public function specList(): array
    {
        return collect($this->specs ?? [])->filter(fn ($spec) => filled($spec['label'] ?? null) && filled($spec['value'] ?? null))->values()->all();
    }
}
