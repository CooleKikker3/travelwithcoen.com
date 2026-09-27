<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Cookie with the language the visitor chose with the language switch. */
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        // Language switch (?lang=nl): remember the choice and continue on the clean URL.
        $chosen = $request->query('lang');
        if (is_string($chosen) && array_key_exists($chosen, config('travel.locales'))) {
            return redirect($request->fullUrlWithoutQuery('lang'))
                ->withCookie(cookie()->forever(self::COOKIE, $chosen));
        }

        // Dutch visitors landing on the English home page go to the Dutch one, unless they chose English.
        if ($request->route()?->getName() === 'home' && $request->isMethod('GET')) {
            $preferred = $request->cookie(self::COOKIE) ?? ($this->prefersDutch($request) ? 'nl' : null);
            if ($preferred === 'nl') {
                return redirect(lroute('home', [], 'nl'));
            }
        }

        return $next($request);
    }

    /**
     * Location from Cloudflare (CF-IPCountry, once the site runs behind it), otherwise the browser language.
     * No IP lookups with third parties.
     */
    private function prefersDutch(Request $request): bool
    {
        return $request->header('CF-IPCountry') === 'NL'
            || $request->getPreferredLanguage(array_keys(config('travel.locales'))) === 'nl';
    }
}
