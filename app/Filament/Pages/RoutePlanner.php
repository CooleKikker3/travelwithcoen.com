<?php

namespace App\Filament\Pages;

use App\Enums\RouteType;
use App\Filament\Support\Options;
use App\Models\CountryRoute;
use App\Support\RouteGeometry;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Draw a (planned) route by clicking waypoints on a map. Between waypoints the line is either
 * straight or follows walking paths, calculated by the free BRouter service (brouter.de).
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

    public ?int $countryId = null;

    public string $type = 'planned';

    public ?string $name = null;

    public string $routing = 'hiking';

    public function mount(): void
    {
        if ($existing = CountryRoute::find($this->route)) {
            $this->countryId = $existing->country_id;
            $this->type = $existing->type->value;
            $this->name = $existing->name;
            $this->routing = $existing->routing ?? 'hiking';
        }
    }

    /** Data for the map script: this route's waypoints and line, plus other routes for context. */
    public function initialData(): array
    {
        $existing = CountryRoute::find($this->route);
        $line = $existing?->points()->get(['latitude', 'longitude'])->map(fn ($p) => [$p->latitude, $p->longitude])->all() ?? [];

        return [
            'waypoints' => $existing?->waypoints ?? [],
            // Routes imported from GPX have no waypoints: show their line as a reference.
            'line' => $existing?->waypoints ? $line : [],
            'reference' => array_merge(RouteGeometry::featureCollection(
                CountryRoute::when($existing, fn ($q) => $q->whereKeyNot($existing->id))->get(),
                RouteGeometry::OVERVIEW / 5
            )['features'], $existing && ! $existing->waypoints && $line ? [['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'LineString', 'coordinates' => array_map(fn ($p) => [$p[1], $p[0]], $line)]]] : []),
        ];
    }

    public function countryOptions(): array
    {
        return Options::countries();
    }

    /**
     * Line between two waypoints ([lat, lng]) following walking paths. Falls back to a straight line.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function segment(array $from, array $to): array
    {
        [$from, $to] = [array_map('floatval', array_slice($from, 0, 2)), array_map('floatval', array_slice($to, 0, 2))];

        if ($this->routing !== 'hiking') {
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
     * @param  array<int, array{0: float, 1: float, 2: int}>  $waypoints  [lat, lng, index in line]
     * @param  array<int, array{0: float, 1: float}>  $line
     */
    public function save(array $waypoints, array $line): void
    {
        if (! $this->countryId || count($line) < 2) {
            Notification::make()->danger()->title('Kies een land en zet minstens twee punten.')->send();

            return;
        }

        $route = CountryRoute::updateOrCreate(['id' => $this->route], [
            'country_id' => $this->countryId,
            'type' => RouteType::from($this->type),
            'name' => $this->name,
            'routing' => $this->routing,
            'waypoints' => $waypoints,
            'gpx_path' => null,
        ]);
        $route->replacePoints(array_map(fn ($p) => ['lat' => (float) $p[0], 'lng' => (float) $p[1]], $line));
        $this->route = $route->id;

        Notification::make()->success()->title('Route opgeslagen: '.number_format($route->fresh()->distance_km, 1).' km')->send();
    }
}
