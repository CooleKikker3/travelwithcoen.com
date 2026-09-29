@php
    use App\Enums\DayType;
    use App\Models\JourneyDay;
    use App\Models\TrackingPoint;
    use App\Support\JourneyStats;
    use App\Support\RouteGeometry;
    use App\Support\TrackingPrivacy;

    // The latest day and position the viewer may see (visitors: after the delay; family: live), and the totals so far.
    $user = auth()->user();
    $day = JourneyDay::visibleTo($user)->select('journey_days.*')->withNumber()->with('country')->latest('date')->first();
    $stats = $day ? JourneyStats::for($user) : null;
    $delayed = TrackingPrivacy::cutoff($user) !== null;
    $state = match (true) {
        ! $day => null,
        $day->type === DayType::Rest => __('site.status_block.resting'),
        $day->started_at && ! $day->ended_at => __('site.status_block.walking'),
        default => null, // a normal walked day needs no extra word
    };

    // As the crow flies to Hanoi, from the latest visible position (else from the start).
    $position = TrackingPoint::visibleTo($user)->with('country')->latest('recorded_at')->first(['latitude', 'longitude', 'country_id']);
    // Current country: of the latest visible position, else of the latest day.
    $country = $position?->country ?? $day?->country;
    [$from, $to] = [$position ? [(float) $position->latitude, (float) $position->longitude] : [config('travel.start.lat'), config('travel.start.lng')], [config('travel.destination.lat'), config('travel.destination.lng')]];
    $toHanoi = RouteGeometry::distanceKm([$from, $to]);
    $fromHome = RouteGeometry::distanceKm([[config('travel.start.lat'), config('travel.start.lng')], $to]);
    $kmFormat = __('site.stats.km', ['km' => '#']); // e.g. "# km", for the counting numbers (app.js)
    $km = fn ($value) => __('site.stats.km', ['km' => \Illuminate\Support\Number::format($value, maxPrecision: 0, locale: app()->getLocale())]);
@endphp
{{-- "Live": where Coen is and the key numbers. Hidden until there is a visible journey day. --}}
@if ($day)
    <section {{ $attributes->class('container-page relative z-10') }}>
        <div class="rounded-3xl bg-white px-5 py-4 shadow-2xl shadow-forest-950/35 ring-1 ring-sage-200 sm:px-6" data-reveal>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-forest-800 px-3 py-1 text-sm font-extrabold tracking-wide text-fern-300 uppercase">
                    <span class="relative flex size-2.5" aria-hidden="true">
                        @unless ($delayed)<span class="absolute inset-0 animate-ping rounded-full bg-fern-300"></span>@endunless
                        <span class="relative size-2.5 rounded-full bg-fern-300"></span>
                    </span>
                    {{ __('site.status_block.live') }}
                </span>
                <span class="-rotate-2 font-hand text-2xl text-moss-600">
                    {{ $day->name() }}@if ($state) · {{ $state }}@endif
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                @foreach ([
                    // [label, value, count from, format]: numbers count up (or down, to Hanoi) when shown.
                    [__('site.status_block.walked_km'), round($stats['distance_km']), 0, $kmFormat],
                    [__('site.status_block.days'), $stats['days'], 0, '#'],
                    [__('site.status_block.to_hanoi'), round($toHanoi), round($fromHome), $kmFormat],
                    [__('site.status_block.country'), $country, null, null],
                ] as [$label, $value, $countFrom, $format])
                    <div class="rounded-xl bg-sage-100 px-3 py-2">
                        <dt class="text-[0.65rem] font-bold tracking-wide text-moss-600 uppercase">{{ $label }}</dt>
                        <dd class="font-display text-xl font-extrabold text-forest-900 sm:text-2xl">
                            @if ($value instanceof \App\Models\Country)
                                <x-flag :country="$value" class="mr-1 align-[-0.1em]" /> {{ $value->translate('name') }}
                            @elseif ($format)
                                <span data-count-from="{{ $countFrom }}" data-count-to="{{ $value }}" data-format="{{ $format }}">{{ str_replace('#', \Illuminate\Support\Number::format($value, locale: app()->getLocale()), $format) }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if ($delayed)
                <p class="mt-2 text-xs text-moss-600">{{ __('site.status_block.as_of', ['date' => $day->date->translatedFormat('j F Y')]) }} · {{ __('site.map.delay_note', ['days' => (int) round(\App\Support\Settings::get('public_tracking_delay_hours') / 24)]) }}</p>
            @endif
        </div>
    </section>
@endif
