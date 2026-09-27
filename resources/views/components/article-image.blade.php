<figure @class(['article-image', 'sensitive' => $sensitive ?? false]) @if ($sensitive ?? false) data-sensitive @endif>
    <div class="relative overflow-hidden">
        <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" @if ($imageStyle) style="{{ $imageStyle }}" @endif>
        @if ($sensitive ?? false)
            <x-sensitive-overlay />
        @endif
    </div>
    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>
