<?php

// Only the settings that differ from Livewire's defaults (merged with vendor/livewire/livewire/config/livewire.php).
return [

    // Gallery uploads include (phone) videos, so allow larger files and slower uploads.
    // The server's php.ini upload_max_filesize / post_max_size must allow this too.
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:512000'], // 500 MB
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'svg', 'mp4', 'mov', 'jpg', 'jpeg', 'webp', 'webm'],
        'max_upload_time' => 30,
        'cleanup' => true,
    ],

];
