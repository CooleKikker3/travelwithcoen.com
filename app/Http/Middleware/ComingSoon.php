<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the website is closed (Settings > Website access), visitors get a "coming soon" page.
 * Admins and the preview IP addresses (PREVIEW_IPS in .env) see the real site. The CMS, API and assets are not affected.
 */
class ComingSoon
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::get('site_open') || self::canPreview($request)) {
            return $next($request);
        }

        // 200 (a 503 shows up as an error in the server monitor) with noindex: search engines keep the placeholder out of their index.
        return response()->view('pages.coming-soon')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public static function canPreview(Request $request): bool
    {
        return (bool) $request->user()?->isAdmin() || in_array($request->ip(), config('travel.preview_ips'), true);
    }
}
