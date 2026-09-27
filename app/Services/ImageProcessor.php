<?php

namespace App\Services;

use App\Support\MediaStorage;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Re-encodes uploaded photos with GD: fixes phone rotation, limits the size and,
 * by re-encoding, removes all EXIF metadata — including the GPS location.
 */
class ImageProcessor
{
    public const MAX_SIZE = 2400;

    /**
     * Processes the image on the media disk.
     *
     * @return array{taken_at: ?Carbon, width: ?int, height: ?int}
     */
    public function process(string $path): array
    {
        return MediaStorage::editLocally($path, fn (string $file) => $this->processFile($file));
    }

    /** @return array{taken_at: ?Carbon, width: ?int, height: ?int} */
    public function processFile(string $file): array
    {
        $type = @exif_imagetype($file);
        $exif = $type === IMAGETYPE_JPEG ? (@exif_read_data($file) ?: []) : [];

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => false,
        };

        if (! $image) {
            return ['taken_at' => null, 'width' => null, 'height' => null];
        }

        $image = match ($exif['Orientation'] ?? 1) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        $scale = min(1, self::MAX_SIZE / max(imagesx($image), imagesy($image)));
        if ($scale < 1) {
            $image = imagescale($image, (int) round(imagesx($image) * $scale));
        }

        match ($type) {
            IMAGETYPE_PNG => imagepng($image, $file, 7),
            IMAGETYPE_WEBP => imagewebp($image, $file, 85),
            default => imagejpeg($image, $file, 85),
        };

        try {
            $takenAt = isset($exif['DateTimeOriginal']) ? Carbon::createFromFormat('Y:m:d H:i:s', $exif['DateTimeOriginal']) : null;
        } catch (Throwable) {
            $takenAt = null;
        }

        return ['taken_at' => $takenAt, 'width' => imagesx($image), 'height' => imagesy($image)];
    }
}
