<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\StoryClick;
use Filament\Widgets\Widget;

/** Statistics page: clicks on the links in Instagram stories, per article. */
class StoryClicks extends Widget
{
    protected string $view = 'filament.widgets.story-clicks';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected function getViewData(): array
    {
        $week = now()->subDays(7);

        return [
            'total' => StoryClick::count(),
            'week' => StoryClick::where('clicked_at', '>=', $week)->count(),
            'articles' => Article::query()
                ->withCount(['storyClicks', 'storyClicks as week_count' => fn ($query) => $query->where('clicked_at', '>=', $week)])
                ->withMax('storyClicks', 'clicked_at')
                ->whereHas('storyClicks')
                ->orderByDesc('story_clicks_count')
                ->limit(20)
                ->get(),
        ];
    }
}
