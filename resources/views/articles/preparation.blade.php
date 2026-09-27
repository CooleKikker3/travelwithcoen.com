<x-layouts.app :title="__('site.preparation.title')">
    <x-page-header :title="__('site.preparation.title')" :lead="__('site.preparation.lead')">
        <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('site.preparation.title') }}">
            @foreach ($topics as $topic)
                <a href="#{{ $topic->value }}" class="rounded-full bg-forest-700 px-3 py-1.5 text-sm font-semibold text-sage-100 hover:bg-forest-600">
                    {{ $topic->getLabel() }} <span class="text-fern-300">{{ $byTopic->get($topic->value)?->count() ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </x-page-header>

    <div class="container-page mt-12 space-y-14">
        @foreach ($topics as $topic)
            <section id="{{ $topic->value }}" class="scroll-mt-6">
                <h2 class="border-b border-sage-200 pb-2 text-2xl font-semibold">{{ $topic->getLabel() }}</h2>
                @if ($byTopic->has($topic->value))
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($byTopic[$topic->value] as $article)
                            <x-article-card :article="$article" />
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-moss-600">{{ __('site.preparation.topic_empty') }}</p>
                @endif
            </section>
        @endforeach

        @if ($byTopic->has('other'))
            <section>
                <h2 class="border-b border-sage-200 pb-2 text-2xl font-semibold">{{ __('site.articles.other_topic') }}</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($byTopic['other'] as $article)
                        <x-article-card :article="$article" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
