@props(['article', 'locale'])
{{-- "Reading guide" at the top of an article, as a taped note: reading time, how often it was read, photos and the day of the walk. --}}
@php
    $photos = $article->photoCount($locale);
    $items = array_filter([
        ['clock', __('site.articles.reading_time', ['minutes' => $article->readingMinutes($locale)])],
        $article->views > 0 ? ['eye', trans_choice('site.articles.views', $article->views, ['count' => \Illuminate\Support\Number::format($article->views, locale: app()->getLocale())])] : null,
        $photos > 0 ? ['camera', trans_choice('site.articles.photos', $photos, ['count' => $photos])] : null,
        $article->journeyDay ? ['pin', __('site.articles.day', ['day' => $article->journeyDay->name()])] : null,
    ]);
    $paths = [
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'camera' => '<path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L17 6h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><circle cx="12" cy="13" r="3.5"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
    ];
@endphp
<aside {{ $attributes->class('reading-guide') }} aria-label="{{ __('site.articles.guide') }}">
    {{-- Spread evenly over the full width (equal space between and around the items); two per row on small phones. --}}
    <ul class="grid grid-cols-2 gap-x-4 gap-y-3 sm:flex sm:justify-evenly">
        @foreach ($items as [$icon, $text])
            <li class="flex items-center gap-2.5 font-bold text-forest-800">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-moss-600 shadow-sm ring-1 ring-sage-200">
                    {{-- Paths are fixed SVG markup above, not user input. --}}
                    <svg class="size-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$icon] !!}</svg>
                </span>
                {{ $text }}
            </li>
        @endforeach
    </ul>
</aside>
