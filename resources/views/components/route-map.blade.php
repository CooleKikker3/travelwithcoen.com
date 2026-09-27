@props(['geojson', 'interactive' => true, 'legend' => true, 'border' => null, 'startHome' => false])
{{-- Map of planned (dashed) and actual (solid) routes. With a border (MultiPolygon coordinates) only that country is shown. Initialised lazily by resources/js/map.js. --}}
<figure {{ $attributes->class('relative overflow-hidden rounded-2xl bg-sage-100 ring-1 ring-sage-200') }}
    data-route-map data-interactive="{{ $interactive ? 'true' : 'false' }}" data-start-home="{{ $startHome ? 'true' : 'false' }}">
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
        <figcaption class="absolute bottom-2 left-2 z-[500] flex flex-wrap gap-3 rounded-xl bg-forest-900/80 px-3 py-2 text-xs font-semibold text-white shadow-sm">
            <span class="flex items-center gap-1.5"><span class="w-6 border-t-[3px] border-dashed border-sand-100"></span>{{ __('site.map.planned') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-6 border-t-4 border-fern-300"></span>{{ __('site.map.actual') }}</span>
        </figcaption>
    @endif
</figure>
