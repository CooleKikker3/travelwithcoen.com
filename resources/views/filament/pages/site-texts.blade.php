<x-filament-panels::page>
    <p class="text-sm text-gray-500">Pas hier de vaste teksten van de website aan. Laat een veld leeg om de standaardtekst te gebruiken.</p>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">Teksten opslaan</x-filament::button>
    </form>
</x-filament-panels::page>
