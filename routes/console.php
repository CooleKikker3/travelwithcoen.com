<?php

use App\Models\GalleryItem;
use App\Services\YouTubeSync;
use App\Support\MediaStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull new videos from the configured YouTube channel (needs the scheduler: `php artisan schedule:work` locally, cron in production).
Artisan::command('youtube:sync', function (YouTubeSync $sync) {
    $this->info($sync->sync().' new videos added.');
})->purpose('Sync videos from the configured YouTube channel');

Schedule::command('youtube:sync')->hourly()->withoutOverlapping();

// Check the media storage (local disk or R2): write, read, public URL, delete a small test file.
Artisan::command('media:check', function () {
    $disk = MediaStorage::diskName();
    $path = 'healthcheck/'.Str::random(8).'.txt';
    $this->info("Media disk: {$disk}");

    try {
        MediaStorage::disk()->put($path, 'ok');
        $this->line('Write: ok');
        $this->line('Read: '.(MediaStorage::disk()->get($path) === 'ok' ? 'ok' : 'FAILED'));
        $url = MediaStorage::url($path);
        $public = rescue(fn () => Http::timeout(15)->get($url)->body() === 'ok', false, report: false);
        $this->line("Public URL: {$url} → ".($public ? 'ok' : 'not reachable (check R2_URL / public access)'));
        MediaStorage::disk()->delete($path);
        $this->line('Delete: ok');
    } catch (Throwable $e) {
        $this->error('Failed: '.$e->getMessage());

        return 1;
    }
})->purpose('Check that the media storage (local or Cloudflare R2) works');

// Add upload metadata to gallery files that were stored in R2 before metadata existed.
Artisan::command('media:describe', function () {
    $items = GalleryItem::whereNotNull('path')->get();
    $items->each(fn ($item) => MediaStorage::describe($item->path, $item->storageMetadata()));
    $this->info($items->count().' files described on disk "'.MediaStorage::diskName().'".');
})->purpose('Write upload metadata onto existing media files in R2');
