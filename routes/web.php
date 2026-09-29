<?php

use App\Enums\ArticleType;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TrackingController;
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
    Route::get($segment('live'), [TrackingController::class, 'live'])->name('live');
    // The public statistics page was removed; keep old links working.
    Route::get($segment('statistics'), fn () => redirect(lroute('journey'), 301));
    Route::get($segment('equipment'), [PageController::class, 'equipment'])->name('equipment');
    Route::get($segment('gallery'), [PageController::class, 'gallery'])->name('gallery');

    Route::get($segment('stories'), [ArticleController::class, 'stories'])->name('stories');
    Route::get($segment('diary'), [ArticleController::class, 'index'])->defaults('type', ArticleType::Diary->value)->name('diary.index');
    Route::get($segment('diary').'/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Diary->value)->name('diary.show');

    Route::get($segment('preparation'), [ArticleController::class, 'index'])->defaults('type', ArticleType::Preparation->value)->name('preparation.index');
    Route::get($segment('preparation').'/{slug}', [ArticleController::class, 'show'])->defaults('type', ArticleType::Preparation->value)->name('preparation.show');

    Route::get($segment('login'), [LoginController::class, 'show'])->middleware('guest')->name('login');
    Route::post($segment('login'), [LoginController::class, 'store'])->middleware(['guest', 'throttle:5,1']);
    Route::post($segment('logout'), [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
};

foreach (array_keys(config('travel.locales')) as $locale) {
    $locale === config('app.fallback_locale')
        ? Route::middleware(["locale:{$locale}", 'coming-soon'])->group($publicRoutes($locale))
        : Route::prefix($locale)->name("{$locale}.")->middleware(["locale:{$locale}", 'coming-soon'])->group($publicRoutes($locale));
}

Route::get('sitemap.xml', SitemapController::class)->middleware(['locale:en', 'coming-soon'])->name('sitemap');
// robots.txt as a route, so it can point search engines to the sitemap with the full (environment) URL.
Route::get('robots.txt', fn () => response(
    "User-agent: *\nDisallow: /admin\nDisallow: /api/\n\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain'],
));

// Tracking API. Privacy is enforced here on the server, never in the browser.
Route::prefix('api')->name('api.')->group(function () {
    Route::get('public/tracking', [TrackingController::class, 'publicIndex'])->middleware('throttle:60,1')->name('tracking.public');
    Route::get('private/tracking', [TrackingController::class, 'privateIndex'])->middleware(['auth', 'can:see-live-tracking', 'throttle:120,1'])->name('tracking.private');
    Route::get('track', [TrackingController::class, 'track'])->middleware('throttle:240,1')->name('track');
    Route::post('tracking', [TrackingController::class, 'ingest'])->middleware('throttle:60,1')->name('tracking.ingest');
});
