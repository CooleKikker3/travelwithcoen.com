<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull new videos from the configured YouTube channel (needs the scheduler: `php artisan schedule:work` locally, cron in production).
Artisan::command('youtube:sync', function (App\Services\YouTubeSync $sync) {
    $this->info($sync->sync().' videos synced.');
})->purpose('Sync videos from the configured YouTube channel');

Schedule::command('youtube:sync')->hourly()->withoutOverlapping();
