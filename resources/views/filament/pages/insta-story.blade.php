{{-- Instagram story generator (InstaStory + resources/js/insta-story.js). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
<x-filament-panels::page>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:800|caveat:700|nunito:700,800&display=swap" rel="stylesheet">

    <div wire:ignore data-insta-story data-story="{{ json_encode($this->storyData()) }}" style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start">
        <canvas data-canvas width="1080" height="1920" style="width:min(100%,20rem);aspect-ratio:9/16;border-radius:1rem;box-shadow:0 10px 30px rgb(0 0 0 / .25);background:#1c3a26"></canvas>

        <div style="display:grid;gap:1rem;flex:1;min-width:16rem;max-width:28rem">
            <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                <span>Taal</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select data-locale>
                        @foreach (array_keys($this->storyData()['locales']) as $locale)
                            <option value="{{ $locale }}">{{ ['nl' => 'Nederlands', 'en' => 'Engels'][$locale] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                <span>Handgeschreven tekst</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" data-note maxlength="30" />
                </x-filament::input.wrapper>
            </label>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                <x-filament::button data-copy icon="heroicon-o-link" color="gray">1. Link kopiëren</x-filament::button>
                <x-filament::button data-share icon="heroicon-o-share">2. Delen</x-filament::button>
                <x-filament::button data-download icon="heroicon-o-arrow-down-tray" color="gray">Downloaden</x-filament::button>
                <x-filament::button data-tape icon="heroicon-o-arrow-path" color="gray">Ander tapeje</x-filament::button>
            </div>
            <p data-status style="font-size:.875rem;color:rgb(22 163 74);min-height:1.25rem"></p>

            <ol style="font-size:.875rem;line-height:1.6;list-style:decimal;padding-left:1.25rem;opacity:.8">
                <li>Kopieer de link.</li>
                <li>Deel de afbeelding naar Instagram → Story (of download hem).</li>
                <li>Kies in Instagram de sticker <strong>Link</strong>, plak de link en zet de sticker op het gestippelde vak.</li>
            </ol>
        </div>
    </div>

    @vite('resources/js/insta-story.js')
</x-filament-panels::page>
