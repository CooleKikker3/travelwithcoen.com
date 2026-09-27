@props(['items', 'next' => null, 'columns' => 4])
{{--
    Playful masonry "wall" of gallery items (photos, videos, YouTube) in their own formats.
    resources/js/wall.js spreads the tiles over columns, pops them in while scrolling and,
    when $next is given, keeps loading older items. Without JS it is a simple column layout.
--}}
<div data-wall data-max-columns="{{ $columns }}" {{ $attributes->class('wall') }}>
    <div data-wall-source class="wall-source">
        @foreach ($items as $item)
            @php($caption = $item->translate('caption'))
            <figure data-wall-item data-ratio="{{ $item->ratio() }}" class="wall-item">
                @if ($item->isYoutube())
                    {{-- The YouTube player loads only when clicked (resources/js/app.js). --}}
                    <a href="{{ $item->url() }}" data-youtube="{{ $item->youtube_id }}" target="_blank" rel="noopener"
                        class="group relative block aspect-video overflow-hidden bg-forest-900" aria-label="{{ $caption ?? 'YouTube video' }}">
                        <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy" class="size-full object-cover opacity-90 transition group-hover:opacity-100">
                        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <span class="flex size-14 items-center justify-center rounded-full bg-forest-900/80 pl-1 text-xl text-white shadow-lg transition group-hover:scale-110 group-hover:bg-moss-600">▶</span>
                        </span>
                    </a>
                @elseif ($item->isVideo())
                    <video src="{{ $item->url() }}" controls preload="metadata" playsinline class="block w-full bg-forest-900" style="aspect-ratio: {{ $item->ratio() }}"></video>
                @else
                    <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="block overflow-hidden bg-sage-100">
                        <img src="{{ $item->url() }}" alt="{{ $caption ?? '' }}" loading="lazy" class="block w-full" style="aspect-ratio: {{ $item->ratio() }}">
                    </a>
                @endif

                @if ($caption || ($item->source === 'article' && $item->article))
                    <figcaption class="px-3 py-2 text-sm text-forest-800">
                        {{ $caption }}
                        @if ($item->source === 'article' && $item->article)
                            <a href="{{ $item->article->url() }}" class="mt-0.5 block text-xs font-semibold text-moss-600 hover:text-forest-700">{{ __('site.media.from_article', ['title' => $item->article->translate('title')]) }} →</a>
                        @endif
                    </figcaption>
                @endif
            </figure>
        @endforeach
    </div>

    @if ($next)
        <div class="mt-10 text-center">
            <a href="{{ $next }}" data-wall-next class="inline-block rounded-full bg-forest-800 px-5 py-2.5 font-semibold text-white hover:bg-forest-700">{{ __('site.media.more') }}</a>
        </div>
    @endif
</div>
