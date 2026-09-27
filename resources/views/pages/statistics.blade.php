@php
    $n = fn ($value, $decimals = 0) => \Illuminate\Support\Number::format($value ?? 0, maxPrecision: $decimals, locale: app()->getLocale());
    $km = fn ($value) => __('site.stats.km', ['km' => $n($value)]);
    $delayDays = round(\App\Support\TrackingPrivacy::delayHours() / 24);
@endphp
<x-layouts.app :title="__('site.statistics.title')">
    <x-page-header :title="__('site.statistics.title')" :lead="__('site.statistics.lead', ['days' => $delayDays])" />

    <div class="container-page mt-10">
        @if (! $stats['days'])
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.statistics.empty') }}</p>
            <dl class="mt-6 grid gap-3 sm:grid-cols-3">
                <x-stat :label="__('site.statistics.planned')" :value="$km($stats['planned_km'])" :hint="__('site.status.not_final')" />
            </dl>
        @else
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat :label="__('site.statistics.distance')" :value="$km($stats['distance_km'])" />
                <x-stat :label="__('site.statistics.days')" :value="$n($stats['days'])" :hint="$stats['first_day']?->translatedFormat('j M Y').' – '.$stats['last_day']?->translatedFormat('j M Y')" />
                <x-stat :label="__('site.statistics.walking_days')" :value="$n($stats['walking_days'])" />
                <x-stat :label="__('site.statistics.rest_days')" :value="$n($stats['rest_days'])" />
                <x-stat :label="__('site.statistics.average')" :value="$km($stats['average_km'])" />
                <x-stat :label="__('site.statistics.longest')" :value="$stats['longest'] ? $km($stats['longest']->distance_km) : '—'" :hint="$stats['longest']?->date->translatedFormat('j M Y')" />
                <x-stat :label="__('site.statistics.hours')" :value="$n($stats['walking_hours'])" />
                <x-stat :label="__('site.statistics.countries')" :value="$n($stats['countries'])" />
                <x-stat :label="__('site.statistics.tent_nights')" :value="$n($stats['tent_nights'])" />
                <x-stat :label="__('site.statistics.highest')" :value="$stats['highest_m'] !== null ? $n($stats['highest_m']).' m' : '—'" />
                <x-stat :label="__('site.statistics.lowest')" :value="$stats['lowest_m'] !== null ? $n($stats['lowest_m']).' m' : '—'" />
                <x-stat :label="__('site.statistics.planned')" :value="$km($stats['planned_km'])"
                    :hint="$stats['planned_km'] ? __('site.statistics.difference').': '.$n($stats['distance_km'] / $stats['planned_km'] * 100).'%' : null" />
            </dl>

            @if ($stats['nights'])
                <h2 class="mt-12 text-2xl font-semibold">{{ __('site.statistics.nights') }}</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($stats['nights'] as $type => $count)
                        <x-stat :label="\App\Enums\Overnight::from($type)->getLabel()" :value="$n($count)" />
                    @endforeach
                </dl>
            @endif

            <h2 class="mt-12 text-2xl font-semibold">{{ __('site.statistics.per_country') }}</h2>
            <div class="mt-4 overflow-x-auto rounded-2xl bg-white ring-1 ring-sage-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-sage-100 text-xs tracking-wide text-moss-600 uppercase">
                        <tr>
                            <th class="px-4 py-3">{{ __('site.stats.countries') }}</th>
                            <th class="px-4 py-3">{{ __('site.statistics.distance') }}</th>
                            <th class="px-4 py-3">{{ __('site.statistics.days') }}</th>
                            <th class="px-4 py-3">{{ __('site.statistics.rest_days') }}</th>
                            <th class="px-4 py-3">{{ __('site.statistics.average') }}</th>
                            <th class="px-4 py-3">{{ __('site.statistics.hours') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sage-100">
                        @foreach ($countries->filter(fn ($row) => $row['stats']['days']) as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold"><a href="{{ $row['country']->url() }}" class="hover:text-moss-600">{{ $row['country']->flag() }} {{ $row['country']->translate('name') }}</a></td>
                                <td class="px-4 py-3">{{ $km($row['stats']['distance_km']) }}</td>
                                <td class="px-4 py-3">{{ $n($row['stats']['days']) }}</td>
                                <td class="px-4 py-3">{{ $n($row['stats']['rest_days']) }}</td>
                                <td class="px-4 py-3">{{ $km($row['stats']['average_km']) }}</td>
                                <td class="px-4 py-3">{{ $n($row['stats']['walking_hours']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
