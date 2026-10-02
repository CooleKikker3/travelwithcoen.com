<?php

namespace App\Filament\Pages;

use App\Models\GalleryItem;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Support\JourneyStats;
use App\Support\MediaStorage;
use App\Support\RouteGeometry;
use App\Support\TrackingPrivacy;
use Illuminate\Support\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;

/**
 * Instagram story with numbers, not tied to an article: "today" (the latest journey day) or "so far" (the whole
 * walk), including straight-line ("as the crow flies") distances from home and to Hanoi. Drawn in the browser by
 * resources/js/insta-story.js like the article stories. Shows where Coen is now, unlike the delayed website.
 */
class CounterStory extends Page
{
    protected string $view = 'filament.pages.counter-story';

    protected static ?string $title = 'Teller-story';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $mode = 'today';

    /** Two sets of numbers: as visitors see them (after the tracking delay, the default) and live. */
    public function storyData(): array
    {
        return [
            'kind' => 'stats',
            // Photos to pick from (newest first); a photo can also be uploaded, only for drawing the story (not saved).
            'gallery' => GalleryItem::where('kind', 'image')->where('is_sensitive', false)->whereNotNull('path')->latest('taken_at')->limit(30)
                ->pluck('path')->map(fn (string $path) => MediaStorage::url($path))->all(),
            'delayDays' => round(TrackingPrivacy::delayHours() / 24),
            'variants' => ['delayed' => $this->numbers(TrackingPrivacy::publicCutoff()), 'live' => $this->numbers(null)],
        ];
    }

    /** Numbers up to the cutoff (null = live). */
    private function numbers(?Carbon $cutoff): array
    {
        $day = JourneyDay::select('journey_days.*')->withNumber()->with('country')
            ->when($cutoff, fn ($query) => $query->visibleTo(null))
            ->whereDate('date', '<=', now()->toDateString())->latest('date')->first();
        $stats = JourneyStats::for($cutoff ? null : auth()->user());
        $home = [config('travel.start.lat'), config('travel.start.lng')];
        $hanoi = [config('travel.destination.lat'), config('travel.destination.lng')];
        $point = fn (?int $id) => ($p = $id ? TrackingPoint::find($id) : null) ? [$p->latitude, $p->longitude] : null;
        $latest = TrackingPoint::when($cutoff, fn ($query) => $query->visibleTo(null))->latest('recorded_at')->first();
        $now = $latest ? [$latest->latitude, $latest->longitude] : $point($day?->end_point_id);
        [$dayStart, $dayEnd] = [$point($day?->start_point_id), $point($day?->end_point_id)];
        $fromHome = $now ? RouteGeometry::haversine($home, $now) : null;
        $toHanoi = $now ? RouteGeometry::haversine($now, $hanoi) : null;

        // A photo from before the cutoff, else the newest one (Coen sees it in the preview and can pick another).
        $photos = fn ($query) => $query->where('kind', 'image')->where('is_sensitive', false)->whereNotNull('path')->latest('taken_at');
        $photo = fn ($query) => ($cutoff ? $photos((clone $query)->where('taken_at', '<=', $cutoff))->value('path') : null) ?? $photos($query)->value('path');
        $todayPhoto = ($day ? $photo(GalleryItem::where('journey_day_id', $day->id)) : null) ?? $photo(GalleryItem::query());

        $locales = [];
        foreach (['nl', 'en'] as $locale) {
            $n = fn (?float $value, int $decimals = 0) => $value === null ? null : Number::format($value, maxPrecision: $decimals, locale: $locale);
            $item = fn (string $key, ?string $value, string $label) => $value === null || $value === '' || $value === '0' ? null : compact('key', 'value', 'label');
            $hours = $day?->walking_minutes ? sprintf('%d:%02d', intdiv($day->walking_minutes, 60), $day->walking_minutes % 60) : null;

            $common = [
                'url' => lroute('journey', [], $locale),
                'flag' => null,
                'note' => __('site.story.stats_note', [], $locale),
                'sticker' => __('site.story.stats_sticker', [], $locale),
                'site' => parse_url(config('app.url'), PHP_URL_HOST),
                'home' => config('travel.start.name'),
                'destination' => config('travel.destination.name'),
            ];

            $locales[$locale] = [
                'today' => $common + [
                    'headline' => $day ? __('site.day_name', ['number' => $day->number()], $locale) : __('site.story.stats_today', [], $locale),
                    'sub' => collect([$day?->country?->translate('name', $locale), $day?->date->locale($locale)->translatedFormat('j F Y')])->filter()->join(' · '),
                    'route' => $day && $day->start_location && $day->end_location ? "{$day->start_location} → {$day->end_location}" : null,
                    'flag' => $day?->country ? strtolower($day->country->iso_code) : null,
                    'items' => array_values(array_filter([
                        $item('km', $n($day?->distance_km, 1), __('site.story.km_today', [], $locale)),
                        $item('time', $hours, __('site.story.hours_walked', [], $locale)),
                        $item('crow_day', $dayStart && $dayEnd ? $n(RouteGeometry::haversine($dayStart, $dayEnd), 1) : null, __('site.story.crow_today', [], $locale)),
                        $item('crow_home', $n($fromHome), __('site.story.crow_home', ['home' => config('travel.start.name')], $locale)),
                        $item('crow_hanoi', $n($toHanoi), __('site.story.crow_hanoi', ['destination' => config('travel.destination.name')], $locale)),
                    ])),
                ],
                'total' => $common + [
                    'headline' => __('site.story.stats_total', [], $locale),
                    'sub' => $stats['days'] ? trans_choice('site.story.after_days', $stats['days'], ['days' => $stats['days']], $locale) : null,
                    'route' => null,
                    'flag' => $day?->country ? strtolower($day->country->iso_code) : null,
                    'items' => array_values(array_filter([
                        $item('km', $n($stats['distance_km']), __('site.story.km_label', [], $locale)),
                        $item('progress', $stats['planned_km'] > 0 && $stats['distance_km'] > 0 ? $n(min(100, $stats['distance_km'] / $stats['planned_km'] * 100)).'%' : null, __('site.story.of_the_way', [], $locale)),
                        $item('days', $n($stats['days']), __('site.story.days_on_road', [], $locale)),
                        $item('countries', $n($stats['countries']), __('site.story.countries', [], $locale)),
                        $item('tent', $n($stats['tent_nights']), __('site.story.tent_nights', [], $locale)),
                        $item('hours', $n($stats['walking_hours']), __('site.story.hours_walked', [], $locale)),
                        $item('crow_home', $n($fromHome), __('site.story.crow_home', ['home' => config('travel.start.name')], $locale)),
                        $item('crow_hanoi', $n($toHanoi), __('site.story.crow_hanoi', ['destination' => config('travel.destination.name')], $locale)),
                    ])),
                ],
            ];
        }

        return [
            'photos' => [
                'today' => $todayPhoto ? MediaStorage::url($todayPhoto) : null,
                'total' => ($p = $photo(GalleryItem::query())) ? MediaStorage::url($p) : null,
            ],
            // How far along, as the crow flies (0–1): the position on the Lisse–Hanoi line.
            'crow' => $fromHome !== null && $toHanoi !== null ? min(1, $fromHome / max(1, $fromHome + $toHanoi)) : null,
            'progress' => $stats['planned_km'] > 0 && $stats['distance_km'] > 0 ? min(1, $stats['distance_km'] / $stats['planned_km']) : null,
            'locales' => $locales,
        ];
    }
}
