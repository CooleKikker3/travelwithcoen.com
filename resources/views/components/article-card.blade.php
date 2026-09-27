@props(['article', 'index' => 0])
<article class="polaroid group flex flex-col rounded-md bg-white p-3 pb-4 shadow-md ring-1 ring-sage-200/70" data-reveal style="--i: {{ $index ?? 0 }}">
    <a href="{{ $article->url() }}" class="block aspect-[4/3] overflow-hidden rounded-sm bg-sage-100">
        @if ($article->coverUrl())
            <img src="{{ $article->coverUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="topo size-full bg-forest-700"></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 px-2 pt-4">
        <div class="flex flex-wrap items-center gap-2 text-xs text-moss-600">
            <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time>
            @foreach (array_slice($article->tags ?? [], 0, 3) as $tag)
                <span class="badge">#{{ $tag }}</span>
            @endforeach
            @if ($article->country)
                <span class="badge"><x-flag :country="$article->country" /> {{ $article->country->translate('name') }}</span>
            @endif
            @unless ($article->isTranslated(app()->getLocale()))
                <span class="badge bg-sand-100 text-bark-700">EN</span>
            @endunless
        </div>
        <h3 class="text-xl font-semibold text-forest-900">
            <a href="{{ $article->url() }}" class="hover:text-moss-600">{{ $article->translate('title') }}</a>
        </h3>
        @if ($excerpt = $article->translate('excerpt'))
            <p class="text-forest-700">{{ $excerpt }}</p>
        @endif
    </div>
</article>
