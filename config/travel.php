<?php

return [

    // Supported content/UI locales. The first one is the default and has no URL prefix.
    'locales' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    // Start and destination of the walk (shown on the home map; the rough plan runs between them).
    'start' => ['name' => 'Lisse', 'country' => 'NL', 'lat' => 52.2575, 'lng' => 4.5570],
    'destination' => ['name' => 'Hanoi', 'country' => 'VN', 'lat' => 21.0285, 'lng' => 105.8542],

    // Shared secret for POST /api/tracking (phone app / device). Empty = ingest disabled.
    'tracking_ingest_token' => env('TRACKING_INGEST_TOKEN'),

    // Used to strip metadata (incl. GPS) from uploaded videos. Optional.
    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

    // YouTube channel whose videos are added to the gallery (URL, @handle or channel id).
    'youtube_channel' => env('YOUTUBE_CHANNEL'),

    // Where uploaded photos/videos are stored: Cloudflare R2 when R2_ENABLED=true (see config/filesystems.php), else the local disk.
    'media_disk' => env('R2_ENABLED', false) ? 'r2' : 'public',

];
