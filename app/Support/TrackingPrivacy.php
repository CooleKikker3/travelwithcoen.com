<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The single place that decides which location data someone may see.
 * Guests only get data older than the public delay (default 14 days); trusted viewers
 * and admins see everything. Always applied in queries, never in the browser.
 */
class TrackingPrivacy
{
    /** Latest data counts as "live" when it was recorded less than this long ago. */
    public const LIVE_MINUTES = 60;

    public static function delayHours(): int
    {
        return max(0, (int) Settings::get('public_tracking_delay_hours'));
    }

    public static function canSeeLive(?User $user): bool
    {
        return (bool) $user?->canSeeLiveTracking();
    }

    /** Newest moment the given user may see, or null for no limit. */
    public static function cutoff(?User $user): ?Carbon
    {
        return self::canSeeLive($user) ? null : now()->subHours(self::delayHours());
    }

    /** Cutoff for the public, regardless of who is logged in (public API, sitemap). */
    public static function publicCutoff(): Carbon
    {
        return now()->subHours(self::delayHours());
    }
}
