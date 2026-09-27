<x-layouts.app :title="__('site.live.title')">
    <x-page-header :title="__('site.live.title')" :lead="$live ? __('site.live.lead_private') : __('site.live.lead_public', ['days' => $delayDays])">
        @if ($last)
            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($isLive)
                    <span class="inline-flex items-center gap-2 rounded-full bg-fern-300 px-3 py-1 text-sm font-bold text-forest-950">
                        <span class="size-2 animate-pulse rounded-full bg-forest-800"></span>{{ __('site.live.live_badge') }}
                    </span>
                @endif
                <span class="text-sage-200">{{ __('site.live.ago', ['time' => $last->recorded_at->diffForHumans()]) }}</span>
            </div>
        @endif
    </x-page-header>

    <section class="container-page mt-10 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <x-route-map :geojson="$map" class="h-[28rem] sm:h-[36rem]" />

        <aside class="space-y-4">
            @if ($last)
                <dl class="space-y-3">
                    <x-stat :label="__('site.live.recorded')" :value="$last->recorded_at->translatedFormat('j M Y, H:i')" :hint="$last->country?->translate('name')" />
                    @if ($live)
                        <x-stat :label="__('site.live.received')" :value="$last->received_at->translatedFormat('j M Y, H:i')" />
                        <x-stat :label="__('site.live.public_from')" :value="$last->publicFrom()->translatedFormat('j M Y, H:i')" />
                    @endif
                </dl>
                @if ($live && ! $isLive)
                    <p class="rounded-2xl bg-sand-100 p-4 text-sm text-bark-700" role="note">{{ __('site.live.stale') }}</p>
                @endif
            @else
                <p class="rounded-2xl bg-sage-100 p-5 text-forest-700">{{ __('site.live.unavailable') }}</p>
            @endif

            @guest
                <a href="{{ lroute('login') }}" class="block rounded-2xl bg-forest-800 p-5 font-semibold text-sage-100 hover:bg-forest-700">{{ __('site.live.family') }} →</a>
            @endguest
        </aside>
    </section>
</x-layouts.app>
