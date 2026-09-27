@php
    use App\Enums\JourneyPhase;
    use App\Support\JourneyStats;
    use App\Support\Settings;

    $preparing = (JourneyPhase::tryFrom(Settings::get('journey_phase')) ?? JourneyPhase::Preparation) === JourneyPhase::Preparation;
    $stats = JourneyStats::for(auth()->user());
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
@endphp
{{-- Key facts of the journey as tilted luggage tags. --}}
<section {{ $attributes }}>
    <dl class="flex flex-wrap gap-4">
        @foreach ($facts as [$label, $value])
            <div class="bob rounded-xl border-2 border-dashed border-olive-300 bg-sand-100 px-4 py-2 shadow-sm" data-reveal style="--i: {{ $loop->index }}; --tilt: {{ [2, -2, 1, -1][$loop->index % 4] }}deg; animation-delay: -{{ $loop->index * 0.8 }}s">
                <dt class="text-xs font-bold tracking-wide text-bark-700 uppercase">{{ $label }}</dt>
                <dd class="font-hand text-2xl leading-tight text-forest-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
</section>
