<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Country;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', [
            'preparation' => Article::published()->where('type', ArticleType::Preparation)->limit(3)->get(),
            'diary' => Article::published()->where('type', ArticleType::Diary)->limit(3)->get(),
            'countries' => Country::published()->get(),
        ]);
    }
}
