<x-layouts.app :title="__('site.about.title')">
    <x-page-header :title="__('site.about.title')" :lead="__('site.about.lead')" />

    <div class="container-page mt-12">
        <div class="prose prose-lg max-w-3xl prose-headings:font-display prose-p:text-forest-800">
            @foreach (__('site.about.body') as $paragraph)
                <p>{{ str_replace([':Departure', ':departure'], [ucfirst(__('site.departure')), __('site.departure')], $paragraph) }}</p>
            @endforeach
        </div>
        <p class="mt-8 max-w-3xl rounded-2xl bg-sand-100 p-5 text-sm text-bark-700">{{ __('site.about.uncertain') }}</p>
    </div>
</x-layouts.app>
