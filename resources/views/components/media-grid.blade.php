@props(['photos' => collect(), 'videos' => collect()])
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
                    @if ($description = $video->translate('description'))
                        <p class="mt-1 text-sm text-forest-700">{{ $description }}</p>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif

@if ($photos->isNotEmpty())
    <ul @class(['grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4', 'mt-6' => $videos->isNotEmpty()])>
        @foreach ($photos as $photo)
            <li>
                <figure>
                    <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-xl bg-sage-100">
                        <img src="{{ $photo->url() }}" alt="{{ $photo->translate('caption') ?? '' }}" loading="lazy" class="size-full object-cover transition duration-500 hover:scale-105">
                    </a>
                    @if ($caption = $photo->translate('caption'))
                        <figcaption class="mt-1 text-xs text-moss-600">{{ $caption }}</figcaption>
                    @endif
                </figure>
            </li>
        @endforeach
    </ul>
@endif
