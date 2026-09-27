@php
    use App\Enums\JourneyPhase;

    $preparing = $phase === JourneyPhase::Preparation;
    $km = fn ($value) => __('site.stats.km', ['km' => \Illuminate\Support\Number::format($value ?? 0, maxPrecision: 0, locale: app()->getLocale())]);

    // Before departure: the plan. From departure on: the real numbers.
    $facts = $preparing ? [
        [__('site.home.facts.departure'), ucfirst(__('site.departure'))],
        [__('site.home.facts.duration'), __('site.home.facts.duration_value')],
        [__('site.home.facts.daily'), __('site.home.facts.daily_value')],
        [__('site.home.facts.pack'), __('site.home.facts.pack_value')],
    ] : [
        [__('site.statistics.distance'), $km($stats['distance_km'])],
        [__('site.statistics.days'), $stats['days']],
        [__('site.statistics.countries'), $stats['countries']],
        [__('site.statistics.tent_nights'), $stats['tent_nights']],
    ];

    [$title, $lead] = match ($phase) {
        JourneyPhase::Journey => [__('site.home.title_journey'), __('site.home.lead_journey')],
        JourneyPhase::Archive => [__('site.home.title_archive'), __('site.home.lead_archive')],
        default => [__('site.home.title'), __('site.home.lead', ['departure' => __('site.departure')])],
    };
@endphp
<x-layouts.app>
    <section class="topo relative overflow-hidden bg-forest-900 text-sage-100">
        <div class="container-page grid gap-12 py-16 sm:py-24 lg:grid-cols-[1.3fr_1fr] lg:items-center">
            <div>
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-forest-700/70 px-3 py-1 text-sm font-semibold text-fern-300">
                    <span class="size-2 rounded-full bg-olive-300"></span>
                    {{ __('site.status.label') }}: {{ $phase->getLabel() }}
                </div>
                <h1 class="text-4xl leading-[1.05] font-semibold text-white sm:text-6xl">{{ $title }}</h1>
                <p class="mt-6 max-w-xl text-lg text-sage-200">{{ $lead }}</p>
                @if (! $preparing && $lastLocation)
                    <p class="mt-4 text-sm text-fern-300">
                        {{ __('site.home.latest_location') }}:
                        <strong class="text-white">{{ $lastLocation->country?->translate('name') ?? '—' }}</strong>,
                        {{ $lastLocation->recorded_at->translatedFormat('j F Y') }}
                    </p>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($preparing)
                        <a href="{{ lroute('preparation.index') }}" class="btn-primary">{{ __('site.home.cta_preparation') }} →</a>
                    @else
                        <a href="{{ lroute('journey') }}" class="btn-primary">{{ __('site.journey.title') }} →</a>
                    @endif
                    <a href="{{ lroute('about') }}" class="btn-ghost">{{ __('site.home.cta_about') }}</a>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3">
                @foreach ($facts as [$label, $value])
                    <div class="rounded-2xl bg-forest-800/80 p-5 ring-1 ring-forest-700">
                        <dt class="text-xs font-semibold tracking-wide text-fern-300 uppercase">{{ $label }}</dt>
                        <dd class="mt-1 font-display text-xl text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    @if ($preparing)
        {{-- Rough direction: deliberately static text, not route data. --}}
        <section class="border-b border-sage-200 bg-sage-100">
            <div class="container-page py-10">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-2xl font-semibold">{{ __('site.home.direction_title') }}</h2>
                    <span class="badge bg-sand-100 text-bark-700">{{ __('site.status.not_final') }}</span>
                </div>
                <ol class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-3 text-sm font-semibold">
                    @foreach (preg_split('/\R+/', trim(__('site.home.direction'))) as $stop)
                        <li class="flex items-center gap-2">
                            <span @class(['rounded-full px-3 py-1.5', 'bg-forest-800 text-white' => $loop->first || $loop->last, 'bg-white text-forest-700 ring-1 ring-sage-200' => ! ($loop->first || $loop->last)])>{{ $stop }}</span>
                            @unless ($loop->last)
                                <span class="text-moss-400" aria-hidden="true">→</span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 max-w-3xl text-sm text-moss-600">{{ __('site.home.direction_note') }}</p>
            </div>
        </section>
    @endif

    <section class="container-page mt-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-3xl font-semibold">{{ __('site.home.map_title') }}</h2>
            <a href="{{ lroute('journey') }}" class="font-semibold text-moss-600 hover:text-forest-700">{{ __('site.journey.title') }} →</a>
        </div>
        <x-route-map :geojson="$overview" class="mt-6 h-80 sm:h-[26rem]" />
    </section>

    @php
        $sections = [
            ['articles' => $diary, 'title' => __('site.home.diary_title'), 'link' => lroute('diary.index'), 'more' => __('site.home.all_diary'), 'lead' => null],
            ['articles' => $preparation, 'title' => __('site.home.preparation_title'), 'link' => lroute('preparation.index'), 'more' => __('site.home.all_preparation'), 'lead' => __('site.home.preparation_lead')],
        ];
        if ($preparing) {
            $sections = array_reverse($sections);
        }
    @endphp

    @foreach ($sections as $section)
        @if ($section['articles']->isNotEmpty() || ($preparing && $loop->first))
            <section class="container-page mt-16">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-semibold">{{ $section['title'] }}</h2>
                        @if ($section['lead'])
                            <p class="mt-2 max-w-2xl text-forest-700">{{ $section['lead'] }}</p>
                        @endif
                    </div>
                    <a href="{{ $section['link'] }}" class="font-semibold text-moss-600 hover:text-forest-700">{{ $section['more'] }} →</a>
                </div>
                @if ($section['articles']->isEmpty())
                    <p class="mt-8 rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.home.empty') }}</p>
                @else
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($section['articles'] as $article)
                            <x-article-card :article="$article" />
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    @endforeach

    @if ($gallery->isNotEmpty())
        <section class="container-page mt-16">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl font-semibold">{{ __('site.home.photos_title') }}</h2>
                <a href="{{ lroute('gallery') }}" class="font-semibold text-moss-600 hover:text-forest-700">{{ __('site.media.title') }} →</a>
            </div>
            <x-media-grid :items="$gallery" />
        </section>
    @endif
</x-layouts.app>
