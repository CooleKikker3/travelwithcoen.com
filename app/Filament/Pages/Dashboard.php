<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickActions;
use Filament\Pages\Dashboard as BaseDashboard;

/** Only the quick-action buttons: nothing to load on a weak connection. Numbers live under Statistics. */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [QuickActions::class];
    }
}
