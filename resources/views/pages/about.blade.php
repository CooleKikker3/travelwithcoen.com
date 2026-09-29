@php
    $photo = ($path = \App\Support\Settings::get('about_image')) ? \App\Support\MediaStorage::url($path) : null;
    $youtube = config('travel.youtube_channel');
@endphp
<x-layouts.app :title="__('site.about.title')" :image="$photo">
    <x-page-header :title="__('site.about.title')" :lead="__('site.about.lead')" />

    <x-journey-facts class="container-page mt-12" />

    <div class="container-page mt-14 grid items-start gap-12 lg:grid-cols-[1fr_22rem]">
        <div>
            <div class="prose prose-lg max-w-none prose-headings:font-display prose-p:text-forest-800">
                @foreach (preg_split('/\R\s*\R/', trim(__('site.about.body', ['departure' => __('site.departure')]))) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <p class="mt-8 rounded-2xl bg-sand-100 p-5 text-sm text-bark-700">{{ __('site.about.uncertain') }}</p>
            @if ($youtube)
                <a href="{{ str_starts_with($youtube, 'http') ? $youtube : 'https://www.youtube.com/'.ltrim($youtube, '/') }}" class="btn-outline mt-8" rel="noopener" target="_blank">{{ __('site.about.youtube') }} →</a>
            @endif
        </div>
        @if ($photo)
            {{-- A polaroid stuck in the journal. --}}
            <figure class="polaroid mx-auto max-w-sm rounded-md bg-white p-3 pb-10 shadow-xl ring-1 ring-sage-200/70 lg:sticky lg:top-28" style="--tilt: 2deg" data-reveal>
                <img src="{{ $photo }}" alt="{{ __('site.about.hand') }}" class="aspect-[4/5] w-full rounded-sm object-cover" loading="lazy">
                <figcaption class="mt-4 text-center font-hand text-2xl text-forest-800">{{ __('site.about.hand') }}</figcaption>
            </figure>
        @endif
    </div>
</x-layouts.app>