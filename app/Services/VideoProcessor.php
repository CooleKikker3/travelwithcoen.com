<?php

namespace App\Services;

use App\Support\MediaStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Phone videos can contain the GPS location in their metadata. With ffmpeg installed
 * (FFMPEG_PATH) that metadata is removed without re-encoding; without it a warning is logged.
 */
class VideoProcessor
{
    public function stripMetadata(string $path): void
    {
        $ffmpeg = config('travel.ffmpeg_path');

        // Check first, so a large video is not downloaded from R2 for nothing.
        if (! rescue(fn () => Process::run([$ffmpeg, '-version'])->successful(), false, report: false)) {
            Log::warning('Video metadata (possibly including GPS) not removed: ffmpeg not available.', ['path' => $path]);

            return;
        }

        MediaStorage::editLocally($path, function (string $file) use ($ffmpeg, $path) {
            $temp = $file.'.clean.'.pathinfo($file, PATHINFO_EXTENSION);
            $result = Process::timeout(600)->run([$ffmpeg, '-y', '-i', $file, '-map_metadata', '-1', '-map', '0', '-c', 'copy', $temp]);

            if ($result->successful() && is_file($temp)) {
                rename($temp, $file);
            } else {
                @unlink($temp);
                Log::warning('Video metadata could not be removed.', ['path' => $path, 'error' => $result->errorOutput()]);
            }
        });
    }
}
