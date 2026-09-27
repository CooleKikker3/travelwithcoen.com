<x-filament-panels::page>
    <p class="text-sm text-gray-500">Change any fixed text of the website. Leave a field empty (or unchanged) to use the default text.</p>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">Save texts</x-filament::button>
    </form>
</x-filament-panels::page>
