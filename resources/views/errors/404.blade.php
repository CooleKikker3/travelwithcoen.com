@php
    // No route matched, so the locale middleware did not run: take the language from the address.
    if (request()->segment(1) === 'nl') {
        app()->setLocale('nl');
    }
@endphp
{{-- Page not found: a sand-coloured travel-journal page with a stamp, flowing into the dark footer. --}}
<x-layouts.app :title="__('site.errors.not_found_title')" solid-nav>
    <section class="topo-sand blend-footer relative overflow-hidden bg-sand-100 text-forest-900">
        <div class="container-page flex min-h-[70vh] flex-col items-center justify-center pt-12 pb-40 text-center">
            {{-- Compass, bobbing a little: lost, but still looking. --}}
            <svg class="bob size-24" style="--tilt: -8deg" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                <circle cx="50" cy="50" r="46" fill="#fff" stroke="var(--color-olive-500)" stroke-width="2" stroke-dasharray="3 5"/>
                <circle cx="50" cy="50" r="34" stroke="var(--color-olive-300)" stroke-width="1.5"/>
                <path d="M50 14 58 50 50 86 42 50Z" fill="var(--color-forest-700)" opacity=".25"/>
                <path d="M50 14 58 50H42Z" fill="var(--color-moss-500)"/>
                <circle cx="50" cy="50" r="4" fill="var(--color-forest-800)"/>
            </svg>

            {{-- Passport stamp: upright display font, so the number sits exactly in the middle. --}}
            <p class="rise mt-8 inline-flex items-center justify-center rounded-2xl border-4 border-dashed px-8 py-3 font-display font-extrabold leading-none"
               style="--tilt: -5deg; transform: rotate(-5deg); font-size: clamp(3.5rem, 12vw, 6rem); color: var(--color-forest-700); border-color: var(--color-moss-500); background: rgb(255 255 255 / .7)">404</p>
            <h1 class="rise mt-10 text-4xl font-extrabold sm:text-6xl" style="--d: .1s">{{ __('site.errors.not_found_title') }}</h1>
            <p class="rise mt-3 -rotate-2 font-hand text-3xl text-moss-600" style="--d: .3s">{{ __('site.errors.not_found_hand') }}</p>
            <p class="rise mt-6 max-w-xl text-lg text-forest-700" style="--d: .45s">{{ __('site.errors.not_found_lead') }}</p>

            <div class="rise mt-10 flex flex-wrap justify-center gap-3" style="--d: .6s">
                <a href="{{ lroute('home') }}" class="btn-primary">{{ __('site.errors.back_home') }}</a>
                <a href="{{ lroute('journey') }}" class="btn-outline">{{ __('site.journey.title') }} <x-icons.arrow /></a>
            </div>
        </div>
    </section>
</x-layouts.app>
