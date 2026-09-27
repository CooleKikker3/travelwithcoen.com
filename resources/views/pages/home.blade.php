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
    {{-- Hero: the photo chosen in Settings, with the green wave pattern over a gradient; without a photo just the pattern. --}}
    <section class="relative isolate overflow-hidden bg-forest-900 text-sage-100">
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-forest-950/90 via-forest-900/60 to-forest-900/10"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950/70 to-transparent to-40%"></div>
        @endif
        <div class="topo absolute inset-0 -z-10" aria-hidden="true"></div>

        <div @class(['container-page flex flex-col justify-end py-16 sm:py-24', 'min-h-[80vh]' => $heroImage])>
            <div class="max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-forest-700/70 px-3 py-1 text-sm font-semibold text-fern-300 backdrop-blur">
                    <span class="size-2 rounded-full bg-olive-300"></span>
                    {{ __('site.status.label') }}: {{ $phase->getLabel() }}
                </div>
                <h1 class="text-4xl leading-[1.05] font-semibold text-white drop-shadow sm:text-6xl">{{ $title }}</h1>
                <p class="mt-6 max-w-xl text-lg text-sage-100">{{ $lead }}</p>
                @if (! $preparing && $lastLocation)
                    <p class="mt-4 text-sm text-fern-300">
                        {{ __('site.home.latest_location') }}:
                        <strong class="text-white">{{ $lastLocation->country?->translate('name') ?? '—' }}</strong>,
                        {{ $lastLocation->recorded_at->translatedFormat('j F Y') }}
                    </p>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($preparing)
                        <a href="{{ stories_url('preparation') }}" class="btn-primary">{{ __('site.home.cta_preparation') }} →</a>
                    @else
                        <a href="{{ lroute('journey') }}" class="btn-primary">{{ __('site.journey.title') }} →</a>
                    @endif
                    <a href="{{ lroute('about') }}" class="btn-ghost">{{ __('site.home.cta_about') }}</a>
                </div>

                {{-- Key facts as one quiet line instead of statistic tiles. --}}
                <p class="mt-10 flex flex-wrap gap-x-2 gap-y-1 text-sm text-sage-200">
                    @foreach ($facts as [$label, $value])
                        <span>{{ $label }} <strong class="font-semibold text-white">{{ $value }}</strong></span>
                        @unless ($loop->last)<span class="text-fern-300/60" aria-hidden="true">·</span>@endunless
                    @endforeach
                </p>
            </div>
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
        <x-route-map :geojson="$overview" start-home class="mt-6 h-80 sm:h-[26rem]" />
    </section>

    @php
        $sections = [
            ['articles' => $diary, 'title' => __('site.home.diary_title'), 'link' => stories_url('diary'), 'more' => __('site.home.all_diary'), 'lead' => null],
            ['articles' => $preparation, 'title' => __('site.home.preparation_title'), 'link' => stories_url('preparation'), 'more' => __('site.home.all_preparation'), 'lead' => __('site.home.preparation_lead')],
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
            <x-gallery-wall :items="$gallery" />
        </section>
    @endif
</x-layouts.app>
