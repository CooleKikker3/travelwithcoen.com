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

// The CMS loads existing uploads in the browser for their preview; R2 must allow that (CORS),
// otherwise image fields keep showing "Loading". Run again after adding a domain.
Artisan::command('media:cors {origins?* : Extra allowed origins, e.g. https://travelwithcoen.com}', function () {
    $disk = MediaStorage::disk();
    if (! method_exists($disk, 'getClient')) {
        return $this->warn('Media are stored locally: no CORS needed.');
    }

    $origins = array_values(array_unique([config('app.url'), 'http://localhost:8000', 'http://127.0.0.1:8000', ...$this->argument('origins')]));
    $rule = ['AllowedOrigins' => $origins, 'AllowedMethods' => ['GET', 'HEAD'], 'AllowedHeaders' => ['*'], 'MaxAgeSeconds' => 3600];

    try {
        $disk->getClient()->putBucketCors([
        'Bucket' => config('filesystems.disks.r2.bucket'),
            'CORSConfiguration' => ['CORSRules' => [$rule]],
        ]);
    } catch (\Aws\S3\Exception\S3Exception $e) {
        // The usual R2 token (Object Read & Write) may not change bucket settings: set it in the dashboard.
        $this->warn('Not allowed with this R2 token ('.$e->getAwsErrorCode().'). In Cloudflare: R2 > bucket > Settings > CORS policy, paste:');

        return $this->line(json_encode([$rule], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $this->info('R2 allows the CMS to load media from: '.implode(', ', $origins));
})->purpose('Allow the site (CMS previews) to load media from the R2 bucket');
