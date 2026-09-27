@php
    $km = fn (float $value) => __('site.stats.km', ['km' => \Illuminate\Support\Number::format($value, maxPrecision: 0, locale: app()->getLocale())]);
@endphp
<x-layouts.app :title="__('site.journey.title')">
    <x-page-header :title="__('site.journey.title')" :lead="__('site.journey.lead')">
        <dl class="mt-8 flex flex-wrap gap-3">
            @foreach ([
                [__('site.stats.countries'), $countries->count()],
                [__('site.statistics.countries'), $totals['countries']],
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

        <ol class="relative mt-8 space-y-8 border-l-2 border-dashed border-moss-400 pl-6 sm:pl-10">
            {{-- First stop on the timeline: the preparation, before the first country. --}}
            <li class="relative">
                <span class="absolute top-7 -left-[33px] size-4 rounded-full border-4 border-mist-50 bg-olive-500 sm:-left-[49px]" aria-hidden="true"></span>
                <article class="topo rounded-2xl bg-forest-800 p-6 text-sage-100">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-display text-2xl font-semibold text-white">
                            <a href="{{ stories_url('preparation') }}" class="hover:text-fern-300">{{ __('site.preparation.title') }}</a>
                        </h3>
                        <span class="badge bg-forest-700 text-fern-300">{{ trans_choice('site.journey.stories', $preparationCount) }}</span>
                    </div>
                    <p class="mt-2 max-w-2xl text-sage-200">{{ __('site.preparation.lead') }}</p>
                    @if ($preparation->isNotEmpty())
                        <ul class="mt-4 space-y-1">
                            @foreach ($preparation as $story)
                                <li><a href="{{ $story->url() }}" class="font-semibold text-fern-300 hover:text-white">{{ $story->translate('title') }} →</a></li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            </li>

            @if ($countries->isEmpty())
                <li><p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.journey.empty') }}</p></li>
            @endif
                @foreach ($countries as $country)
                    @php(['planned' => $planned, 'actual' => $actual] = $distances[$country->id])
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
    </section>

    {{-- All stories (preparation and on the road), filterable by type and tag. --}}
    <section id="stories" class="container-page mt-20 scroll-mt-6">
        <h2 class="text-3xl font-semibold">{{ __('site.journey.stories_title') }}</h2>

        <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('site.journey.stories_title') }}">
            @php($chip = fn (bool $active) => $active ? 'bg-forest-800 text-white' : 'bg-sage-100 text-forest-700 hover:bg-sage-200')
            <a href="{{ stories_url() }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $chip(! $type && ! $tag) }}">{{ __('site.articles.all_tags') }}</a>
            @foreach (\App\Enums\ArticleType::cases() as $case)
                <a href="{{ stories_url($case->value) }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $chip($type === $case && ! $tag) }}">{{ $case->getLabel() }}</a>
            @endforeach
            @foreach ($tags as $t)
                <a href="{{ stories_url($type?->value, $t) }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $chip($tag === $t) }}">#{{ $t }}</a>
            @endforeach
        </nav>

        @if ($articles->isEmpty())
            <p class="mt-8 rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.articles.empty') }}</p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </section>
</x-layouts.app>
