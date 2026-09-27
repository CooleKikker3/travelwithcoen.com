@props(['items'])
{{-- Gallery items mixed: photos, uploaded videos and YouTube videos. --}}
<ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
    @foreach ($items as $item)
        @php($caption = $item->translate('caption'))
        <li>
            <figure>
                @if ($item->isYoutube())
                    {{-- Loads the YouTube player only when clicked (resources/js/app.js). --}}
                    <a href="{{ $item->url() }}" data-youtube="{{ $item->youtube_id }}" target="_blank" rel="noopener"
                        class="group relative block aspect-square overflow-hidden rounded-xl bg-forest-900" aria-label="{{ $caption ?? 'YouTube video' }}">
                        <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy" class="size-full object-cover opacity-90 transition group-hover:opacity-100">
                        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <span class="flex size-14 items-center justify-center rounded-full bg-forest-900/80 text-white shadow-lg transition group-hover:bg-moss-600">▶</span>
                        </span>
                    </a>
                @elseif ($item->isVideo())
                    <video src="{{ $item->url() }}" controls preload="metadata" playsinline class="aspect-square w-full rounded-xl bg-forest-900 object-cover"></video>
                @else
                    <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-xl bg-sage-100">
                        <img src="{{ $item->url() }}" alt="{{ $caption ?? '' }}" loading="lazy" class="size-full object-cover transition duration-500 hover:scale-105">
                    </a>
                @endif
                <figcaption class="mt-1 text-xs text-moss-600">
                    {{ $caption }}
                    @if ($item->source === 'article' && $item->article)
                        <a href="{{ $item->article->url() }}" class="block font-semibold text-forest-700 hover:text-moss-600">{{ __('site.media.from_article', ['title' => $item->article->translate('title')]) }} →</a>
                    @endif
                </figcaption>
            </figure>
        </li>
    @endforeach
</ul>
