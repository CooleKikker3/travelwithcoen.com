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
    <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/brand/icon-512.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:500,700,800|caveat:600,700|nunito:400,400i,600,700,800&display=swap" rel="stylesheet">
    <script>document.documentElement.classList.add('js')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.skip') }}</a>

    {{-- Floating navigation: transparent over the dark top of every page, a blurred green pill once scrolled (js in app.js). --}}
    <header class="site-nav fixed inset-x-0 top-0 z-[1100] px-3 pt-3 text-sage-100 sm:px-5" data-nav>
        <div class="site-nav__bar mx-auto flex max-w-6xl items-center justify-between gap-4 rounded-full py-2 pr-2 pl-4 sm:pl-5">
            <a href="{{ lroute('home') }}" class="group flex items-center gap-3">
                <svg class="size-10 shrink-0 text-fern-300 transition duration-500 group-hover:-rotate-12 group-hover:scale-110" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                    <path d="M3 28 L13 12 L19 21 L23 16 L33 28 Z" fill="currentColor" opacity=".25"/>
                    <path d="M3 28 L13 12 L19 21 L23 16 L33 28" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M6 33 C12 30, 16 31, 20 29 S29 26, 32 23" stroke="#c2c07a" stroke-width="1.6" stroke-dasharray="2 2.5" stroke-linecap="round"/>
                </svg>
                <span class="leading-none">
                    <span class="block font-display text-xl font-extrabold tracking-tight text-white">{{ __('site.name') }}</span>
                    {{-- Start and finish joined by a dotted trail that walks on hover. --}}
                    <span class="mt-1 flex items-center gap-1.5 text-[10px] font-extrabold tracking-[.2em] text-fern-300 uppercase">Lisse<span class="logo-trail" aria-hidden="true"></span>Hanoi</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                @foreach ($nav as $name => $label)
                    <a href="{{ lroute($name) }}" @class(['nav-link', 'is-active' => $isActive($name)]) @if ($isActive($name)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <div class="flex rounded-full bg-forest-950/40 p-1 text-xs font-extrabold" role="group" aria-label="{{ __('site.language') }}">
                    @foreach ($locales as $l => $label)
                        @if ($l === $locale)
                            <span class="rounded-full bg-fern-300 px-2.5 py-1 text-forest-950" aria-current="true" title="{{ $label }}">{{ strtoupper($l) }}</span>
                        @else
                            <a href="{{ ($alternates[$l] ?? lroute('home', [], $l)).'?lang='.$l }}" hreflang="{{ $l }}" lang="{{ $l }}" class="rounded-full px-2.5 py-1 text-sage-200 transition hover:-rotate-6 hover:text-white" title="{{ $label }}">{{ strtoupper($l) }}</a>
                        @endif
                    @endforeach
                </div>

                <details class="nav-menu lg:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full bg-fern-300 text-forest-950 transition hover:rotate-6" aria-label="{{ __('site.menu') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M4 7c3-2 5 2 8 0s5-2 8 0M4 12c3-2 5 2 8 0s5-2 8 0M4 17c3-2 5 2 8 0s5-2 8 0"/></svg>
                    </summary>
                    <nav class="topo absolute inset-x-3 top-full mt-2 rounded-3xl bg-forest-900/95 p-4 shadow-2xl ring-1 ring-white/10 backdrop-blur" aria-label="Main">
                        @foreach ($nav as $name => $label)
                            <a href="{{ lroute($name) }}" @class(['nav-menu__link block rounded-2xl px-4 py-2.5 font-display text-2xl font-bold', 'bg-forest-700 text-white' => $isActive($name), 'text-sage-100 hover:bg-forest-800' => ! $isActive($name)]) style="--i: {{ $loop->index }}">{{ $label }}</a>
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
