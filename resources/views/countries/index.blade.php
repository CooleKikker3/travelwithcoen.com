<x-layouts.app :title="__('site.journey.title')">
    <x-page-header :title="__('site.journey.title')" :lead="__('site.journey.lead')" />

    <div class="container-page mt-12">
        <p class="mb-8 rounded-2xl bg-sage-100 p-4 text-sm text-forest-700">{{ __('site.journey.map_soon') }}</p>

        @if ($countries->isEmpty())
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.journey.empty') }}</p>
        @else
            <ol class="relative space-y-4 border-l-2 border-dashed border-moss-400 pl-6">
                @foreach ($countries as $country)
                    <li class="relative">
                        <span class="absolute top-6 -left-[33px] size-4 rounded-full border-4 border-mist-50 bg-moss-500" aria-hidden="true"></span>
                        <a href="{{ $country->url() }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-5 ring-1 ring-sage-200 transition hover:shadow-md">
                            <span class="flex items-center gap-4">
                                <span class="text-3xl" aria-hidden="true">{{ $country->flag() }}</span>
                                <span>
                                    <span class="block font-display text-xl font-semibold">{{ $country->translate('name') }}</span>
                                    <span class="text-sm text-moss-600">{{ trans_choice('site.journey.stories', $country->articles_count) }}</span>
                                </span>
                            </span>
                            <span class="badge">{{ $country->status->getLabel() }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-layouts.app>
