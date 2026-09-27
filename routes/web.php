<?php

use App\Enums\ArticleType;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
 * Public routes are registered once per locale:
 *   English (default)  /diary        route name "diary.index"
 *   Dutch              /nl/diary     route name "nl.diary.index"
 * Use lroute() to link to the current locale.
 */
$publicRoutes = function () {
    Route::get('/', HomeController::class)->name('home');
    Route::view('/about', 'pages.about')->name('about');

    Route::get('/journey', [CountryController::class, 'index'])->name('journey');
    Route::get('/countries/{slug}', [CountryController::class, 'show'])->name('countries.show');

    Route::get('/diary', [ArticleController::class, 'index'])->defaults('type', ArticleType::Diary->value)->name('diary.index');
    Route::get('/diary/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Diary->value)->name('diary.show');

    Route::get('/preparation', [ArticleController::class, 'preparation'])->name('preparation.index');
    Route::get('/preparation/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Preparation->value)->name('preparation.show');
};

Route::middleware('locale:en')->group($publicRoutes);

foreach (array_keys(config('travel.locales')) as $locale) {
    if ($locale !== config('app.fallback_locale')) {
        Route::prefix($locale)->name("{$locale}.")->middleware("locale:{$locale}")->group($publicRoutes);
    }
}
