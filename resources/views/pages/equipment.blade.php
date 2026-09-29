@php
    $kg = fn ($grams) => \Illuminate\Support\Number::format($grams / 1000, precision: 2, locale: app()->getLocale()).' kg';
@endphp
@php
    $photo = ($path = \App\Support\Settings::get('gear_image')) ? \App\Support\MediaStorage::url($path) : null;
@endphp
<x-layouts.app :title="__('site.equipment.title')" :image="$photo">
    <x-page-header :title="__('site.equipment.title')" :lead="__('site.equipment.lead')">
        <dl class="mt-8 flex flex-wrap gap-3">
            <div class="rounded-2xl bg-forest-700/70 px-4 py-3">
                <dt class="text-xs font-semibold tracking-wide text-fern-300 uppercase">{{ __('site.equipment.base_weight') }}</dt>
                <dd class="font-display text-xl text-white">{{ $kg($baseWeight) }} <span class="text-sm text-sage-200">· {{ __('site.equipment.target') }}</span></dd>
            </div>
            <div class="rounded-2xl bg-forest-700/70 px-4 py-3">
                <dt class="text-xs font-semibold tracking-wide text-fern-300 uppercase">{{ __('site.equipment.worn_weight') }}</dt>
                <dd class="font-display text-xl text-white">{{ $kg($wornWeight) }}</dd>
            </div>
        </dl>
    </x-page-header>

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
                <ul class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $item)
                        <li @class(['rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sage-200 transition hover:-translate-y-0.5 hover:shadow-md', 'opacity-60' => $item->status === $replaced]) data-reveal style="--i: {{ min($loop->index, 6) }}">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <span class="font-display text-lg font-semibold">{{ $item->translate('name') }}</span>
                                    @if ($item->brand || $item->model)
                                        <span class="text-moss-600">— {{ trim($item->brand.' '.$item->model) }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    @if ($item->weight_g)
                                        <span class="font-semibold">{{ \Illuminate\Support\Number::format($item->weight_g, locale: app()->getLocale()) }} g</span>
                                    @endif
                                    @if ($item->price)
                                        <span class="text-moss-600">{{ \Illuminate\Support\Number::currency($item->price, 'EUR', app()->getLocale()) }}</span>
                                    @endif
                                    @if ($item->is_worn)
                                        <span class="badge bg-sand-100 text-bark-700">{{ __('site.equipment.worn') }}</span>
                                    @endif
                                    <span class="badge">{{ $item->status->getLabel() }}</span>
                                </div>
                            </div>
                            @if ($reason = $item->translate('reason'))
                                <p class="mt-2 text-sm text-forest-700"><strong>{{ __('site.equipment.why') }}:</strong> {{ $reason }}</p>
                            @endif
                            @if ($review = $item->translate('review'))
                                <p class="mt-1 text-sm text-forest-700"><strong>{{ __('site.equipment.review') }}:</strong> {{ $review }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-sage-200 p-8 text-center text-moss-600">{{ __('site.equipment.empty') }}</p>
        @endforelse
    </div>
</x-layouts.app>
