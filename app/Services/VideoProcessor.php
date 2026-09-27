<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Phone videos can contain the GPS location in their metadata. With ffmpeg installed
 * (FFMPEG_PATH) that metadata is removed without re-encoding; without it a warning is logged.
 */
class VideoProcessor
{
    public function stripMetadata(string $path, string $disk = 'public'): ?Carbon
    {
        $file = Storage::disk($disk)->path($path);
        $temp = $file.'.clean.'.pathinfo($file, PATHINFO_EXTENSION);

        $result = rescue(fn () => Process::timeout(300)->run([
            config('travel.ffmpeg_path'), '-y', '-i', $file, '-map_metadata', '-1', '-map', '0', '-c', 'copy', $temp,
        ]), report: false);

        if ($result?->successful() && is_file($temp)) {
            rename($temp, $file);
        } else {
            @unlink($temp);
            Log::warning('Video metadata (possibly including GPS) could not be removed: ffmpeg not available.', ['path' => $path]);
        }

        return null;
    }
}
