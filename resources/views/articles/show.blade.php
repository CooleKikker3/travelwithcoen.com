@php
    $default = $article->originalLocale();
    $contentLocale = $isTranslated ? app()->getLocale() : $default;
@endphp
<x-layouts.app
    :title="$article->translate('title')"
    :description="$article->translate('excerpt')"
    :alternates="$alternates"
    :canonical="$isTranslated ? null : $article->url($default)"
    :image="$article->coverUrl()"
>
    <article>
        <header class="wave-bottom relative isolate overflow-hidden bg-forest-800 text-sage-100">
            @if ($article->coverUrl())
                <img src="{{ $article->coverUrl() }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950/90 via-forest-900/60 to-forest-900/30"></div>
            @endif
            <div class="topo absolute inset-0 -z-10" aria-hidden="true"></div>
            <div @class(['container-page max-w-4xl pt-14 pb-28 sm:pt-20 sm:pb-32', 'min-h-[60vh] flex flex-col justify-end' => $article->coverUrl()])>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge bg-forest-700 text-fern-300">{{ $article->type->getLabel() }}</span>
                    @if ($article->country)
                        <a href="{{ $article->country->url() }}" class="badge bg-forest-700 text-fern-300 hover:text-white"><x-flag :country="$article->country" /> {{ $article->country->translate('name') }}</a>
                    @endif
                </div>
                <h1 class="rise mt-4 text-4xl font-extrabold text-white drop-shadow sm:text-6xl" lang="{{ $contentLocale }}">{{ $article->translate('title') }}</h1>
                <p class="mt-4 text-sage-200">
                    {{ __('site.articles.published', ['date' => $article->published_at->translatedFormat('j F Y')]) }}
                </p>
            </div>
        </header>

        <div class="container-page mt-10 max-w-3xl">
            @unless ($isTranslated)
                <p class="mb-8 rounded-2xl bg-sand-100 p-4 text-sm text-bark-700" role="note">{{ __('site.articles.not_translated') }}</p>
            @endunless

            @if ($excerpt = $article->translate('excerpt'))
                <p class="-rotate-1 border-l-4 border-olive-300 pl-5 font-display text-2xl text-forest-700" lang="{{ $contentLocale }}">{{ $excerpt }}</p>
            @endif

            {{-- Body is HTML from the admin-only CMS editor. --}}
            <div class="prose prose-lg mt-8 max-w-none prose-headings:font-display prose-a:text-moss-600 prose-img:rounded-xl" lang="{{ $contentLocale }}">
                {!! $article->bodyHtml() !!}
            </div>

            @if ($article->gallery->isNotEmpty())
                <div class="mt-10"><x-gallery-wall :items="$article->gallery" /></div>
            @endif

            @if ($article->tags)
                <ul class="mt-10 flex flex-wrap gap-2" aria-label="{{ __('site.articles.tags') }}">
                    @foreach ($article->tags as $tag)
                        <li><a href="{{ stories_url(null, $tag) }}" class="badge hover:bg-sage-200">#{{ $tag }}</a></li>
                    @endforeach
                </ul>
            @endif

            <a href="{{ stories_url() }}" class="btn-outline mt-12">← {{ __('site.articles.back') }}</a>
        </div>
    </article>
</x-layouts.app>
