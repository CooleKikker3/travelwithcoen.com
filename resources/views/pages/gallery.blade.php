<x-layouts.app :title="__('site.media.title')">
    <x-page-header :title="__('site.media.title')" :lead="__('site.media.lead')">
        <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('site.media.title') }}">
            @foreach (['' => __('site.media.all'), 'image' => __('site.media.photos'), 'video' => __('site.media.videos')] as $value => $label)
                <a href="{{ lroute('gallery') }}{{ $value ? '?kind='.$value : '' }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold', 'bg-fern-300 text-forest-950' => $kind === ($value ?: null), 'bg-forest-700 text-sage-100 hover:bg-forest-600' => $kind !== ($value ?: null)])>{{ $label }}</a>
            @endforeach
        </nav>
    </x-page-header>

    <div class="container-page mt-12">
        @if ($items->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.media.empty') }}</p>
        @else
            <x-media-grid :items="$items" />
            <div class="mt-8">{{ $items->links() }}</div>
        @endif
    </div>
</x-layouts.app>
