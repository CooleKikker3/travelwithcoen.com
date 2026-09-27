@props(['title', 'lead' => null, 'eyebrow' => null])
<section class="topo bg-forest-800 text-sage-100">
    <div class="container-page py-14 sm:py-20">
        @if ($eyebrow)
            <div class="mb-3 text-sm font-semibold tracking-wide text-fern-300 uppercase">{{ $eyebrow }}</div>
        @endif
        <h1 class="max-w-3xl text-4xl font-semibold text-white sm:text-5xl">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-4 max-w-2xl text-lg text-sage-200">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
