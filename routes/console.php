<?php

use App\Models\Article;
use App\Models\GalleryItem;
use App\Services\YouTubeSync;
use App\Support\MediaStorage;
use App\Support\Settings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
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

// Files removed or replaced on the site stay in storage; this deletes the ones nothing refers to any more.
// Only files older than a week: time to undo mistakes, and uploads that were never saved are cleaned up too.
Artisan::command('media:prune {--dry-run : Only list what would be deleted} {--days=7}', function () {
    $disk = MediaStorage::disk();
    $cutoff = now()->subDays((int) $this->option('days'))->getTimestamp();

    $used = collect([
        ...GalleryItem::whereNotNull('path')->pluck('path'),
        ...Article::whereNotNull('cover_image')->pluck('cover_image'),
        Settings::get('home_image'),
        ...array_values(Settings::get('home_image_variants') ?? []),
    ])->filter()->flip();
    // Images placed in article text are referenced inside the (JSON) body.
    $bodies = Article::pluck('body')->map(fn ($body) => json_encode($body, JSON_UNESCAPED_SLASHES))->join("\n");

    $orphans = collect(['articles/images', 'articles/covers', 'gallery', 'site'])
        ->flatMap(fn ($directory) => $disk->allFiles($directory))
        ->reject(fn ($path) => $used->has($path) || str_contains($bodies, $path))
        ->filter(fn ($path) => $disk->lastModified($path) < $cutoff)
        ->values();

    foreach ($orphans as $path) {
        $this->line(($this->option('dry-run') ? 'Would delete: ' : 'Deleted: ').$path);
    }
    if (! $this->option('dry-run')) {
        $disk->delete($orphans->all());
        Log::info('Unused media deleted', ['files' => $orphans->all()]);
    }
    $this->info($orphans->count().' unused file(s)'.($this->option('dry-run') ? ' found.' : ' deleted.'));
})->purpose('Delete media files that are no longer used anywhere on the site');

Schedule::command('media:prune')->weeklyOn(1, '04:00')->withoutOverlapping();

// Assign every route point and GPS point to the country it lies in (CountryLocator); run after adding a country.
Artisan::command('geo:countries', function () {
    $locator = app(\App\Support\CountryLocator::class);

    \App\Models\CountryRoute::withoutGlobalScope(\App\Models\CountryRoute::PUBLISHED)->with('points')->get()->each(fn ($route) => $route->replacePoints(
        $route->points->map(fn ($p) => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'ele' => $p->elevation, 'time' => $p->recorded_at])->all()
    ));

    $points = \App\Models\TrackingPoint::orderBy('recorded_at')->get(['id', 'latitude', 'longitude', 'country_id']);
    $countries = $locator->locateAll($points->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all(), $points->first()?->country_id);
    $points->each(fn ($p, $i) => $p->country_id === $countries[$i] ?: \App\Models\TrackingPoint::whereKey($p->id)->update(['country_id' => $countries[$i]]));
    app(\App\Services\TrackingRecorder::class)->updateCurrentCountry();

    $this->info(\App\Models\CountryRoute::count().' route pieces and '.$points->count().' GPS points assigned to countries.');
})->purpose('Assign route and GPS points to the country they lie in');
Schedule::command('geo:countries')->weeklyOn(1, '04:30')->withoutOverlapping();

// Files uploaded before every R2 write got a cache lifetime (config/filesystems.php): add it, keeping their metadata.
Artisan::command('media:cache-headers', function () {
    $disk = MediaStorage::disk();
    if (! method_exists($disk, 'getClient')) {
        return $this->warn('Media are stored locally: nothing to do.');
    }
    $client = $disk->getClient();
    $bucket = config('filesystems.disks.r2.bucket');
    $fixed = 0;

    foreach (['articles/images', 'articles/covers', 'gallery', 'site'] as $directory) {
        foreach ($disk->allFiles($directory) as $path) {
            $head = $client->headObject(['Bucket' => $bucket, 'Key' => $path]);
            if (str_contains((string) ($head['CacheControl'] ?? ''), 'max-age')) {
                continue;
            }
            $client->copyObject([
                'Bucket' => $bucket,
                'Key' => $path,
                'CopySource' => $bucket.'/'.str_replace('%2F', '/', rawurlencode($path)),
                'MetadataDirective' => 'REPLACE',
                'Metadata' => $head['Metadata'] ?? [],
                'ContentType' => $head['ContentType'] ?? 'application/octet-stream',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
            $fixed++;
        }
    }

    $this->info("{$fixed} file(s) given a cache lifetime of one year.");
})->purpose('Give existing media files in R2 a long browser cache lifetime');

// Garmin inReach: new positions from the MapShare feed (GARMIN_MAPSHARE_URL). The device sends every 10 min or more.
Artisan::command('garmin:sync', function () {
    if (blank(config('travel.garmin.mapshare_url'))) {
        return $this->warn('No GARMIN_MAPSHARE_URL set.');
    }
    $this->info(app(\App\Services\GarminMapShare::class)->sync().' new position(s) from Garmin.');
})->purpose('Fetch new Garmin inReach positions from MapShare');
Schedule::command('garmin:sync')->everyTenMinutes()->withoutOverlapping();
