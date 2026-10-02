<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Automatic Dutch → English translations (App\Services\GoogleTranslate): Cloud Translation API key.
    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_KEY'),
        // What the translations cost: linked from the "Vertalingen" page.
        'billing_url' => env('GOOGLE_BILLING_URL', 'https://console.cloud.google.com/billing/0114D4-D24B5E-64CFB7?project=travelwithcoen'),
    ],

    // Google Analytics 4 (measurement ID "G-..."). Empty = off. Only loaded after the visitor accepts the cookie banner.
    'google_analytics' => [
        'id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
