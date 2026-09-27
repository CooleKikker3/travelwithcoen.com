<figure class="article-image">
    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" @if ($imageStyle) style="{{ $imageStyle }}" @endif>
    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>
