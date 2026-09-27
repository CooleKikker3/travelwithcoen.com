<x-layouts.app :title="$country->translate('name')" :description="$country->translate('intro')" :alternates="$alternates">
    <x-page-header :title="$country->flag().' '.$country->translate('name')" :lead="$country->translate('intro')">
        <div class="mt-6"><span class="badge">{{ $country->status->getLabel() }}</span></div>
    </x-page-header>

    <div class="container-page mt-12 max-w-4xl">
        <h2 class="text-2xl font-semibold">{{ __('site.country.story') }}</h2>
        @if ($story = $country->translate('story'))
            <div class="prose prose-lg mt-4 max-w-none prose-headings:font-display">{!! $story !!}</div>
        @else
            <p class="mt-4 text-moss-600">{{ __('site.country.no_story') }}</p>
        @endif
    </div>

    @if ($articles->isNotEmpty())
        <section class="container-page mt-14">
            <h2 class="text-2xl font-semibold">{{ __('site.country.articles', ['country' => $country->translate('name')]) }}</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif

    <div class="container-page mt-12">
        <a href="{{ lroute('journey') }}" class="font-semibold text-moss-600 hover:text-forest-700">← {{ __('site.country.back') }}</a>
    </div>
</x-layouts.app>
