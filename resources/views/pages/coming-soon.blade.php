<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('site.name') }} — {{ __('site.coming_soon.title') }}</title>
    <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:500,700,800|caveat:600,700|nunito:400,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
{{-- Placeholder while the website is closed (ComingSoon middleware). --}}
<body class="topo flex min-h-screen items-center bg-forest-900 text-sage-100">
    <main class="container-page py-16 text-center">
        <svg class="rise mx-auto size-20 text-fern-300" viewBox="0 0 36 36" fill="none" aria-hidden="true">
            <path d="M3 28 L13 12 L19 21 L23 16 L33 28 Z" fill="currentColor" opacity=".25"/>
            <path d="M3 28 L13 12 L19 21 L23 16 L33 28" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M6 33 C12 30, 16 31, 20 29 S29 26, 32 23" stroke="#c2c07a" stroke-width="1.6" stroke-dasharray="2 2.5" stroke-linecap="round"/>
        </svg>
        <p class="rise mt-6 font-display text-xl font-extrabold text-white" style="--d: .1s">{{ __('site.name') }}</p>
        <p class="rise mt-1 flex items-center justify-center gap-2 text-xs font-extrabold tracking-[.2em] text-fern-300 uppercase" style="--d: .15s">Lisse<span class="logo-trail" aria-hidden="true"></span>Hanoi</p>
        <h1 class="rise mx-auto mt-10 max-w-3xl text-5xl leading-[0.95] font-extrabold text-white sm:text-7xl" style="--d: .25s">{{ __('site.coming_soon.title') }}</h1>
        <p class="rise mt-4 -rotate-2 font-hand text-3xl text-olive-300" style="--d: .45s">{{ __('site.coming_soon.hand') }}</p>
        <p class="rise mx-auto mt-6 max-w-xl text-lg text-sage-200" style="--d: .6s">{{ __('site.coming_soon.lead') }}</p>
    </main>
</body>
</html>
