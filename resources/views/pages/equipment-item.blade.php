{{-- One piece of gear, laid out like an article (articles/show). --}}
@php
    $default = $item->originalLocale();
    $contentLocale = $isTranslated ? app()->getLocale() : $default;
@endphp
<x-layouts.app
    :title="$item->translate('name')"
    :description="$item->translate('excerpt')"
    :alternates="$alternates"
    :canonical="$isTranslated ? null : $item->url($default)"
    :image="$item->coverUrl()"
>
    <article>
        <header class="wave-bottom relative isolate overflow-hidden bg-forest-800 text-sage-100">
            @if ($item->coverUrl())
                <img src="{{ $item->coverUrl() }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950/90 via-forest-900/60 to-forest-900/30"></div>
            @endif
            <div class="topo absolute inset-0 -z-10" aria-hidden="true"></div>
            <div @class(['container-page max-w-4xl pt-14 pb-28 sm:pt-20 sm:pb-32', 'min-h-[60vh] flex flex-col justify-end' => $item->coverUrl()])>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <a href="{{ lroute('equipment') }}" class="badge bg-forest-700 text-fern-300 hover:text-white">{{ $item->category->getLabel() }}</a>
                </div>
                <h1 class="rise mt-4 text-4xl font-extrabold text-white drop-shadow sm:text-6xl" lang="{{ $contentLocale }}">{{ $item->translate('name') }}</h1>
                @if ($item->brand || $item->model)
                    <p class="mt-4 text-lg text-sage-200">{{ trim($item->brand.' '.$item->model) }}</p>
                @endif
            </div>
        </header>

        <div class="container-page mt-10 max-w-3xl">
            @unless ($isTranslated)
                <p class="mb-8 rounded-2xl bg-sand-100 p-4 text-sm text-bark-700" role="note">{{ __('site.equipment.not_translated') }}</p>
            @endunless

            @if ($excerpt = $item->translate('excerpt'))
                <p class="-rotate-1 border-l-4 border-olive-300 pl-5 font-display text-2xl text-forest-700" lang="{{ $contentLocale }}">{{ $excerpt }}</p>
            @endif

            @if ($specs = $item->specList())
                <dl class="mt-8 grid gap-x-6 gap-y-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sage-200 sm:grid-cols-2" aria-label="{{ __('site.equipment.specs') }}">
                    @foreach ($specs as $spec)
                        <div class="flex justify-between gap-4 border-b border-sage-200/60 py-1.5 last:border-0 sm:[&:nth-last-child(2):nth-child(odd)]:border-0">
                            <dt class="text-moss-600">{{ $spec['label'] }}</dt>
                            <dd class="text-right font-semibold">{{ $spec['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            {{-- Body is HTML from the admin-only CMS editor. --}}
            <div class="gear-story prose prose-lg mt-8 max-w-none prose-headings:font-display prose-a:text-moss-600" lang="{{ $contentLocale }}">
                {!! $item->bodyHtml() !!}
            </div>

            <a href="{{ lroute('equipment') }}" class="btn-outline mt-12">← {{ __('site.equipment.back') }}</a>
        </div>
    </article>
</x-layouts.app>
