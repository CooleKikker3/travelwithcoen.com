<x-layouts.app :title="__('site.media.title')">
    <x-page-header :title="__('site.media.title')" :lead="__('site.media.lead')" />

    <div class="container-page mt-12">
        @if ($photos->isEmpty() && $videos->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.media.empty') }}</p>
        @endif

        @if ($videos->isNotEmpty())
            <h2 class="mb-4 text-2xl font-semibold">{{ __('site.media.videos') }}</h2>
            <x-media-grid :videos="$videos" />
        @endif

        @if ($photos->isNotEmpty())
            <h2 class="mt-12 mb-4 text-2xl font-semibold">{{ __('site.media.photos') }}</h2>
            <x-media-grid :photos="$photos" />
            <div class="mt-8">{{ $photos->links() }}</div>
        @endif
    </div>
</x-layouts.app>
