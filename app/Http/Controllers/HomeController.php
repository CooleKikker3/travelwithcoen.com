<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
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
            'stats' => JourneyStats::for($user),
            // Stops in the rough direction that match a country link to its page.
            'countryLinks' => Country::published()->get()->mapWithKeys(fn (Country $country) => [mb_strtolower($country->translate('name')) => $country->url()]),
            'heroImage' => ($image = Settings::get('home_image')) ? MediaStorage::url($image) : null,
            'lastLocation' => TrackingPoint::visibleTo($user)->with('country')->latest('recorded_at')->first(),
            'preparation' => Article::published()->where('type', ArticleType::Preparation)->limit(3)->get(),
            'diary' => Article::published()->where('type', ArticleType::Diary)->limit(3)->get(),
            'gallery' => GalleryItem::public()->limit(8)->get(),
            'overview' => RouteGeometry::withTracking(RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW), $user, null, RouteGeometry::OVERVIEW),
        ]);
    }
}
