<?php

namespace App\Filament\Pages;

use App\Models\Article;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Instagram story for an article, in the site's style (resources/js/insta-story.js draws it on a canvas in the
 * browser). Instagram has no links in images: the story gets a marked spot for the link sticker, and the page
 * copies a tracking link (/s/{article}/{locale}, counted in StoryClicks) to paste into that sticker. Opened from the article list / edit page.
 */
class InstaStory extends Page
{
    protected string $view = 'filament.pages.insta-story';

    protected static ?string $title = 'Insta-story';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?int $article = null;

    /** Texts and links per language, for the story generator. */
    public function storyData(): array
    {
        $article = Article::with(['country', 'journeyDay'])->findOrFail($this->article);

        return [
            'cover' => $article->coverUrl(),
            // Only the languages the article is written in.
            'locales' => collect(['nl', 'en'])->filter(fn (string $locale) => $article->isTranslated($locale))->mapWithKeys(fn (string $locale) => [$locale => [
                'title' => $article->translate('title', $locale),
                // Through the tracking link, so the clicks show up under Statistieken.
                'url' => route('story.link', [$article->id, $locale]),
                'caption' => collect([
                    $article->journeyDay ? __('site.day_name', ['number' => $article->journeyDay->number()], $locale) : null,
                    $article->country?->translate('name', $locale),
                ])->filter()->join(' · ') ?: $article->published_at?->locale($locale)->translatedFormat('j F Y'),
                'note' => __('site.story.note', [], $locale),
                'sticker' => __('site.story.sticker', [], $locale),
                'site' => parse_url(config('app.url'), PHP_URL_HOST),
            ]])->all(),
        ];
    }
}
