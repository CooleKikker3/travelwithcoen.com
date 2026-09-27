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
        'journey_phase' => 'preparation',
        'budget_total_eur' => 35000,
        'budget_reserve_eur' => 5000,
    ];

    private const CACHE_KEY = 'settings';

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? self::DEFAULTS[$key] ?? null;
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
