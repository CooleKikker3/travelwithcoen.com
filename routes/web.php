<?php

use App\Enums\ArticleType;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
 * Public routes are registered once per locale, with translated URL segments (lang/{locale}/routes.php):
 *   English (default)  /diary          route name "diary.index"
 *   Dutch              /nl/dagboek     route name "nl.diary.index"
 * Use lroute() to link to the current locale.
 */
$publicRoutes = fn (string $locale) => function () use ($locale) {
    $segment = fn (string $key) => trans("routes.{$key}", [], $locale);

    Route::get('/', HomeController::class)->name('home');
    Route::view($segment('about'), 'pages.about')->name('about');

    Route::get($segment('journey'), [CountryController::class, 'index'])->name('journey');
    Route::get($segment('countries').'/{slug}', [CountryController::class, 'show'])->name('countries.show');

    Route::get($segment('diary'), [ArticleController::class, 'index'])->defaults('type', ArticleType::Diary->value)->name('diary.index');
    Route::get($segment('diary').'/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Diary->value)->name('diary.show');

    Route::get($segment('preparation'), [ArticleController::class, 'preparation'])->name('preparation.index');
    Route::get($segment('preparation').'/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Preparation->value)->name('preparation.show');
};

foreach (array_keys(config('travel.locales')) as $locale) {
    $locale === config('app.fallback_locale')
        ? Route::middleware("locale:{$locale}")->group($publicRoutes($locale))
        : Route::prefix($locale)->name("{$locale}.")->middleware("locale:{$locale}")->group($publicRoutes($locale));
}
