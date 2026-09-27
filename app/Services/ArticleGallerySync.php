<?php

namespace App\Services;

use App\Filament\Blocks\ImageBlock;
use App\Models\Article;
use App\Models\GalleryItem;

/**
 * Keeps the gallery in step with the images in an article: every image block becomes a
 * gallery item linked to the article (caption per language); removed images disappear.
 */
class ArticleGallerySync
{
    public function sync(Article $article): void
    {
        $images = [];
        $sensitive = [];

        foreach ($article->body ?? [] as $locale => $html) {
            foreach ($this->imageBlocks((string) $html) as $config) {
                if ($path = ImageBlock::path($config)) {
                    $images[$path][$locale] = $config['caption'] ?? null;
                    $sensitive[$path] = ($sensitive[$path] ?? false) || ! empty($config['sensitive']);
                }
            }
        }

        foreach ($images as $path => $captions) {
            GalleryItem::updateOrCreate(
                ['article_id' => $article->id, 'source' => 'article', 'path' => $path],
                ['caption' => array_filter($captions), 'is_sensitive' => $sensitive[$path] ?? false, 'country_id' => $article->country_id, 'journey_day_id' => $article->journey_day_id],
            );
        }

        GalleryItem::where('article_id', $article->id)
            ->where('source', 'article')
            ->whereNotIn('path', array_keys($images))
            ->delete();
    }

    /** @return array<int, array> configs of the image custom blocks in editor HTML */
    public function imageBlocks(string $html): array
    {
        preg_match_all('/<div\b[^>]*data-type="customBlock"[^>]*>/i', $html, $tags);

        return collect($tags[0])
            ->filter(fn (string $tag) => preg_match('/data-id="image"/', $tag))
            ->map(fn (string $tag) => preg_match("/data-config=(\"|')(.*?)\\1/s", $tag, $m)
                ? json_decode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5), true)
                : null)
            ->filter(fn ($config) => is_array($config))
            ->values()
            ->all();
    }
}
