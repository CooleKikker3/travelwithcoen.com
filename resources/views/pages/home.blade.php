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
    @if ($heroImage)
        {{-- The hero photo is the largest element: start downloading it right away. --}}
        <x-slot:head>
            <link rel="preload" as="image" href="{{ $heroImage }}" @if ($heroSrcset) imagesrcset="{{ $heroSrcset }}" imagesizes="100vw" @endif fetchpriority="high">
        </x-slot:head>
    @endif
    {{-- Hero: the photo chosen in Settings, with the green wave pattern over a gradient; without a photo just the pattern. --}}
    <section class="wave-bottom relative isolate overflow-hidden bg-forest-900 text-sage-100">
        @if ($heroImage)
            <img src="{{ $heroImage }}" @if ($heroSrcset) srcset="{{ $heroSrcset }}" sizes="100vw" @endif alt="" class="drift absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
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
        {{-- The rough plan: a sand-coloured "map page" with passport stamps; deliberately static text, not route data. --}}
        <section class="container-page relative mt-16">
            <div class="topo-sand relative overflow-hidden rounded-[2rem] bg-sand-100 px-6 py-10 shadow-xl shadow-forest-900/10 ring-1 ring-olive-300/40 sm:px-12 sm:py-14" data-reveal>
                {{-- Compass in the corner. --}}
                <svg class="pointer-events-none absolute -top-10 -right-10 size-48 rotate-[40deg] text-olive-300/60 sm:size-64" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                    <circle cx="50" cy="50" r="46" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 4"/>
                    <circle cx="50" cy="50" r="34" stroke="currentColor" stroke-width="1"/>
                    <path d="M50 10 57 50 50 90 43 50Z" fill="currentColor" opacity=".5"/>
                    <path d="M10 50 50 44 90 50 50 56Z" fill="currentColor" opacity=".3"/>
                    <path d="M50 10 57 50H43Z" fill="var(--color-bark-700)" opacity=".35"/>
                </svg>

                <div class="relative flex flex-wrap items-end gap-x-4 gap-y-1">
                    <h2 class="text-4xl font-extrabold sm:text-5xl">{{ __('site.home.direction_title') }}</h2>
                    <span class="-rotate-3 pb-1 font-hand text-3xl text-moss-600">{{ __('site.home.direction_hand') }}</span>
                </div>

                <ol class="relative mt-8 flex flex-wrap items-center gap-x-3 gap-y-8">
                    @foreach (preg_split('/\R+/', trim(__('site.home.direction'))) as $stop)
                        @php
                            // Stops that match a country on the site link to its page.
                            $link = $countryLinks[mb_strtolower(trim($stop))] ?? null;
                            $classes = 'stamp relative bg-white/80 text-moss-600'.($loop->first || $loop->last ? ' stamp--edge' : '').($link ? ' stamp--link' : '');
                        @endphp
                        <li class="relative flex items-center gap-3" data-reveal style="--i: {{ $loop->index }}; --tilt: {{ [-3, 2, -1, 3, -2][$loop->index % 5] }}deg">
                            @if ($link)
                                <a href="{{ $link }}" class="{{ $classes }}"><span class="stamp__nr">{{ $loop->iteration }}</span>{{ $stop }}</a>
                            @else
                                <span class="{{ $classes }}"><span class="stamp__nr">{{ $loop->iteration }}</span>{{ $stop }}</span>
                            @endif
                            @unless ($loop->last)
                                <svg class="h-4 w-10 text-olive-500" viewBox="0 0 40 16" fill="none" aria-hidden="true"><path d="M1 11c9-9 20-9 30-3" stroke="currentColor" stroke-width="2" stroke-dasharray="3 4" stroke-linecap="round"/><path d="M29 2l6 6-8 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- The route: dark band with waves at the top and bottom, the plan and the latest (delayed) location. --}}
    <section id="route" class="topo wave-both relative mt-20 bg-forest-900 pt-24 pb-28 text-sage-100">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
                <div>
                    <div class="flex flex-wrap items-end gap-x-4">
                        <h2 class="text-4xl font-extrabold text-white sm:text-5xl">{{ __('site.home.map_title') }}</h2>
                        <span class="-rotate-3 pb-1 font-hand text-3xl text-olive-300">{{ __('site.home.map_hand') }}</span>
                    </div>
                    @if ($lastLocation)
                        <p class="mt-2 text-sm text-sage-200">{{ __('site.home.last_seen', ['date' => $lastLocation->recorded_at->translatedFormat('j F Y')]) }}</p>
                    @endif
                </div>
                <a href="{{ lroute('journey') }}" class="btn-ghost">{{ __('site.journey.title') }} →</a>
            </div>
            <x-route-map :geojson="$overview" :delay-days="$delayDays" class="mt-8 h-[26rem] rounded-3xl shadow-2xl shadow-black/40 ring-4 ring-forest-700 sm:h-[34rem]" data-reveal />
        </div>
    </section>

    {{-- Latest news: the newest stories of any kind (preparation and on the road), stuck in like photos. --}}
    <section class="container-page mt-20">
        <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
            <div class="flex flex-wrap items-end gap-x-4">
                <h2 class="text-4xl font-extrabold sm:text-5xl">{{ __('site.home.news_title') }}</h2>
                <span class="-rotate-3 pb-1 font-hand text-3xl text-moss-600">{{ __('site.home.news_hand') }}</span>
            </div>
            <a href="{{ stories_url() }}" class="btn-outline">{{ __('site.home.all_updates') }} →</a>
        </div>
        @if ($latest->isEmpty())
            <p class="mt-8 rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.home.empty') }}</p>
        @else
            <div class="mt-10 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $article)
                    <x-article-card :article="$article" :index="$loop->index" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($gallery->isNotEmpty())
        {{-- Newest photos and videos: light sage band with waves, pinned-up prints. --}}
        <section class="topo-sand wave-top blend-footer mt-20 bg-sage-100 pt-24 pb-40">
            <div class="container-page">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4" data-reveal>
                    <div class="flex flex-wrap items-end gap-x-4">
                        <h2 class="text-4xl font-extrabold sm:text-5xl">{{ __('site.home.photos_title') }}</h2>
                        <span class="-rotate-3 pb-1 font-hand text-3xl text-moss-600">{{ __('site.home.photos_hand') }}</span>
                    </div>
                    <a href="{{ lroute('gallery') }}" class="btn-outline">{{ __('site.media.title') }} →</a>
                </div>
                <x-gallery-wall :items="$gallery" />
            </div>
        </section>
    @endif
</x-layouts.app>
