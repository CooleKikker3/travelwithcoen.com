@props(['geojson', 'interactive' => true, 'legend' => true, 'border' => null, 'startHome' => false, 'delayDays' => 0, 'trackLevel' => 2, 'trackCountry' => null])
{{-- Map of routes, the rough plan, locations and events, with a legend of what is on it. With a border (MultiPolygon coordinates) only that country is shown. Initialised lazily by resources/js/map.js. --}}
<figure {{ $attributes->class('relative overflow-hidden rounded-2xl bg-sage-100 ring-1 ring-sage-200') }}
    data-route-map data-interactive="{{ $interactive ? 'true' : 'false' }}" data-start-home="{{ $startHome ? 'true' : 'false' }}" data-track-url="{{ route('api.track') }}" data-track-level="{{ $trackLevel }}" data-zoom-hint="{{ __('site.map.zoom_hint') }}" @if ($trackCountry) data-track-country="{{ $trackCountry }}" @endif>
    <script type="application/json">@json($geojson)</script>
    @if ($border)
        <script type="application/json" data-border>@json($border)</script>
    @endif
    <div data-map-canvas class="absolute inset-0" role="img" aria-label="{{ __('site.map.label') }}"></div>

    @if (empty($geojson['features']))
        <figcaption class="absolute inset-0 z-[500] flex items-center justify-center p-4 text-center text-sm font-semibold text-moss-600 pointer-events-none">
            <span class="rounded-full bg-white/90 px-3 py-1.5 shadow-sm">{{ __('site.map.no_route') }}</span>
        </figcaption>
    @elseif ($legend)
        @php
            // Only explain what is on this map.
            $types = collect($geojson['features'])->pluck('properties.type')->unique();
            $items = array_filter([
                'planned' => $types->contains('planned') ? __('site.map.planned') : null,
                'open' => $types->contains('open') ? __('site.map.open') : null,
                'actual' => $types->contains('actual') ? __('site.map.actual') : null,
                'exit' => $types->contains('exit') ? __('site.map.exit') : null,
                'position' => $types->contains('position') ? __('site.map.position').($delayDays ? ' ('.__('site.map.delay', ['days' => $delayDays]).')' : '') : null,
                'endpoints' => $types->contains('start') ? __('site.map.endpoints') : null,
            ]);
        @endphp
        @if ($items)
            <figcaption class="map-legend absolute bottom-3 left-3 z-[500] max-w-[calc(100%-1.5rem)] rounded-2xl bg-forest-950/80 px-4 py-3 text-xs font-bold text-sage-100 shadow-lg ring-1 ring-white/10 backdrop-blur">
                <span class="block -rotate-2 font-hand text-xl leading-none text-olive-300">{{ __('site.map.legend') }}</span>
                <ul class="mt-2 flex flex-col gap-1.5">
                    @foreach ($items as $type => $label)
                        <li class="flex items-center gap-2.5"><span class="map-legend__key map-legend__key--{{ $type }}" aria-hidden="true"></span>{{ $label }}</li>
                    @endforeach
                </ul>
            </figcaption>
        @endif
    @endif
</figure>
