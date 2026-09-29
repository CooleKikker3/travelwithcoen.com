<?php

namespace App\Http\Controllers;

use App\Enums\JourneyPhase;
use App\Models\Article;
use App\Models\Country;
use App\Models\CountryRoute;
use App\Models\GalleryItem;
use App\Models\TrackingPoint;
use App\Support\JourneyStats;
use App\Support\MediaStorage;
use App\Support\RouteGeometry;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $routes = CountryRoute::whereHas('country', fn ($query) => $query->where('is_published', true))->get();

        return view('pages.home', [
            // Before departure the focus is preparation, during the walk progress, afterwards the archive.
            'phase' => JourneyPhase::tryFrom(Settings::get('journey_phase')) ?? JourneyPhase::Preparation,
            // Stops in the rough direction that match a country link to its page.
            'countryLinks' => Country::published()->get()->mapWithKeys(fn (Country $country) => [mb_strtolower($country->translate('name')) => $country->url()]),
            'heroImage' => ($image = Settings::get('home_image')) ? MediaStorage::url($image) : null,
            // Smaller WebP copies (see ImageProcessor::variants): the browser picks the size for the screen.
            'heroSrcset' => collect(Settings::get('home_image_variants') ?? [])->map(fn ($path, $width) => MediaStorage::url($path)." {$width}w")->join(', '),
            'lastLocation' => TrackingPoint::visibleTo($user)->with('country')->latest('recorded_at')->first(),
            'latest' => Article::published()->with('country')->limit(3)->get(),
            'gallery' => GalleryItem::public()->limit(8)->get(),
            'overview' => RouteGeometry::withOpenPlan(
                RouteGeometry::withTracking(RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW), $user, null, RouteGeometry::OVERVIEW),
            ),
            'delayDays' => $user?->canSeeLiveTracking() ? 0 : (int) round(Settings::get('public_tracking_delay_hours') / 24),
        ]);
    }
}
