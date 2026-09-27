<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
