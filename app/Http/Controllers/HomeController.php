<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\CountryRoute;
use App\Support\RouteGeometry;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $routes = CountryRoute::whereHas('country', fn ($query) => $query->where('is_published', true))->get();

        return view('pages.home', [
            'preparation' => Article::published()->where('type', ArticleType::Preparation)->limit(3)->get(),
            'diary' => Article::published()->where('type', ArticleType::Diary)->limit(3)->get(),
            'overview' => RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW),
        ]);
    }
}
