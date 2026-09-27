@props(['title' => null, 'description' => null, 'alternates' => null, 'canonical' => null])
@php
    $locale = app()->getLocale();
    $locales = config('travel.locales');
    $default = config('app.fallback_locale');

    // Same page in every locale. Pages with translated slugs pass their own $alternates.
    if ($alternates === null) {
        $route = request()->route();
        $name = $route?->getName() ? preg_replace('/^('.implode('|', array_keys($locales)).')\./', '', $route->getName()) : 'home';
        $params = $route ? array_intersect_key($route->parameters(), array_flip($route->parameterNames())) : [];
        $alternates = collect($locales)->mapWithKeys(fn ($label, $l) => [$l => lroute($name, $params, $l)])->all();
    }

    $canonical ??= $alternates[$locale] ?? $alternates[$default] ?? url()->current();
    $pageTitle = $title ? $title.' · '.__('site.name') : __('site.name').' — '.__('site.tagline');
    $description ??= __('site.description');

    $nav = [
        'home' => __('site.nav.home'),
        'journey' => __('site.nav.journey'),
        'live' => __('site.nav.live'),
        'gallery' => __('site.nav.media'),
        'equipment' => __('site.nav.equipment'),
        'about' => __('site.nav.about'),
    ];
    $current = preg_replace('/^[a-z]{2}\./', '', request()->route()?->getName() ?? '');
    $isActive = fn ($name) => $current === $name || str_starts_with($current, strtok($name, '.').'.') || ($name === 'journey' && preg_match('/^(countries|diary|preparation)\./', $current));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $l => $url)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ $url }}">
    @endforeach
    @isset($alternates[$default])
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[$default] }}">
    @endisset
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('site.name') }}">
    <meta property="og:title" content="{{ $title ?? __('site.tagline') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $locale === 'nl' ? 'nl_NL' : 'en_GB' }}">
    <meta name="theme-color" content="#142a1c">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,400i,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.skip') }}</a>

    <header class="bg-forest-900 text-sage-100">
        <div class="container-page flex items-center justify-between gap-4 py-4">
            <a href="{{ lroute('home') }}" class="group flex items-center gap-3">
                <svg class="size-9 shrink-0 text-fern-300" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                    <path d="M3 28 L13 12 L19 21 L23 16 L33 28 Z" fill="currentColor" opacity=".25"/>
                    <path d="M3 28 L13 12 L19 21 L23 16 L33 28" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M6 33 C12 30, 16 31, 20 29 S29 26, 32 23" stroke="#c2c07a" stroke-width="1.6" stroke-dasharray="2 2.5" stroke-linecap="round"/>
                </svg>
                <span class="leading-tight">
                    <span class="block font-display text-lg font-semibold text-white">{{ __('site.name') }}</span>
                    <span class="block text-xs text-fern-300">NL → Hanoi</span>
                </span>
            </a>

            <nav class="hidden items-center gap-0.5 xl:flex" aria-label="Main">
                @foreach ($nav as $name => $label)
                    <a href="{{ lroute($name) }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold transition', 'bg-forest-700 text-white' => $isActive($name), 'text-sage-200 hover:text-white' => ! $isActive($name)]) @if ($isActive($name)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <div class="flex rounded-full border border-forest-700 p-0.5 text-xs font-bold" role="group" aria-label="{{ __('site.language') }}">
                    @foreach ($locales as $l => $label)
                        @if ($l === $locale)
                            <span class="rounded-full bg-fern-300 px-2.5 py-1 text-forest-950" aria-current="true" title="{{ $label }}">{{ strtoupper($l) }}</span>
                        @else
                            <a href="{{ $alternates[$l] ?? lroute('home', [], $l) }}" hreflang="{{ $l }}" lang="{{ $l }}" class="rounded-full px-2.5 py-1 text-sage-200 hover:text-white" title="{{ $label }}">{{ strtoupper($l) }}</a>
                        @endif
                    @endforeach
                </div>

                <details class="relative xl:hidden">
                    <summary class="list-none cursor-pointer rounded-full border border-forest-700 px-3 py-1.5 text-sm font-semibold">{{ __('site.menu') }}</summary>
                    <nav class="absolute right-0 z-20 mt-2 w-52 rounded-xl bg-forest-800 p-2 shadow-xl" aria-label="Main">
                        @foreach ($nav as $name => $label)
                            <a href="{{ lroute($name) }}" @class(['block rounded-lg px-3 py-2 font-semibold', 'bg-forest-700 text-white' => $isActive($name), 'text-sage-200' => ! $isActive($name)])>{{ $label }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="topo mt-20 bg-forest-950 text-sage-200">
        <div class="container-page flex flex-col gap-4 py-10 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p><span class="font-display text-base text-white">{{ __('site.name') }}</span> — {{ __('site.footer.note') }}</p>
            <div class="flex flex-wrap items-center gap-4">
                @auth
                    <span>{{ __('site.login.logged_in_as', ['name' => auth()->user()->name]) }}</span>
                    <form method="POST" action="{{ lroute('logout') }}">@csrf<button class="font-semibold text-fern-300 hover:text-white">{{ __('site.login.logout') }}</button></form>
                @else
                    <a href="{{ lroute('login') }}" class="font-semibold text-fern-300 hover:text-white">{{ __('site.live.family') }}</a>
                @endauth
                <span class="text-fern-300">© {{ date('Y') }} Coen</span>
            </div>
        </div>
    </footer>
</body>
</html>
