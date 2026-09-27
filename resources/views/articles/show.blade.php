@php
    $default = config('app.fallback_locale');
    $contentLocale = $isTranslated ? app()->getLocale() : $default;
@endphp
<x-layouts.app
    :title="$article->translate('title')"
    :description="$article->translate('excerpt')"
    :alternates="$alternates"
    :canonical="$isTranslated ? null : $article->url($default)"
>
    <article>
        <header class="topo bg-forest-800 text-sage-100">
            <div class="container-page max-w-4xl py-14 sm:py-20">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge bg-forest-700 text-fern-300">{{ $article->type->getLabel() }}</span>
                    @if ($article->country)
                        <a href="{{ $article->country->url() }}" class="badge bg-forest-700 text-fern-300 hover:text-white">{{ $article->country->flag() }} {{ $article->country->translate('name') }}</a>
                    @endif
                </div>
                <h1 class="mt-4 text-4xl font-semibold text-white sm:text-5xl" lang="{{ $contentLocale }}">{{ $article->translate('title') }}</h1>
                <p class="mt-4 text-sage-200">
                    {{ __('site.articles.published', ['date' => $article->published_at->translatedFormat('j F Y')]) }}
                </p>
            </div>
        </header>

        @if ($article->coverUrl())
            <div class="container-page max-w-5xl -mt-2">
                <img src="{{ $article->coverUrl() }}" alt="" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-lg">
            </div>
        @endif

        <div class="container-page mt-10 max-w-3xl">
            @unless ($isTranslated)
                <p class="mb-8 rounded-2xl bg-sand-100 p-4 text-sm text-bark-700" role="note">{{ __('site.articles.not_translated') }}</p>
            @endunless

            @if ($excerpt = $article->translate('excerpt'))
                <p class="text-xl text-forest-700" lang="{{ $contentLocale }}">{{ $excerpt }}</p>
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

            <a href="{{ stories_url() }}" class="mt-12 inline-block font-semibold text-moss-600 hover:text-forest-700">
                ← {{ __('site.articles.back') }}
            </a>
        </div>
    </article>
</x-layouts.app>
