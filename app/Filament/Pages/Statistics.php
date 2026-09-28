<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\JourneyOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Journey numbers (distance, tracking, articles, gallery, budget), only loaded when opened. */
class Statistics extends Page
{
    protected string $view = 'filament.pages.statistics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Statistieken';

    protected static ?string $title = 'Statistieken';

    protected static ?int $navigationSort = 1;

    protected function getHeaderWidgets(): array
    {
        return [JourneyOverview::class];
    }
}
