<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(): View
    {
        return view('countries.index', [
            'countries' => Country::published()->withCount(['articles' => fn ($query) => $query->published()])->get(),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();

        $country = Country::published()
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, config('app.fallback_locale'))))
            ->firstOrFail();

        if ($country->translate('slug', $locale) !== $slug) {
            return redirect($country->url(), 301);
        }

        return view('countries.show', [
            'country' => $country,
            'articles' => $country->articles()->published()->get(),
            'alternates' => collect(array_keys(config('travel.locales')))
                ->mapWithKeys(fn (string $l) => [$l => $country->url($l)])
                ->all(),
        ]);
    }
}
