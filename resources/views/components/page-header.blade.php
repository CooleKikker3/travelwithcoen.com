@props(['title', 'lead' => null, 'eyebrow' => null, 'country' => null])
<section class="topo wave-bottom bg-forest-800 text-sage-100">
    <div class="container-page pt-14 pb-24 sm:pt-20 sm:pb-28">
        @if ($eyebrow)
            <div class="mb-3 text-sm font-semibold tracking-wide text-fern-300 uppercase">{{ $eyebrow }}</div>
        @endif
        <h1 class="max-w-3xl text-4xl font-semibold text-white sm:text-5xl">@if ($country)<x-flag :country="$country" class="mr-3 align-[-0.05em]" />@endif{{ $title }}</h1>
        @if ($lead)
            <p class="mt-4 max-w-2xl text-lg text-sage-200">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
