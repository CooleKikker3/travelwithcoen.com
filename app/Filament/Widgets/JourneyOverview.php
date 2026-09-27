<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Enums\CountryStatus;
use App\Enums\JourneyPhase;
use App\Models\Article;
use App\Models\Country;
use App\Models\Expense;
use App\Models\GalleryItem;
use App\Models\TrackingPoint;
use App\Models\Video;
use App\Support\JourneyStats;
use App\Support\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class JourneyOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = JourneyStats::for(auth()->user());
        $lastPoint = TrackingPoint::latest('recorded_at')->first();

        $spent = Expense::sum('amount_eur');
        $available = Settings::get('budget_total_eur') - Settings::get('budget_reserve_eur');
        $current = Country::where('status', CountryStatus::Current)->first();

        return [
            Stat::make('Journey status', JourneyPhase::from(Settings::get('journey_phase'))->getLabel())
                ->description($current ? 'Current country: '.$current->translate('name', 'en') : 'No current country set'),
            Stat::make('Last tracking update', $lastPoint?->recorded_at->diffForHumans() ?? '—')
                ->description($lastPoint ? 'Received '.$lastPoint->received_at->diffForHumans() : 'No tracking data yet')
                ->color($lastPoint && $lastPoint->recorded_at->gt(now()->subHours(6)) ? 'success' : 'warning'),
            Stat::make('Total distance', Number::format($stats['distance_km'], 0).' km')
                ->description(Number::format(\App\Models\JourneyDay::where('date', '>=', now()->startOfMonth())->sum('distance_km'), 0).' km this month · '.$stats['countries'].' countries'),
            Stat::make('Articles', Article::where('status', ArticleStatus::Published)->count())
                ->description(Article::where('status', ArticleStatus::Draft)->count().' drafts'),
            Stat::make('Photos / videos', GalleryItem::count().' / '.Video::count()),
            Stat::make('Budget spent (private)', Number::currency($spent, 'EUR'))
                ->description(Number::currency($available - $spent, 'EUR').' left excl. reserve'
                    .($stats['walking_days'] ? ' · '.Number::currency($spent / max(1, $stats['days']), 'EUR').'/day' : ''))
                ->color($spent > $available ? 'danger' : 'success'),
        ];
    }
}
