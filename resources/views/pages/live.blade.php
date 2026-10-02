{{-- Live: the map (almost the whole screen) and when the last location came in; nothing else. --}}
<x-layouts.app :title="__('site.live.title')" solid-nav>
    <section class="topo-sand blend-footer bg-sand-100 px-3 pb-28 sm:px-5">
        <div class="relative mx-auto max-w-[110rem]">
            <x-route-map :geojson="$map" start-home :delay-days="$live ? 0 : $delayDays" class="h-[calc(100svh-6.5rem)] min-h-[26rem] rounded-3xl shadow-2xl shadow-forest-950/30 ring-4 ring-white" />

            {{-- Last location, floating over the map. --}}
            <div class="pointer-events-none absolute inset-x-3 top-3 z-[600] flex justify-center sm:inset-x-auto sm:left-16 sm:justify-start">
                <div class="pointer-events-auto max-w-md rounded-2xl bg-forest-950/85 px-5 py-4 text-sage-100 shadow-2xl ring-1 ring-white/10 backdrop-blur">
                    @if ($last)
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-fern-300 px-3 py-1 text-xs font-extrabold tracking-wide text-forest-950 uppercase">
                                <span class="relative flex size-2" aria-hidden="true">
                                    @if ($isLive)<span class="absolute inset-0 animate-ping rounded-full bg-forest-800"></span>@endif
                                    <span class="relative size-2 rounded-full bg-forest-800"></span>
                                </span>
                                {{ $live ? __('site.live.live_badge') : __('site.live.last_location') }}
                            </span>
                            <span class="font-display text-xl font-extrabold text-white">{{ ($live ? $last->received_at : $last->recorded_at)->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-sage-200">
                            {{ __('site.live.last_location') }}: {{ $last->recorded_at->timezone(config('app.display_timezone', 'Europe/Amsterdam'))->translatedFormat('j M, H:i') }}@if ($last->country) · <x-flag :country="$last->country" /> {{ $last->country->translate('name') }}@endif
                        </p>
                        @if ($live && ! $isLive)
                            <p class="mt-2 text-xs text-olive-300">{{ __('site.live.stale') }}</p>
                        @endif
                    @else
                        <p class="text-sm">{{ __('site.live.unavailable') }}</p>
                    @endif

                    @unless ($live)
                        <p class="mt-2 text-xs text-sage-200">{{ __('site.live.lead_public', ['days' => $delayDays]) }}</p>
                        <a href="{{ lroute('login') }}" class="mt-3 inline-block text-sm font-bold text-fern-300 hover:text-white">{{ __('site.live.family') }} <x-icons.arrow /></a>
                    @endunless
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
