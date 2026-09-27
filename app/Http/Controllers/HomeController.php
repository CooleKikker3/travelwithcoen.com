<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Enums\JourneyPhase;
use App\Models\Article;
use App\Models\CountryRoute;
use App\Models\GalleryItem;
use App\Models\TrackingPoint;
use App\Models\Video;
use App\Support\JourneyStats;
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
            'lastLocation' => TrackingPoint::visibleTo($user)->with('country')->latest('recorded_at')->first(),
            'preparation' => Article::published()->where('type', ArticleType::Preparation)->limit(3)->get(),
            'diary' => Article::published()->where('type', ArticleType::Diary)->limit(3)->get(),
            'photos' => GalleryItem::public()->limit(6)->get(),
            'video' => Video::public()->first(),
            'overview' => RouteGeometry::withTracking(RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW), $user, null, RouteGeometry::OVERVIEW),
        ]);
    }
}
