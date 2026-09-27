<?php

namespace App\Http\Controllers;

use App\Enums\RouteType;
use App\Models\Country;
use App\Support\JourneyStats;
use App\Support\RouteGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CountryController extends Controller
{
    /** Global overview: one map of all routes plus a timeline with a map per country. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $countries = Country::published()
            ->with('routes')
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->get();

        $routes = $countries->flatMap->routes;
        $stats = JourneyStats::for($user);

        return view('countries.index', [
            'countries' => $countries,
            'overview' => RouteGeometry::withTracking(RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW), $user, null, RouteGeometry::OVERVIEW),
            'maps' => $countries->mapWithKeys(fn (Country $country) => [
                $country->id => RouteGeometry::withTracking(RouteGeometry::featureCollection($country->routes, RouteGeometry::OVERVIEW / 2), $user, $country->id, RouteGeometry::OVERVIEW / 2),
            ]),
            'distances' => $countries->mapWithKeys(fn (Country $country) => [
                $country->id => $this->distances($country->routes, JourneyStats::for($user, $country)),
            ]),
            'totals' => $this->distances($routes, $stats) + ['countries' => $stats['countries']],
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
            'map' => RouteGeometry::withTracking(RouteGeometry::featureCollection($country->routes, RouteGeometry::DETAILED), $user, $country->id, RouteGeometry::DETAILED),
            'distances' => $this->distances($country->routes, $stats),
            'stats' => $stats,
            'articles' => $country->articles()->published()->get(),
            'photos' => $country->photos()->public()->limit(12)->get(),
            'videos' => $country->videos()->public()->get(),
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
    private function distances(Collection $routes, array $stats): array
    {
        return [
            'planned' => (float) $routes->where('type', RouteType::Planned)->sum('distance_km'),
            'actual' => $stats['days'] ? $stats['distance_km'] : (float) $routes->where('type', RouteType::Actual)->sum('distance_km'),
        ];
    }
}
