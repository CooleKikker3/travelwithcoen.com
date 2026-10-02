<?php

namespace App\Filament\Pages;

use App\Enums\ArticleType;
use App\Enums\RouteType;
use App\Models\Article;
use App\Models\CountryRoute;
use App\Models\GalleryItem;
use App\Support\MediaStorage;
use App\Models\JourneyDay;
use Filament\Pages\Page;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;

/**
 * Instagram story for an article, in the site's style (resources/js/insta-story.js draws it on a canvas in the
 * browser, in one of five designs with parts that can be switched on and off). Instagram has no links in images:
 * the story gets a marked spot for the link sticker, and the page copies a tracking link (/s/{article}/{locale},
 * counted in StoryClicks) to paste into that sticker. Opened from the article list / edit page.
 */
class InstaStory extends Page
{
    protected string $view = 'filament.pages.insta-story';

    protected static ?string $title = 'Insta-story';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?int $article = null;

    /** Texts, numbers and links per language, for the story generator. */
    public function storyData(): array
    {
        $article = Article::with(['country', 'journeyDay'])->findOrFail($this->article);
        $day = $article->journeyDay;

        // Kilometres walked up to the story's day (never further than the story itself tells).
        $until = $day?->date ?? ($article->type === ArticleType::Diary ? $article->published_at : null);
        $walked = $until ? (float) JourneyDay::whereDate('date', '<=', $until)->sum('distance_km') : 0.0;
        $planned = (float) CountryRoute::where('type', RouteType::Planned)->sum('distance_km');

        return [
            'cover' => $article->coverUrl(),
            // More photos for the collage: the photos in the story's text and linked uploads (no sensitive ones).
            'photos' => GalleryItem::where('article_id', $article->id)->where('kind', 'image')->where('is_sensitive', false)
                ->whereNotNull('path')->oldest('taken_at')->limit(6)->pluck('path')
                ->map(fn (string $path) => MediaStorage::url($path))->reject(fn (string $url) => $url === $article->coverUrl())->values()->all(),
            'flag' => $article->country ? strtolower($article->country->iso_code) : null,
            'progress' => $walked > 0 && $planned > 0 ? min(1, $walked / $planned) : null,
            // Only the languages the article is written in.
            'locales' => collect(['nl', 'en'])->filter(fn (string $locale) => $article->isTranslated($locale))->mapWithKeys(fn (string $locale) => [$locale => [
                'title' => $article->translate('title', $locale),
                'excerpt' => $article->translate('excerpt', $locale),
                // Through the tracking link, so the clicks show up under Statistieken.
                'url' => route('story.link', [$article->id, $locale]),
                'day' => $day ? __('site.day_name', ['number' => $day->number()], $locale) : null,
                'day_number' => $day ? (string) $day->number() : null,
                'country' => $article->country?->translate('name', $locale),
                'date' => ($day?->date ?? $article->published_at)?->locale($locale)->translatedFormat('j F Y'),
                'km' => $walked > 0 ? __('site.story.walked', ['km' => Number::format($walked, maxPrecision: 0, locale: $locale)], $locale) : null,
                'km_number' => $walked > 0 ? Number::format($walked, maxPrecision: 0, locale: $locale) : null,
                'km_label' => __('site.story.km_label', [], $locale),
                'progress' => $walked > 0 && $planned > 0 ? __('site.story.progress', ['percent' => Number::format(min(100, $walked / $planned * 100), maxPrecision: 0, locale: $locale)], $locale) : null,
                'greeting' => $article->country ? __('site.story.greetings', ['country' => $article->country->translate('name', $locale)], $locale) : null,
                'labels' => [
                    'from' => __('site.story.from', [], $locale),
                    'to' => __('site.story.to', [], $locale),
                    'day' => __('site.story.label_day', [], $locale),
                    'country' => __('site.story.label_country', [], $locale),
                    'date' => __('site.story.label_date', [], $locale),
                    'walked' => __('site.story.label_walked', [], $locale),
                    'home' => __('site.story.home', [], $locale),
                ],
                'note' => __('site.story.note', [], $locale),
                'sticker' => __('site.story.sticker', [], $locale),
                'site' => parse_url(config('app.url'), PHP_URL_HOST),
            ]])->all(),
        ];
    }
}
