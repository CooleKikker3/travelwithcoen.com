<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Enums\PreparationTopic;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(string $type): View
    {
        return view('articles.index', [
            'type' => ArticleType::from($type),
            'articles' => Article::published()->where('type', $type)->with('country')->paginate(12),
        ]);
    }

    public function preparation(): View
    {
        $articles = Article::published()->where('type', ArticleType::Preparation)->get();

        return view('articles.preparation', [
            'topics' => PreparationTopic::cases(),
            'byTopic' => $articles->groupBy(fn (Article $article) => $article->topic?->value ?? 'other'),
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $type = $request->route()->defaults['type'];
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $article = Article::published()
            ->where('type', $type)
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, $fallback)))
            ->with(['country', 'photos', 'videos'])
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
