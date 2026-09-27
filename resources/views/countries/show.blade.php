@php
    $km = fn (float $value) => __('site.stats.km', ['km' => \Illuminate\Support\Number::format($value, maxPrecision: 0, locale: app()->getLocale())]);
@endphp
<x-layouts.app :title="$country->translate('name')" :description="$country->translate('intro')" :alternates="$alternates">
    <x-page-header :country="$country" :title="$country->translate('name')" :lead="$country->translate('intro')">
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <span class="badge">{{ $country->status->getLabel() }}</span>
            <span class="text-sm text-sage-200">{{ __('site.stats.planned') }}: <strong class="text-white">{{ $km($distances['planned']) }}</strong></span>
            <span class="text-sm text-sage-200">{{ __('site.stats.walked') }}: <strong class="text-white">{{ $km($distances['actual']) }}</strong></span>
        </div>
    </x-page-header>

    <section class="container-page mt-10">
        <x-route-map :geojson="$map" :start-home="$country->iso_code === 'NL'" class="h-[26rem] sm:h-[32rem]" />
        <p class="mt-3 text-sm text-moss-600">{{ __('site.map.planned_note') }}</p>
    </section>

    @if ($stats['days'])
        <dl class="container-page mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-stat :label="__('site.statistics.days')" :value="$stats['days']" :hint="$stats['first_day']->translatedFormat('j M').' – '.$stats['last_day']->translatedFormat('j M Y')" />
            <x-stat :label="__('site.statistics.walking_days')" :value="$stats['walking_days']" />
            <x-stat :label="__('site.statistics.rest_days')" :value="$stats['rest_days']" />
            <x-stat :label="__('site.statistics.average')" :value="$km($stats['average_km'] ?? 0)" />
            <x-stat :label="__('site.statistics.hours')" :value="round($stats['walking_hours'])" />
        </dl>
    @endif

    <div class="container-page mt-12 max-w-4xl">
        <h2 class="text-2xl font-semibold">{{ __('site.country.story') }}</h2>
        @if ($story = $country->translate('story'))
            <div class="prose prose-lg mt-4 max-w-none prose-headings:font-display">{!! $story !!}</div>
        @else
            <p class="mt-4 text-moss-600">{{ __('site.country.no_story') }}</p>
        @endif
    </div>

    @if ($articles->isNotEmpty())
        <section class="container-page mt-14">
            <h2 class="text-2xl font-semibold">{{ __('site.country.articles', ['country' => $country->translate('name')]) }}</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" :index="$loop->index" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($gallery->isNotEmpty())
        <section class="container-page mt-14">
            <h2 class="mb-6 text-2xl font-semibold">{{ __('site.media.title') }}</h2>
            <x-gallery-wall :items="$gallery" />
        </section>
    @endif

    <div class="container-page mt-12">
        <a href="{{ lroute('journey') }}" class="font-semibold text-moss-600 hover:text-forest-700">← {{ __('site.country.back') }}</a>
    </div>
</x-layouts.app>
