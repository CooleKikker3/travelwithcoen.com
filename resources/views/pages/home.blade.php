@php
    use App\Enums\JourneyPhase;

    $preparing = $phase === JourneyPhase::Preparation;

    [$title, $lead] = match ($phase) {
        JourneyPhase::Journey => [__('site.home.title_journey'), __('site.home.lead_journey')],
        JourneyPhase::Archive => [__('site.home.title_archive'), __('site.home.lead_archive')],
        default => [__('site.home.title'), __('site.home.lead', ['departure' => __('site.departure')])],
    };
@endphp
<x-layouts.app>
    {{-- Hero: the photo chosen in Settings, with the green wave pattern over a gradient; without a photo just the pattern. --}}
    <section class="wave-bottom relative isolate overflow-hidden bg-forest-900 text-sage-100">
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="drift absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-forest-950/90 via-forest-900/60 to-forest-900/10"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950/70 to-transparent to-40%"></div>
        @endif
        <div class="topo absolute inset-0 -z-10" aria-hidden="true"></div>

        <div @class(['container-page flex flex-col justify-center pt-12 pb-24 sm:pt-16 sm:pb-32', 'min-h-[80vh]' => $heroImage])>
            <div class="max-w-2xl">
                <h1 class="rise text-5xl leading-[0.95] font-extrabold text-white drop-shadow sm:text-7xl" style="--d: .1s">{{ $title }}</h1>
                {{-- Handwritten note with an arrow that draws itself. --}}
                <p class="rise mt-3 flex items-center gap-2 font-hand text-3xl text-olive-300 -rotate-2" style="--d: .5s">
                    <svg class="h-8 w-14 shrink-0" viewBox="0 0 56 32" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path class="draw" pathLength="1" d="M2 4c10 18 26 22 44 16" style="--d: .9s"/><path class="draw" pathLength="1" d="M38.3 17.7 46 20l-4.8 6.5" style="--d: 1.9s"/></g></svg>
                    {{ __('site.home.hand_note') }}
                </p>
                <p class="rise mt-6 max-w-xl text-lg text-sage-100" style="--d: .25s">{{ $lead }}</p>
                @if (! $preparing && $lastLocation)
                    <p class="mt-4 text-sm text-fern-300">
                        {{ __('site.home.latest_location') }}:
                        <strong class="text-white">{{ $lastLocation->country?->translate('name') ?? '—' }}</strong>,
                        {{ $lastLocation->recorded_at->translatedFormat('j F Y') }}
                    </p>
                @endif
                <div class="rise mt-8 flex flex-wrap gap-3" style="--d: .4s">
                    @if ($preparing)
                        <a href="{{ stories_url('preparation') }}" class="btn-primary">{{ __('site.home.cta_preparation') }} →</a>
                    @else
                        <a href="{{ lroute('journey') }}" class="btn-primary">{{ __('site.journey.title') }} →</a>
                    @endif
                    <a href="{{ lroute('about') }}" class="btn-ghost">{{ __('site.home.cta_about') }}</a>
                </div>

            </div>
        </div>
    </section>

    @if ($preparing)
        {{-- Rough direction: deliberately static text, not route data. --}}
        <section class="relative -mt-10 pt-10">
            <div class="container-page py-10">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-3xl font-bold" data-reveal>{{ __('site.home.direction_title') }}</h2>
                    <span class="badge bg-sand-100 text-bark-700">{{ __('site.status.not_final') }}</span>
                </div>
                <ol class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-4">
                    @foreach (preg_split('/\R+/', trim(__('site.home.direction'))) as $stop)
                        <li class="flex items-center gap-3" data-reveal style="--i: {{ $loop->index }}; --tilt: {{ [-3, 2, -1, 3, -2][$loop->index % 5] }}deg">
                            {{-- Stops that match a country on the site link to its page. --}}
                            @if ($link = $countryLinks[mb_strtolower(trim($stop))] ?? null)
                                <a href="{{ $link }}" @class(['stamp hover:bg-fern-300 hover:text-forest-950', 'bg-forest-800 text-fern-300' => $loop->first || $loop->last, 'bg-white text-moss-600' => ! ($loop->first || $loop->last)])>{{ $stop }}</a>
                            @else
                                <span @class(['stamp', 'bg-forest-800 text-fern-300' => $loop->first || $loop->last, 'bg-white text-moss-600' => ! ($loop->first || $loop->last)])>{{ $stop }}</span>
                            @endif
                            @unless ($loop->last)
                                <svg class="h-3 w-8 text-moss-400" viewBox="0 0 32 12" fill="none" aria-hidden="true"><path d="M1 8c8-6 16-6 24-2" stroke="currentColor" stroke-width="2" stroke-dasharray="3 4" stroke-linecap="round"/><path d="M24 2l5 4-6 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
            <h2 class="text-3xl font-bold sm:text-4xl" data-reveal>{{ __('site.home.map_title') }}</h2>
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
                        <h2 class="text-3xl font-bold sm:text-4xl" data-reveal>{{ $section['title'] }}</h2>
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
                            <x-article-card :article="$article" :index="$loop->index" />
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    @endforeach

    @if ($gallery->isNotEmpty())
        <section class="container-page mt-16">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl font-bold sm:text-4xl" data-reveal>{{ __('site.home.photos_title') }}</h2>
                <a href="{{ lroute('gallery') }}" class="font-semibold text-moss-600 hover:text-forest-700">{{ __('site.media.title') }} →</a>
            </div>
            <x-gallery-wall :items="$gallery" />
        </section>
    @endif
</x-layouts.app>
