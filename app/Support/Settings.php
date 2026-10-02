<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Site settings editable in the CMS ("Settings" page), with safe defaults.
 */
class Settings
{
    public const DEFAULTS = [
        'public_tracking_delay_hours' => 336, // 14 days
        // Closed = visitors see a "coming soon" page; admins and PREVIEW_IPS (.env) see the site.
        'site_open' => true,
    ];

    private const CACHE_KEY = 'settings';

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /** Filled-in social media links, network => URL (footer and About page). */
    public static function socialLinks(): array
    {
        return array_filter([
            'facebook' => self::get('facebook_url'),
            'instagram' => self::get('instagram_url'),
            'youtube' => self::get('youtube_url'),
        ]);
    }

    public static function all(): array
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (QueryException) {
            $stored = []; // Table not migrated yet.
        }

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($value) => $value !== null));
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
