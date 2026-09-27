@props(['photos' => collect(), 'videos' => collect()])
{{-- YouTube videos (synced) and gallery items (photos, uploaded videos, images from articles). --}}
@if ($videos->isNotEmpty())
    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($videos as $video)
            <figure class="overflow-hidden rounded-2xl bg-white ring-1 ring-sage-200">
                <div class="aspect-video bg-forest-900">
                    <iframe src="{{ $video->embedUrl() }}" title="{{ $video->translate('title') }}" class="size-full" loading="lazy"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
                <figcaption class="p-4">
                    <span class="font-display text-lg font-semibold">{{ $video->translate('title') }}</span>
                    @if ($video->published_on)
                        <span class="block text-xs text-moss-600">{{ $video->published_on->translatedFormat('j F Y') }}</span>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif

@if ($photos->isNotEmpty())
    <ul @class(['grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4', 'mt-6' => $videos->isNotEmpty()])>
        @foreach ($photos as $item)
            <li>
                <figure>
                    @if ($item->isVideo())
                        <video src="{{ $item->url() }}" controls preload="metadata" playsinline class="aspect-square w-full rounded-xl bg-forest-900 object-cover"></video>
                    @else
                        <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-xl bg-sage-100">
                            <img src="{{ $item->url() }}" alt="{{ $item->translate('caption') ?? '' }}" loading="lazy" class="size-full object-cover transition duration-500 hover:scale-105">
                        </a>
                    @endif
                    <figcaption class="mt-1 text-xs text-moss-600">
                        {{ $item->translate('caption') }}
                        @if ($item->article && $item->relationLoaded('article'))
                            <a href="{{ $item->article->url() }}" class="block font-semibold text-forest-700 hover:text-moss-600">{{ __('site.media.from_article', ['title' => $item->article->translate('title')]) }} →</a>
                        @endif
                    </figcaption>
                </figure>
            </li>
        @endforeach
    </ul>
@endif
