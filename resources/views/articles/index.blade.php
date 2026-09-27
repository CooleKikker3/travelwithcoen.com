<x-layouts.app :title="__('site.diary.title')">
    <x-page-header :title="__('site.diary.title')" :lead="__('site.diary.lead')" />

    <div class="container-page mt-12">
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
