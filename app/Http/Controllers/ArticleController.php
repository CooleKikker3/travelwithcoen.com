<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /** The old diary/preparation lists now live on the Journey page; keep old links working. */
    public function index(Request $request, string $type): RedirectResponse
    {
        return redirect(stories_url($type, $request->string('tag')->trim()->value() ?: null), 301);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $type = $request->route()->defaults['type'];
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $article = Article::published()
            ->where('type', $type)
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, $fallback)))
            ->with(['country', 'gallery'])
            ->firstOrFail();

        // Always serve an article on its own slug for this locale.
        if ($article->translate('slug', $locale) !== $slug) {
            return redirect($article->url(), 301);
        }

        return view('articles.show', [
            'article' => $article,
            'isTranslated' => $article->isTranslated($locale),
            'alternates' => $this->alternates($article),
        ]);
    }

    /** Locale => URL, only for locales the article is actually written in. */
    private function alternates(Article $article): array
    {
        return collect(array_keys(config('travel.locales')))
            ->filter(fn (string $locale) => $article->isTranslated($locale))
            ->mapWithKeys(fn (string $locale) => [$locale => $article->url($locale)])
            ->all();
    }
}
