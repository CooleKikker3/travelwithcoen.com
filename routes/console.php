<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull new videos from the configured YouTube channel (needs the scheduler: `php artisan schedule:work` locally, cron in production).
Artisan::command('youtube:sync', function (App\Services\YouTubeSync $sync) {
    $this->info($sync->sync().' new videos added.');
})->purpose('Sync videos from the configured YouTube channel');

Schedule::command('youtube:sync')->hourly()->withoutOverlapping();

// Check the media storage (local disk or R2): write, read, public URL, delete a small test file.
Artisan::command('media:check', function () {
    $disk = App\Support\MediaStorage::diskName();
    $path = 'healthcheck/'.Illuminate\Support\Str::random(8).'.txt';
    $this->info("Media disk: {$disk}");

    try {
        App\Support\MediaStorage::disk()->put($path, 'ok');
        $this->line('Write: ok');
        $this->line('Read: '.(App\Support\MediaStorage::disk()->get($path) === 'ok' ? 'ok' : 'FAILED'));
        $url = App\Support\MediaStorage::url($path);
        $public = rescue(fn () => Illuminate\Support\Facades\Http::timeout(15)->get($url)->body() === 'ok', false, report: false);
        $this->line("Public URL: {$url} → ".($public ? 'ok' : 'not reachable (check R2_URL / public access)'));
        App\Support\MediaStorage::disk()->delete($path);
        $this->line('Delete: ok');
    } catch (Throwable $e) {
        $this->error('Failed: '.$e->getMessage());

        return 1;
    }
})->purpose('Check that the media storage (local or Cloudflare R2) works');
