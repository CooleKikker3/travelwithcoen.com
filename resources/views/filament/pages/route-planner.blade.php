<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-4">
        <label class="space-y-1 text-sm font-medium">
            <span>Country</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="countryId">
                    <option value="">— choose —</option>
                    @foreach ($this->countryOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="space-y-1 text-sm font-medium">
            <span>Type</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="type">
                    <option value="planned">Planned</option>
                    <option value="actual">Actual (walked)</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="space-y-1 text-sm font-medium">
            <span>Name (internal)</span>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="name" placeholder="e.g. Lisse → Den Helder" />
            </x-filament::input.wrapper>
        </label>
        <label class="space-y-1 text-sm font-medium">
            <span>Line between waypoints</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="routing" data-routing>
                    <option value="hiking">Follow walking paths (BRouter)</option>
                    <option value="straight">Straight lines</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

    <p class="text-sm text-gray-500">
        Click the map to add waypoints. Drag a waypoint to move it, click it to remove it.
        Grey dashed lines are your other routes, for reference.
    </p>

    <div wire:ignore>
        <div data-route-planner data-initial="{{ json_encode($this->initialData()) }}" style="height: 68vh; border-radius: 0.75rem; z-index: 0;"></div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" color="gray" data-action="undo" icon="heroicon-o-arrow-uturn-left">Undo</x-filament::button>
        <x-filament::button type="button" color="gray" data-action="clear" icon="heroicon-o-trash">Clear</x-filament::button>
        <x-filament::button type="button" data-action="save" icon="heroicon-o-check">Save route</x-filament::button>
        <span class="ms-2 text-sm font-semibold" data-distance></span>
        <span class="text-sm text-gray-500" data-status></span>
    </div>

    @vite('resources/js/route-planner.js')
</x-filament-panels::page>
