<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Country;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    /** XML sitemap: every public page in every language, with its translations (hreflang) and last change. */
    public function __invoke(): Response
    {
        $locales = array_keys(config('travel.locales'));
        $latestStory = Article::published()->max('updated_at');
        $pages = [];

        foreach (['home', 'journey', 'live', 'equipment', 'gallery', 'about'] as $name) {
            $pages[] = [
                'urls' => collect($locales)->mapWithKeys(fn ($locale) => [$locale => lroute($name, [], $locale)])->all(),
                'lastmod' => in_array($name, ['home', 'journey']) && $latestStory ? Carbon::parse($latestStory) : null,
            ];
        }

        foreach (Country::published()->get() as $country) {
            $pages[] = [
                'urls' => collect($locales)->mapWithKeys(fn ($locale) => [$locale => $country->url($locale)])->all(),
                'lastmod' => $country->updated_at,
            ];
        }

        foreach (Article::published()->get() as $article) {
            $pages[] = [
                // Only languages the story is actually written in.
                'urls' => collect($locales)->filter(fn ($locale) => $article->isTranslated($locale))
                    ->mapWithKeys(fn ($locale) => [$locale => $article->url($locale)])->all(),
                'lastmod' => $article->updated_at,
            ];
        }

        return response()->view('sitemap', ['pages' => $pages, 'default' => $locales[0]])->header('Content-Type', 'application/xml');
    }
}
