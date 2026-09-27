@php
    $km = fn (float $value) => __('site.stats.km', ['km' => \Illuminate\Support\Number::format($value, maxPrecision: 0, locale: app()->getLocale())]);
@endphp
<x-layouts.app :title="__('site.journey.title')">
    <x-page-header :title="__('site.journey.title')" :lead="__('site.journey.lead')">
        <dl class="mt-8 flex flex-wrap gap-3">
            @foreach ([
                [__('site.stats.countries'), $countries->count()],
                [__('site.stats.planned'), $km($totals['planned'])],
                [__('site.stats.walked'), $km($totals['actual'])],
            ] as [$label, $value])
                <div class="rounded-2xl bg-forest-700/70 px-4 py-3">
                    <dt class="text-xs font-semibold tracking-wide text-fern-300 uppercase">{{ $label }}</dt>
                    <dd class="font-display text-xl text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-page-header>

    <section class="container-page mt-10">
        <h2 class="sr-only">{{ __('site.journey.overview') }}</h2>
        <x-route-map :geojson="$overview" class="h-[28rem] sm:h-[34rem]" />
        <p class="mt-3 text-sm text-moss-600">{{ __('site.map.planned_note') }}</p>
    </section>

    <section class="container-page mt-16">
        <h2 class="text-3xl font-semibold">{{ __('site.journey.timeline') }}</h2>

        @if ($countries->isEmpty())
            <p class="mt-6 rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.journey.empty') }}</p>
        @else
            <ol class="relative mt-8 space-y-8 border-l-2 border-dashed border-moss-400 pl-6 sm:pl-10">
                @foreach ($countries as $country)
                    @php
                        $planned = $country->routes->where('type', \App\Enums\RouteType::Planned)->sum('distance_km');
                        $actual = $country->routes->where('type', \App\Enums\RouteType::Actual)->sum('distance_km');
                    @endphp
                    <li class="relative">
                        <span class="absolute top-7 -left-[33px] size-4 rounded-full border-4 border-mist-50 bg-moss-500 sm:-left-[49px]" aria-hidden="true"></span>
                        <article class="grid overflow-hidden rounded-2xl bg-white ring-1 ring-sage-200 md:grid-cols-[1fr_1.2fr]">
                            <div class="flex flex-col gap-3 p-6">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-4xl" aria-hidden="true">{{ $country->flag() }}</span>
                                    <h3 class="font-display text-2xl font-semibold">
                                        <a href="{{ $country->url() }}" class="hover:text-moss-600">{{ $country->translate('name') }}</a>
                                    </h3>
                                </div>
                                <div><span class="badge">{{ $country->status->getLabel() }}</span></div>
                                @if ($intro = $country->translate('intro'))
                                    <p class="text-forest-700">{{ $intro }}</p>
                                @endif
                                <dl class="mt-auto flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                    <div><dt class="inline text-moss-600">{{ __('site.stats.planned') }}:</dt> <dd class="inline font-semibold">{{ $km($planned) }}</dd></div>
                                    <div><dt class="inline text-moss-600">{{ __('site.stats.walked') }}:</dt> <dd class="inline font-semibold">{{ $km($actual) }}</dd></div>
                                    <div class="text-moss-600">{{ trans_choice('site.journey.stories', $country->articles_count) }}</div>
                                </dl>
                            </div>
                            <a href="{{ $country->url() }}" class="block" tabindex="-1" aria-hidden="true">
                                <x-route-map :geojson="$maps[$country->id]" :interactive="false" :legend="false" class="h-56 rounded-none ring-0 md:h-full md:min-h-56" />
                            </a>
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</x-layouts.app>
