<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Country;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $locales = array_keys(config('travel.locales'));
        $urls = [];

        foreach (['home', 'journey', 'live', 'statistics', 'equipment', 'gallery', 'about'] as $name) {
            foreach ($locales as $locale) {
                $urls[] = ['loc' => lroute($name, [], $locale), 'lastmod' => null];
            }
        }

        foreach (Country::published()->get() as $country) {
            foreach ($locales as $locale) {
                $urls[] = ['loc' => $country->url($locale), 'lastmod' => $country->updated_at];
            }
        }

        foreach (Article::published()->get() as $article) {
            foreach ($locales as $locale) {
                if ($article->isTranslated($locale)) {
                    $urls[] = ['loc' => $article->url($locale), 'lastmod' => $article->updated_at];
                }
            }
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
