<?php

namespace App\Filament\Pages;

use App\Enums\RouteType;
use App\Filament\Support\Options;
use App\Models\CountryRoute;
use App\Support\Polyline;
use App\Support\RouteGeometry;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Route editor. A route piece (e.g. "Nijmeegse Vierdaagse") has a title and a story, and is built from
 * parts: GPX files (read in the browser, only a simplified line is sent) and drawn pieces (click points;
 * straight or over walking paths via BRouter). All editing happens in the browser (resources/js/route-planner.js),
 * with a draft on the device; only "Opslaan" needs a connection.
 */
class RoutePlanner extends Page
{
    protected string $view = 'filament.pages.route-planner';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Reis';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Routeplanner';

    #[Url]
    public ?int $route = null;

    /** Everything the editor needs: this route piece, its parts, and the other routes for context. */
    public function initialData(): array
    {
        $existing = CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->with('segments')->find($this->route);

        return [
            'routeId' => $existing?->id,
            'updatedAt' => $existing?->updated_at?->getTimestampMs() ?? 0,
            'meta' => [
                'countryId' => $existing?->country_id,
                'type' => $existing?->type->value ?? 'planned',
                'titleNl' => $existing?->translate('title', 'nl', false) ?? $existing?->name,
                'titleEn' => $existing?->translate('title', 'en', false),
                'descriptionNl' => $existing?->translate('description', 'nl', false),
                'descriptionEn' => $existing?->translate('description', 'en', false),
                'isDraft' => (bool) $existing?->is_draft,
            ],
            'segments' => $existing ? $this->segmentsFor($existing) : [],
            'reference' => RouteGeometry::featureCollection(
                CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->get(),
                RouteGeometry::OVERVIEW / 5,
            )['features'],
        ];
    }

    /** Route pieces to switch between, newest first. */
    public function routeOptions(): array
    {
        return CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->with('country')->orderBy('is_draft')->orderBy('country_id')->orderBy('sort_order')->get()
            ->mapWithKeys(fn (CountryRoute $route) => [$route->id => $route->country?->flag().' '.$route->label().' · '.number_format((float) $route->distance_km, 1).' km'.($route->is_draft ? ' (concept)' : '')])
            ->all();
    }

    public function countryOptions(): array
    {
        return Options::countries();
    }

    /**
     * Line between two points ([lat, lng]) over walking paths; straight when asked or when BRouter is unreachable.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function segment(array $from, array $to, string $routing = 'hiking'): array
    {
        [$from, $to] = [array_map('floatval', array_slice($from, 0, 2)), array_map('floatval', array_slice($to, 0, 2))];

        if ($routing !== 'hiking') {
            return [$from, $to];
        }

        try {
            return Cache::remember('brouter:'.md5(json_encode([$from, $to])), now()->addMonth(), function () use ($from, $to) {
                $coordinates = Http::timeout(30)->get('https://brouter.de/brouter', [
                    'lonlats' => "{$from[1]},{$from[0]}|{$to[1]},{$to[0]}",
                    'profile' => 'hiking-mountain',
                    'alternativeidx' => 0,
                    'format' => 'geojson',
                ])->throw()->json('features.0.geometry.coordinates');

                // [lng, lat, ele] → [lat, lng], lightly simplified (~3 m).
                return RouteGeometry::simplify(array_map(fn ($c) => [round($c[1], 6), round($c[0], 6)], $coordinates), 0.00003);
            });
        } catch (Throwable) {
            Notification::make()->warning()->title('Wandelpaden niet beschikbaar; voor dit stuk een rechte lijn.')->send();

            return [$from, $to];
        }
    }

    /**
     * Save the route piece and its parts (lines as encoded polylines). Returns the new state for the editor.
     *
     * @return array{routeId: int, updatedAt: int, km: float}|null
     */
    public function save(array $payload): ?array
    {
        $validator = Validator::make($payload, [
            'meta.countryId' => ['required', 'exists:countries,id'],
            'meta.type' => ['required', 'in:planned,actual'],
            'meta.titleNl' => ['nullable', 'string', 'max:150'],
            'meta.titleEn' => ['nullable', 'string', 'max:150'],
            'meta.descriptionNl' => ['nullable', 'string', 'max:5000'],
            'meta.descriptionEn' => ['nullable', 'string', 'max:5000'],
            'meta.isDraft' => ['nullable'],
            'segments' => ['required', 'array', 'min:1', 'max:100'],
            'segments.*.kind' => ['required', 'in:gpx,drawn'],
            'segments.*.label' => ['nullable', 'string', 'max:150'],
            'segments.*.notes' => ['nullable', 'string', 'max:10000'],
            'segments.*.routing' => ['nullable', 'in:hiking,straight'],
            'segments.*.waypoints' => ['nullable', 'array', 'max:500'],
            'segments.*.line' => ['required', 'string', 'max:2000000'],
        ], ['meta.countryId.required' => 'Kies een land.', 'segments.required' => 'Voeg minstens één stuk route toe.']);

        if ($validator->fails()) {
            Notification::make()->danger()->title($validator->errors()->first())->send();

            return null;
        }

        $meta = $payload['meta'];
        $route = CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->find($this->route) ?? new CountryRoute([
            'sort_order' => (CountryRoute::where('country_id', $meta['countryId'])->max('sort_order') ?? 0) + 10,
        ]);
        $route->fill([
            'country_id' => $meta['countryId'],
            'type' => RouteType::from($meta['type']),
            'name' => $meta['titleNl'] ?: ($meta['titleEn'] ?: $route->name),
            'title' => array_filter(['nl' => $meta['titleNl'] ?? null, 'en' => $meta['titleEn'] ?? null]),
            'description' => array_filter(['nl' => $meta['descriptionNl'] ?? null, 'en' => $meta['descriptionEn'] ?? null]),
            'is_draft' => filter_var($meta['isDraft'] ?? false, FILTER_VALIDATE_BOOL),
            'gpx_path' => null,
            'waypoints' => null,
        ])->save();

        $route->replaceSegments(array_map(fn (array $segment) => [
            'kind' => $segment['kind'],
            'label' => $segment['label'] ?? null,
            'notes' => $segment['notes'] ?? null,
            'routing' => $segment['kind'] === 'drawn' ? ($segment['routing'] ?? 'hiking') : null,
            'waypoints' => $segment['kind'] === 'drawn' ? ($segment['waypoints'] ?? []) : null,
            'line' => $segment['line'],
        ], $payload['segments']));

        $route->refresh();
        $this->route = $route->id;

        Notification::make()->success()->title('Route opgeslagen: '.number_format($route->distance_km, 1, ',', '.').' km')->send();

        return ['routeId' => $route->id, 'updatedAt' => $route->updated_at->getTimestampMs(), 'km' => (float) $route->distance_km];
    }

    /** The saved route piece as GPX (for Garmin). */
    public function downloadGpx()
    {
        $route = CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->findOrFail($this->route);

        return response()->streamDownload(fn () => print (\App\Support\GpxExport::route($route)), \App\Support\GpxExport::filename($route), ['Content-Type' => 'application/gpx+xml']);
    }

    public function deleteRoute(): void
    {
        CountryRoute::withoutGlobalScope(CountryRoute::PUBLISHED)->find($this->route)?->delete();
        Notification::make()->success()->title('Routestuk verwijderd')->send();
        $this->redirect(self::getUrl());
    }

    /** Parts of a route for the editor; older routes without parts become one part. */
    private function segmentsFor(CountryRoute $route): array
    {
        if ($route->segments->isNotEmpty()) {
            return $route->segments->map(fn ($s) => $s->only(['kind', 'label', 'notes', 'routing', 'waypoints', 'line']))->all();
        }

        $points = $route->points()->get(['latitude', 'longitude'])->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all();

        return count($points) < 2 ? [] : [[
            'kind' => $route->waypoints ? 'drawn' : 'gpx',
            'label' => $route->name,
            'routing' => $route->waypoints ? ($route->routing ?? 'hiking') : null,
            'waypoints' => $route->waypoints,
            'line' => Polyline::encode($points),
        ]];
    }
}
