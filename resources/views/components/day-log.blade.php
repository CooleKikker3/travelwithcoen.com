@props(['days'])
{{-- Day by day: the journey days the viewer may see (visitors after the delay), newest first. --}}
@if ($days->isNotEmpty())
    <section {{ $attributes->class('container-page') }}>
        <div class="flex flex-wrap items-end gap-x-4" data-reveal>
            <h2 class="text-4xl font-extrabold sm:text-5xl">{{ __('site.day_log.title') }}</h2>
            <span class="-rotate-3 pb-1 font-hand text-3xl text-moss-600">{{ __('site.day_log.hand') }}</span>
        </div>
        <ol class="mt-8 grid gap-3">
            @foreach ($days as $day)
                <li class="flex flex-wrap items-center gap-x-5 gap-y-1 rounded-2xl bg-white px-5 py-4 ring-1 ring-sage-200" data-reveal style="--i: {{ min($loop->index, 6) }}">
                    <span class="w-20 font-display text-xl font-extrabold">{{ $day->name() }}</span>
                    <span class="w-32 text-sm text-moss-600">{{ $day->date->translatedFormat('D j M Y') }}</span>
                    <span class="min-w-0 flex-1 font-semibold">
                        @if ($day->start_location || $day->end_location)
                            {{ $day->start_location ?? '…' }} <span class="text-olive-500" aria-hidden="true"><x-icons.arrow /></span> {{ $day->end_location ?? '…' }}
                        @else
                            {{ $day->type->getLabel() }}
                        @endif
                    </span>
                    <span class="flex flex-wrap items-center gap-2 text-sm">
                        @if ($day->country)<x-flag :country="$day->country" />@endif
                        @if ($day->distance_km)
                            <span class="font-bold">{{ __('site.stats.km', ['km' => \Illuminate\Support\Number::format($day->distance_km, maxPrecision: 1, locale: app()->getLocale())]) }}</span>
                        @endif
                        @if ($day->type !== \App\Enums\DayType::Walk)
                            <span class="badge bg-sand-100 text-bark-700">{{ $day->type->getLabel() }}</span>
                        @endif
                        @if ($day->overnight)
                            <span class="badge">{{ $day->overnight->getLabel() }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    </section>
@endif
