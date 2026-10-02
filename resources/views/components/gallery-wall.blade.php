@props(['items', 'next' => null])
{{--
    Playful photo wall: tiles of different shapes interlock in a dense mosaic (wide items span two
    columns, tall ones two rows, now and then a big one). Clicking a tile opens the lightbox with the
    large version and its description; videos only play when started there. See resources/js/wall.js.
--}}
<div data-wall {{ $attributes->class('wall') }}>
    {{-- Container for the square cell size (100cqw); the lightbox must stay outside it. --}}
    <div class="wall-body">
    <div data-wall-grid class="wall-grid">
        @foreach ($items as $item)
            @php
                $ratio = $item->ratio();
                $shape = match (true) {
                    $item->id % 9 === 0 => 'wall-tile--big',
                    $ratio >= 1.45 => 'wall-tile--wide',
                    $ratio <= 0.8 => 'wall-tile--tall',
                    $ratio > 1.15 && $item->id % 2 === 0 => 'wall-tile--wide',
                    default => '',
                };
                $caption = $item->translate('caption');
                // Sharp 16:9 YouTube thumbnail; falls back to the smaller one if a video has no HD version.
                $hd = $item->isYoutube() ? "https://i.ytimg.com/vi/{$item->youtube_id}/maxresdefault.jpg" : null;
            @endphp
            <a href="{{ $item->url() }}" id="wall-item-{{ $item->id }}" data-wall-item @class(['wall-tile', $shape, 'wall-tile--video' => $item->isVideo() || $item->isYoutube(), 'wall-tile--sensitive' => $item->is_sensitive])
                data-kind="{{ $item->kind }}"
                data-sensitive="{{ $item->is_sensitive ? 1 : 0 }}"
                data-ratio="{{ $item->ratio() }}"
                data-src="{{ $item->isYoutube() ? $item->youtube_id : $item->url() }}"
                data-thumb="{{ $item->isYoutube() ? $hd : $item->thumbnailUrl() }}"
                data-thumb-fallback="{{ $item->thumbnailUrl() }}"
                data-caption="{{ $caption }}"
                data-date="{{ $item->taken_at?->translatedFormat('j F Y') }}"
                data-country="{{ $item->country ? $item->country->flag().' '.$item->country->translate('name') : '' }}"
                @if ($item->article)
                    data-article-url="{{ $item->article->url() }}" data-article-title="{{ __('site.media.from_article', ['title' => $item->article->translate('title')]) }}"
                @endif
                aria-label="{{ $caption ?: __('site.media.open') }}">
                @if ($item->isVideo())
                    <video src="{{ $item->url() }}#t=0.5" preload="metadata" muted playsinline tabindex="-1" aria-hidden="true"></video>
                @else
                    <img src="{{ $item->isYoutube() ? $hd : $item->thumbnailUrl() }}" alt="{{ $caption ?? '' }}" loading="lazy"
                        @if ($item->isYoutube()) onerror="this.onerror=null;this.src='{{ $item->thumbnailUrl() }}'" @endif>
                @endif
                @if ($item->is_sensitive)
                    <span class="wall-sensitive"><x-icons.eye-off class="size-6" />{{ __('site.media.sensitive_label') }}</span>
                @elseif ($item->isVideo() || $item->isYoutube())
                    <span class="wall-play" aria-hidden="true"><x-icons.play /></span>
                @endif
            </a>
        @endforeach
    </div>
    </div>

    @if ($next)
        <div class="mt-10 text-center">
            <a href="{{ $next }}" data-wall-next class="inline-block rounded-full bg-forest-800 px-5 py-2.5 font-semibold text-white hover:bg-forest-700">{{ __('site.media.more') }}</a>
        </div>
    @endif

    {{-- Lightbox: large media left, description right. --}}
    <dialog data-wall-lightbox class="wall-lightbox" aria-label="{{ __('site.media.title') }}">
        <div class="wall-lightbox__inner">
            <div data-lightbox-media class="wall-lightbox__media"></div>
            {{-- Copied into the media area for sensitive items (wall.js). --}}
            <template data-lightbox-sensitive><x-sensitive-overlay /></template>
            <aside class="wall-lightbox__info">
                <p data-lightbox-caption class="text-lg text-forest-900"></p>
                <p data-lightbox-meta class="mt-3 text-sm text-moss-600"></p>
                <a data-lightbox-article href="#" class="mt-3 block text-sm font-semibold text-moss-600 hover:text-forest-700"></a>
                <button type="button" data-lightbox-play class="mt-6 inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 font-semibold text-white hover:bg-forest-700"><x-icons.play class="size-4" /> {{ __('site.media.play') }}</button>
                <div class="mt-auto flex items-center gap-2 pt-6">
                    <button type="button" data-lightbox-prev class="wall-lightbox__nav" aria-label="{{ __('site.media.previous') }}"><x-icons.arrow left /></button>
                    <button type="button" data-lightbox-next class="wall-lightbox__nav" aria-label="{{ __('site.media.next') }}"><x-icons.arrow /></button>
                    <button type="button" data-lightbox-close autofocus class="wall-lightbox__nav ms-auto" aria-label="{{ __('site.media.close') }}">✕</button>
                </div>
            </aside>
        </div>
    </dialog>
</div>
