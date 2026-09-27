<?php

namespace App\Http\Controllers;

use App\Enums\RouteType;
use App\Models\Country;
use App\Support\RouteGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CountryController extends Controller
{
    /** Global overview: one map of all routes plus a timeline with a map per country. */
    public function index(): View
    {
        $countries = Country::published()
            ->with('routes')
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->get();

        $routes = $countries->flatMap->routes;

        return view('countries.index', [
            'countries' => $countries,
            'overview' => RouteGeometry::featureCollection($routes, RouteGeometry::OVERVIEW),
            'maps' => $countries->mapWithKeys(fn (Country $country) => [
                $country->id => RouteGeometry::featureCollection($country->routes, RouteGeometry::OVERVIEW / 2),
            ]),
            'totals' => $this->distances($routes),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();

        $country = Country::published()
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, config('app.fallback_locale'))))
            ->with('routes')
            ->firstOrFail();

        if ($country->translate('slug', $locale) !== $slug) {
            return redirect($country->url(), 301);
        }

        return view('countries.show', [
            'country' => $country,
            'map' => RouteGeometry::featureCollection($country->routes, RouteGeometry::DETAILED),
            'distances' => $this->distances($country->routes),
            'articles' => $country->articles()->published()->get(),
            'alternates' => collect(array_keys(config('travel.locales')))
                ->mapWithKeys(fn (string $l) => [$l => $country->url($l)])
                ->all(),
        ]);
    }

    /** @return array{planned: float, actual: float} */
    private function distances($routes): array
    {
        return [
            'planned' => $routes->where('type', RouteType::Planned)->sum('distance_km'),
            'actual' => $routes->where('type', RouteType::Actual)->sum('distance_km'),
        ];
    }
}
