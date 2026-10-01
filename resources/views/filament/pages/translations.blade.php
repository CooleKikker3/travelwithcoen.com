{{-- Checking automatic translations (Translations page). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
@php
    ['pending' => $pending, 'failed' => $failed] = $this->queue();
    $empty = $this->items()->isEmpty();
@endphp
<x-filament-panels::page>
    @if ($pending)
        <x-filament::section>
            <p style="font-size:.875rem">{{ $pending === 1 ? '1 tekst wordt' : "{$pending} teksten worden" }} nog vertaald. Dat gebeurt op de server, binnen een paar minuten; ververs de pagina straks.</p>
        </x-filament::section>
    @endif

    @if ($failed->isNotEmpty())
        <x-filament::section heading="Vertalen lukte niet" icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <ul style="font-size:.875rem;line-height:1.6">
                @foreach ($failed as $translation)
                    <li>{{ $translation->itemLabel() }} · {{ $translation->fieldLabel() }}: <span style="opacity:.7">{{ $translation->error }}</span></li>
                @endforeach
            </ul>
            <x-filament::button wire:click="retryFailed" color="gray" icon="heroicon-o-arrow-path" style="margin-top:.75rem">Opnieuw proberen</x-filament::button>
        </x-filament::section>
    @endif

    @if ($empty && ! $pending && $failed->isEmpty())
        <x-filament::section>
            <p style="font-size:.875rem;opacity:.8">Alles is nagekeken. Schrijf je iets nieuws in het Nederlands, dan staat de Engelse vertaling hier vanzelf klaar om te controleren.</p>
        </x-filament::section>
    @endif

    @unless ($empty)
        <p style="font-size:.875rem;opacity:.8">Links jouw Nederlandse tekst, rechts de vertaling. Pas aan wat niet klopt en keur goed: pas dan staat het Engels online.</p>
        {{ $this->form }}
    @endunless
</x-filament-panels::page>
