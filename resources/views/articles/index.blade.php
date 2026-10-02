<x-layouts.app :title="__('site.journey.stories_title')">
    <x-page-header :title="__('site.journey.stories_title')" :lead="__('site.stories.lead')" />

    <section class="container-page mt-12">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('site.journey.stories_title') }}">
            @php
                $chip = fn (bool $active) => $active ? 'bg-forest-800 text-white' : 'bg-sage-100 text-forest-700 hover:bg-sage-200';
            @endphp
            <a href="{{ stories_url() }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $chip(! $type && ! $tag) }}">{{ __('site.articles.all_tags') }}</a>
            @foreach (\App\Enums\ArticleType::cases() as $case)
                <a href="{{ stories_url($case->value) }}" class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $chip($type === $case && ! $tag) }}">{{ $case->getLabel() }}</a>
            @endforeach
        </nav>

        @if ($articles->isEmpty())
            <p class="mt-8 rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.articles.empty') }}</p>
        @else
            <div class="mt-10 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" :index="$loop->index" />
                @endforeach
            </div>
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </section>
</x-layouts.app>