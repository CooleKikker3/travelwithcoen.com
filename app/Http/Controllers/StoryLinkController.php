<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StoryClick;
use App\Support\Bots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /s/{article}/{locale} — the link in an Instagram story's link sticker: counts the click (no personal data)
 * and sends the visitor on to the article. Link previews and bots are not counted.
 */
class StoryLinkController extends Controller
{
    public function __invoke(Request $request, Article $article, string $locale): RedirectResponse
    {
        if (! Bots::is($request)) {
            StoryClick::create(['article_id' => $article->id, 'locale' => $locale, 'clicked_at' => now()]);
        }

        return redirect()->away($article->url($locale));
    }
}
