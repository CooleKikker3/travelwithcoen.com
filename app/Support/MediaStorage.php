<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where uploaded photos and videos live: the local "public" disk in development,
 * Cloudflare R2 (S3-compatible) when R2_ENABLED=true in .env.
 */
class MediaStorage
{
    public static function diskName(): string
    {
        return config('travel.media_disk');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * Attach metadata to a stored file (only on R2/S3; the local disk has no metadata). Done with an
     * in-place copy inside the bucket, so large videos are not downloaded again. Also sets a long
     * browser cache, as uploaded files never change under the same name.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public static function describe(string $path, array $metadata): void
    {
        $disk = self::disk();
        $config = config('filesystems.disks.'.self::diskName());

        if (($config['driver'] ?? null) !== 's3' || ! method_exists($disk, 'getClient')) {
            return;
        }

        rescue(fn () => $disk->getClient()->copyObject([
            'Bucket' => $config['bucket'],
            'Key' => $path,
            'CopySource' => $config['bucket'].'/'.str_replace('%2F', '/', rawurlencode($path)),
            'MetadataDirective' => 'REPLACE',
            // S3 metadata must be ASCII and is limited in size.
            'Metadata' => collect($metadata)
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => Str::limit(Str::ascii((string) $value), 200, ''))
                ->all(),
            'ContentType' => $disk->mimeType($path) ?: 'application/octet-stream',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]));
    }

    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * Run $edit on a local copy of the file and store the result back. On a local disk the
     * file is edited in place; on R2 it is downloaded to a temp file and uploaded again.
     *
     * @template T
     *
     * @param  callable(string $localPath): T  $edit
     * @return T
     */
    public static function editLocally(string $path, callable $edit): mixed
    {
        $disk = self::disk();

        if (config('filesystems.disks.'.self::diskName().'.driver') === 'local') {
            return $edit($disk->path($path));
        }

        $temp = tempnam(sys_get_temp_dir(), 'media').'.'.pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($temp, $disk->readStream($path));

        try {
            $result = $edit($temp);
            $disk->put($path, fopen($temp, 'r'));

            return $result;
        } finally {
            @unlink($temp);
        }
    }
}
