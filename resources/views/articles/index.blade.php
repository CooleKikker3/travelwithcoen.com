@php
    $key = $type === \App\Enums\ArticleType::Diary ? 'diary' : 'preparation';
    $route = "{$key}.index";
@endphp
<x-layouts.app :title="__('site.'.$key.'.title').($tag ? ' · #'.$tag : '')">
    <x-page-header :title="__('site.'.$key.'.title')" :lead="__('site.'.$key.'.lead')">
        @if ($tags->isNotEmpty())
            <nav class="mt-8 flex flex-wrap gap-2" aria-label="Tags">
                <a href="{{ lroute($route) }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold', 'bg-fern-300 text-forest-950' => ! $tag, 'bg-forest-700 text-sage-100 hover:bg-forest-600' => $tag])>{{ __('site.articles.all_tags') }}</a>
                @foreach ($tags as $t)
                    <a href="{{ lroute($route) }}?tag={{ urlencode($t) }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold', 'bg-fern-300 text-forest-950' => $tag === $t, 'bg-forest-700 text-sage-100 hover:bg-forest-600' => $tag !== $t])>#{{ $t }}</a>
                @endforeach
            </nav>
        @endif
    </x-page-header>

    <div class="container-page mt-12">
        @if ($tag)
            <h2 class="mb-6 text-2xl font-semibold">{{ __('site.articles.tagged', ['tag' => $tag]) }}</h2>
        @endif

        @if ($articles->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.articles.empty') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </div>
</x-layouts.app>
