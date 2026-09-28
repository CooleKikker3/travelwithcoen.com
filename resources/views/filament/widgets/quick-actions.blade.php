{{-- Dashboard buttons in even grids (inline styles: Filament's CSS has no custom Tailwind classes). --}}
@php
    $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.75rem';
@endphp
<x-filament-widgets::widget>
    <div style="display:grid;gap:1.5rem">
        <x-filament::section heading="Vandaag">
            <div style="{{ $grid }}">
                {{ $this->startDayAction }}
                {{ $this->endDayAction }}
                {{ $this->restDayAction }}
            </div>
        </x-filament::section>

        <x-filament::section heading="Snel naar">
            <div style="{{ $grid }}">
                @foreach ([
                    ['Nieuw artikel', \App\Filament\Resources\Articles\ArticleResource::getUrl('create'), 'heroicon-o-document-plus'],
                    ["Foto's uploaden", \App\Filament\Resources\GalleryItems\GalleryItemResource::getUrl('index'), 'heroicon-o-photo'],
                    ['Gebeurtenis toevoegen', \App\Filament\Resources\JourneyEvents\JourneyEventResource::getUrl('create'), 'heroicon-o-flag'],
                    ['Uitgave toevoegen', \App\Filament\Resources\Expenses\ExpenseResource::getUrl('create'), 'heroicon-o-banknotes'],
                    ['Reisdag toevoegen', \App\Filament\Resources\JourneyDays\JourneyDayResource::getUrl('create'), 'heroicon-o-calendar-days'],
                    ['Route tekenen', \App\Filament\Pages\RoutePlanner::getUrl(), 'heroicon-o-map'],
                ] as [$label, $url, $icon])
                    <x-filament::button tag="a" :href="$url" :icon="$icon" color="gray" size="lg" style="width:100%">{{ $label }}</x-filament::button>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
