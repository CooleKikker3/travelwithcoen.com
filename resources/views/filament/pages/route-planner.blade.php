{{-- Route editor (RoutePlanner + resources/js/route-planner.js). Layout with inline styles: Filament's CSS has no custom Tailwind classes. --}}
@php
    $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.75rem';
    $field = 'display:grid;gap:.25rem;font-size:.875rem;font-weight:500';
    $data = $this->initialData();
@endphp
<x-filament-panels::page>
    <div wire:ignore data-route-editor data-initial="{{ json_encode($data) }}" style="display:grid;gap:1rem">
        {{-- Which route piece --}}
        <div style="{{ $grid }}">
            <label style="{{ $field }}">
                <span>Routestuk</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select data-switch>
                        <option value="">+ Nieuw routestuk</option>
                        @foreach ($this->routeOptions() as $id => $label)
                            <option value="{{ $id }}" @selected($id === $data['routeId'])>{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        {{-- Unsaved work on this device (filled by the script) --}}
        <div data-draft-banner hidden style="padding:.75rem 1rem;border-radius:.75rem;background:#fef3c7;color:#78350f;font-size:.875rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center"></div>

        <x-filament::section heading="Over dit stuk" collapsible :collapsed="(bool) $data['routeId']">
            <div style="display:grid;gap:.75rem">
                <div style="{{ $grid }}">
                    <label style="{{ $field }}">
                        <span>Land</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select data-meta="countryId">
                                <option value="">— kies —</option>
                                @foreach ($this->countryOptions() as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label style="{{ $field }}">
                        <span>Soort</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select data-meta="type">
                                <option value="planned">Gepland</option>
                                <option value="actual">Gelopen</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                </div>
                <label style="display:flex;gap:.5rem;align-items:center;font-size:.875rem;font-weight:500">
                    <input type="checkbox" data-meta="isDraft" style="width:1.1rem;height:1.1rem">
                    Concept (niet zichtbaar op de website)
                </label>
                <div style="{{ $grid }}">
                    <label style="{{ $field }}">
                        <span>Titel (Nederlands)</span>
                        <x-filament::input.wrapper><x-filament::input type="text" data-meta="titleNl" maxlength="150" placeholder="bijv. Nijmeegse Vierdaagse" /></x-filament::input.wrapper>
                    </label>
                    <label style="{{ $field }}">
                        <span>Titel (Engels, optioneel)</span>
                        <x-filament::input.wrapper><x-filament::input type="text" data-meta="titleEn" maxlength="150" placeholder="e.g. Nijmegen Four Days Marches" /></x-filament::input.wrapper>
                    </label>
                </div>
                <div style="{{ $grid }}">
                    <label style="{{ $field }}">
                        <span>Verhaal (Nederlands)</span>
                        <x-filament::input.wrapper><textarea data-meta="descriptionNl" rows="4" maxlength="5000" style="width:100%;border:0;background:transparent;padding:.5rem .75rem;font-size:.875rem" placeholder="Wat is dit stuk, waarom deze route?"></textarea></x-filament::input.wrapper>
                    </label>
                    <label style="{{ $field }}">
                        <span>Verhaal (Engels, optioneel)</span>
                        <x-filament::input.wrapper><textarea data-meta="descriptionEn" rows="4" maxlength="5000" style="width:100%;border:0;background:transparent;padding:.5rem .75rem;font-size:.875rem"></textarea></x-filament::input.wrapper>
                    </label>
                </div>
            </div>
        </x-filament::section>

        {{-- Tools --}}
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
            <x-filament::button type="button" data-action="draw" icon="heroicon-o-pencil">Stuk tekenen</x-filament::button>
            <x-filament::button type="button" color="gray" data-action="gpx" icon="heroicon-o-arrow-up-tray">GPX toevoegen</x-filament::button>
            <input type="file" accept=".gpx,application/gpx+xml" multiple hidden data-gpx-input>
            <x-filament::input.wrapper style="width:auto">
                <x-filament::input.select data-routing title="Lijn tussen getekende punten">
                    <option value="straight">Rechte lijnen</option>
                    <option value="hiking">Wandelpaden volgen</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::button type="button" color="gray" data-action="undo" icon="heroicon-o-arrow-uturn-left">Ongedaan maken</x-filament::button>
        </div>

        <p data-hint style="font-size:.875rem;color:#6b7280;margin:0"></p>

        <div data-map style="height:58vh;min-height:320px;border-radius:.75rem;z-index:0"></div>

        {{-- Parts of this route piece (rendered by the script) --}}
        <div data-segments style="display:grid;gap:.5rem"></div>

        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;position:sticky;bottom:0;padding:.75rem 0;background:inherit;z-index:10">
            <x-filament::button type="button" size="lg" data-action="save" icon="heroicon-o-check">Opslaan</x-filament::button>
            <span data-distance style="font-weight:600"></span>
            <span data-status style="font-size:.875rem;color:#6b7280"></span>
            @if ($data['routeId'])
                <x-filament::button type="button" color="gray" size="sm" wire:click="downloadGpx" icon="heroicon-o-arrow-down-tray">GPX downloaden</x-filament::button>
                <x-filament::button type="button" color="danger" outlined size="sm" wire:click="deleteRoute" wire:confirm="Dit routestuk verwijderen?" icon="heroicon-o-trash" style="margin-inline-start:auto">Verwijderen</x-filament::button>
            @endif
        </div>
    </div>

    @vite('resources/js/route-planner.js')
</x-filament-panels::page>
