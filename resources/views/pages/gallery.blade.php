<x-layouts.app :title="__('site.media.title')">
    <x-page-header :title="__('site.media.title')" :lead="__('site.media.lead')" />

    <div class="container-page mt-12">
        @if ($items->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.media.empty') }}</p>
        @else
            <x-gallery-wall :items="$items" :next="$items->nextPageUrl()" />
        @endif
    </div>
</x-layouts.app>
