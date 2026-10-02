<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Crawlers and link previews: not counted as visits (story clicks, article views). */
class Bots
{
    private const PATTERN = '/bot|crawl|spider|preview|facebookexternalhit|meta-external|slurp|curl|wget|python|headless/i';

    public static function is(Request $request): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $request->userAgent());
    }
}
