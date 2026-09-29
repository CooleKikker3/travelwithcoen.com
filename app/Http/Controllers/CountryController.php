<?php

namespace App\Http\Controllers;

use App\Enums\ArticleType;
use App\Enums\RouteType;
use App\Models\Article;
use App\Models\Country;
use App\Support\JourneyStats;
use App\Support\RouteGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CountryController extends Controller
{
    /** Global overview: map of all routes, a timeline with a map per country, and the latest stories. */
    public function index(Request $request): View|RedirectResponse
    {
        // Stories used to be filtered here (?type=&tag=): they have their own page now.
        if ($request->hasAny(['type', 'tag'])) {
            return redirect(stories_url($request->query('type'), $request->query('tag')), 301);
        }

        $user = $request->user();
        $countries = Country::published()
            ->with('routes')
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->get();

        $routes = $countries->flatMap->routes;
        $stats = JourneyStats::for($user);

        return view('countries.index', [
            'countries' => $countries,
            'overview' => RouteGeometry::withTracking(RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW), $user, null, 2),
            'maps' => $countries->mapWithKeys(fn (Country $country) => [
                $country->id => RouteGeometry::withTracking(RouteGeometry::featureCollection($country->routesThrough(), RouteGeometry::OVERVIEW / 2, $country->id), $user, $country->id, 1),
            ]),
            'distances' => $countries->mapWithKeys(fn (Country $country) => [
                $country->id => $this->distances($country, JourneyStats::for($user, $country)),
            ]),
            'totals' => [
                'planned' => (float) $routes->where('type', RouteType::Planned)->sum('distance_km'),
                'actual' => $stats['days'] ? $stats['distance_km'] : (float) $routes->where('type', RouteType::Actual)->sum('distance_km'),
                'countries' => $stats['countries'],
            ],
            // First timeline item: the preparation, before the first country.
            'preparation' => Article::published()->where('type', ArticleType::Preparation)->limit(3)->get(),
            'preparationCount' => Article::published()->where('type', ArticleType::Preparation)->count(),
            'latest' => Article::published()->with('country')->limit(3)->get(),
            // Day by day (visitors after the delay), newest first.
            'days' => \App\Models\JourneyDay::visibleTo($user)->select('journey_days.*')->withNumber()->with('country')->latest('date')->limit(14)->get(),

        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $user = $request->user();
        $locale = app()->getLocale();

        $country = Country::published()
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, config('app.fallback_locale'))))
            ->with('routes')
            ->firstOrFail();

        if ($country->translate('slug', $locale) !== $slug) {
            return redirect($country->url(), 301);
        }

        $stats = JourneyStats::for($user, $country);

        return view('countries.show', [
            'country' => $country,
            'map' => RouteGeometry::withTracking(RouteGeometry::featureCollection($country->routesThrough(), RouteGeometry::DETAILED, $country->id), $user, $country->id, 2),
            'distances' => $this->distances($country, $stats),
            'pieces' => $country->routesThrough()->where('type', RouteType::Planned),
            'stats' => $stats,
            'articles' => $country->articles()->published()->get(),
            'gallery' => $country->gallery()->public()->limit(12)->get(),
            'alternates' => collect(array_keys(config('travel.locales')))
                ->mapWithKeys(fn (string $l) => [$l => $country->url($l)])
                ->all(),
        ]);
    }

    /**
     * Planned km from the planned routes. Walked km from the journey days,
     * or from imported actual routes as long as there are no days yet.
     *
     * @return array{planned: float, actual: float}
     */
    /** Kilometres within this country (route pieces can cross borders). */
    private function distances(Country $country, array $stats): array
    {
        $routes = $country->routesThrough();

        return [
            'planned' => $routes->where('type', RouteType::Planned)->sum(fn ($route) => $route->kmIn($country->id)),
            'actual' => $stats['days'] ? $stats['distance_km'] : $routes->where('type', RouteType::Actual)->sum(fn ($route) => $route->kmIn($country->id)),
        ];
    }
}
