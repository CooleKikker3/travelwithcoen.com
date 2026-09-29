<?php

return [

    // Supported content/UI locales. The first one is the default and has no URL prefix.
    'locales' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    // Start and destination of the walk (shown on the home map; the rough plan runs between them).
    'start' => ['name' => 'Lisse', 'country' => 'NL', 'lat' => 52.2575, 'lng' => 4.5570],
    // Around the first point of the first planned route piece (home): hidden on the website (route and GPS points).
    'privacy_radius_m' => (int) env('PRIVACY_RADIUS_M', 1000),
    'destination' => ['name' => 'Hanoi', 'country' => 'VN', 'lat' => 21.0285, 'lng' => 105.8542],

    // IP addresses that see the full site while it is closed (Settings > Website access), comma separated.
    'preview_ips' => array_filter(array_map('trim', explode(',', (string) env('PREVIEW_IPS', '127.0.0.1,::1')))),

    // Behind Cloudflare or another proxy: "*" or a comma-separated list. Makes request()->ip() the visitor's IP.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Shared secret for POST /api/tracking (phone app / device). Empty = ingest disabled.
    'tracking_ingest_token' => env('TRACKING_INGEST_TOKEN'),

    // Garmin inReach via MapShare (see DEPLOYMENT.md): feed address and, if set on MapShare, its password.
    'garmin' => [
        'mapshare_url' => env('GARMIN_MAPSHARE_URL'),
        'mapshare_password' => env('GARMIN_MAPSHARE_PASSWORD'),
    ],

    // Used to strip metadata (incl. GPS) from uploaded videos. Optional.
    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

    // YouTube channel whose videos are added to the gallery (URL, @handle or channel id).
    'youtube_channel' => env('YOUTUBE_CHANNEL'),

    // Where uploaded photos/videos are stored: Cloudflare R2 when R2_ENABLED=true (see config/filesystems.php), else the local disk.
    'media_disk' => env('R2_ENABLED', false) ? 'r2' : 'public',

];
