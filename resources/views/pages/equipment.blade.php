@php
    $photo = ($path = \App\Support\Settings::get('gear_image')) ? \App\Support\MediaStorage::url($path) : null;
@endphp
<x-layouts.app :title="__('site.equipment.title')" :image="$photo">
    <x-page-header :title="__('site.equipment.title')" :lead="__('site.equipment.lead')" />

    @if ($photo)
        {{-- Me with my gear: a polaroid over the edge of the header. --}}
        <div class="container-page relative z-10 -mt-16">
            <figure class="polaroid mx-auto max-w-2xl rounded-md bg-white p-3 pb-4 shadow-xl ring-1 ring-sage-200/70" style="--tilt: -1.5deg" data-reveal>
                <img src="{{ $photo }}" alt="{{ __('site.equipment.hand') }}" class="w-full rounded-sm object-cover" loading="lazy">
                <figcaption class="mt-3 text-center font-hand text-2xl text-forest-800">{{ __('site.equipment.hand') }}</figcaption>
            </figure>
        </div>
    @endif

    <div class="container-page mt-14 space-y-14">
        @forelse ($categories as $category => $items)
            <section>
                <h2 class="text-3xl font-extrabold" data-reveal>{{ \App\Enums\EquipmentCategory::from($category)->getLabel() }}</h2>
                <ul class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        <li class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sage-200 transition hover:-translate-y-1 hover:shadow-lg" data-reveal style="--i: {{ min($loop->index, 6) }}">
                            <div class="relative aspect-[4/3] overflow-hidden bg-forest-800">
                                @if ($item->coverUrl())
                                    <img src="{{ $item->coverUrl() }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                                @else
                                    <div class="topo absolute inset-0" aria-hidden="true"></div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="font-display text-xl font-bold">
                                    <a href="{{ $item->url() }}" class="focus:outline-none after:absolute after:inset-0">{{ $item->translate('name') }}</a>
                                </h3>
                                @if ($item->brand || $item->model)
                                    <p class="text-sm text-moss-600">{{ trim($item->brand.' '.$item->model) }}</p>
                                @endif
                                @if ($excerpt = $item->translate('excerpt'))
                                    <p class="mt-2 text-sm text-forest-700">{{ $excerpt }}</p>
                                @endif
                                <span class="mt-auto pt-4 text-sm font-semibold text-moss-600 transition group-hover:translate-x-1" aria-hidden="true">{{ __('site.equipment.read_more') }} →</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.equipment.empty') }}</p>
        @endforelse
    </div>
</x-layouts.app>
