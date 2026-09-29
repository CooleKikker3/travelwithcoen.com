<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Enums\CountryStatus;
use App\Models\Article;
use App\Models\Country;
use App\Models\GalleryItem;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Support\JourneyStats;
use App\Support\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class JourneyOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // No automatic refresh every few seconds: costly on a weak connection.
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = JourneyStats::for(auth()->user());
        $lastPoint = TrackingPoint::latest('recorded_at')->first();

        $current = Country::where('status', CountryStatus::Current)->first();

        return [
            Stat::make('Huidig land', $current ? $current->translate('name', 'nl') ?? $current->translate('name', 'en') : '—')
                ->description('Automatisch uit je laatste locatie'),
            Stat::make('Laatste locatie-update', $lastPoint?->recorded_at->diffForHumans() ?? '—')
                ->description($lastPoint ? 'Ontvangen '.$lastPoint->received_at->diffForHumans() : 'Nog geen locaties ontvangen')
                ->color($lastPoint && $lastPoint->recorded_at->gt(now()->subHours(6)) ? 'success' : 'warning'),
            Stat::make('Totale afstand', Number::format($stats['distance_km'], 0).' km')
                ->description(Number::format(JourneyDay::where('date', '>=', now()->startOfMonth())->sum('distance_km'), 0).' km deze maand · '.$stats['countries'].' landen'),
            Stat::make('Artikelen', Article::where('status', ArticleStatus::Published)->count())
                ->description(Article::where('status', ArticleStatus::Draft)->count().' concepten'),
            Stat::make('Galerij (eigen uploads)', GalleryItem::where('kind', '!=', 'youtube')->count())
                ->description(GalleryItem::where('kind', 'image')->count().' foto\'s · '.GalleryItem::where('kind', 'video')->count().' video\'s'),
            Stat::make('YouTube-video\'s', GalleryItem::where('kind', 'youtube')->count())
                ->description(($latest = GalleryItem::where('kind', 'youtube')->latest('taken_at')->first())
                    ? 'Nieuwste: '.$latest->taken_at->format('j M Y')
                    : (filled(config('travel.youtube_channel')) ? 'Nog niet opgehaald' : 'Geen kanaal ingesteld'))
                ->color('danger'),

        ];
    }
}
