<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StoryClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /s/{article}/{locale} — the link in an Instagram story's link sticker: counts the click (no personal data)
 * and sends the visitor on to the article. Link previews and bots are not counted.
 */
class StoryLinkController extends Controller
{
    private const BOTS = '/bot|crawl|spider|preview|facebookexternalhit|meta-external|slurp|curl|wget|python|headless/i';

    public function __invoke(Request $request, Article $article, string $locale): RedirectResponse
    {
        if (! preg_match(self::BOTS, (string) $request->userAgent())) {
            StoryClick::create(['article_id' => $article->id, 'locale' => $locale, 'clicked_at' => now()]);
        }

        return redirect()->away($article->url($locale));
    }
}
