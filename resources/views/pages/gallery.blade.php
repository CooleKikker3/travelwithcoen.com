<x-layouts.app :title="__('site.media.title')">
    <x-page-header :title="__('site.media.title')" :lead="__('site.media.lead')">
        <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('site.media.title') }}">
            @foreach ([null => __('site.media.all'), 'image' => __('site.media.photos'), 'video' => __('site.media.videos')] as $value => $label)
                <a href="{{ lroute('gallery') }}{{ $value ? '?kind='.$value : '' }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold', 'bg-fern-300 text-forest-950' => $kind === ($value ?: null), 'bg-forest-700 text-sage-100 hover:bg-forest-600' => $kind !== ($value ?: null)])>{{ $label }}</a>
            @endforeach
        </nav>
    </x-page-header>

    <div class="container-page mt-12">
        @if ($items->isEmpty() && $videos->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.media.empty') }}</p>
        @endif

        @if ($items->isNotEmpty())
            <x-media-grid :photos="$items" />
            <div class="mt-8">{{ $items->links() }}</div>
        @endif

        @if ($videos->isNotEmpty())
            <h2 class="mt-14 mb-4 text-2xl font-semibold">{{ __('site.media.youtube') }}</h2>
            <x-media-grid :videos="$videos" />
        @endif
    </div>
</x-layouts.app>
