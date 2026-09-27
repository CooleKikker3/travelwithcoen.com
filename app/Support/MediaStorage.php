<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

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
