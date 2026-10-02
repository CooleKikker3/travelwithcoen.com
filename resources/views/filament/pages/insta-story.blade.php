{{-- Instagram story generator (InstaStory + resources/js/insta-story.js). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
<x-filament-panels::page>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:800|caveat:700|nunito:700,800&display=swap" rel="stylesheet">
    <style>
        .twc-choice { display: flex; align-items: center; gap: .4rem; padding: .4rem .75rem; border-radius: 9999px; font-size: .875rem; font-weight: 600; cursor: pointer;
            border: 1px solid rgb(0 0 0 / .12); transition: background .15s, border-color .15s; }
        .dark .twc-choice { border-color: rgb(255 255 255 / .15); }
        .twc-choice:has(input:checked) { background: rgb(22 163 74 / .12); border-color: rgb(22 163 74); }
        .twc-choice:has(input:disabled) { opacity: .4; cursor: not-allowed; }
        .twc-choice input { accent-color: rgb(22 163 74); }
    </style>

    @php($data = $this->storyData())
    <div wire:ignore data-insta-story data-story="{{ json_encode($data) }}" style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start">
        <canvas data-canvas width="1080" height="1920" style="width:min(100%,20rem);aspect-ratio:9/16;border-radius:1rem;box-shadow:0 10px 30px rgb(0 0 0 / .25);background:#1c3a26"></canvas>

        <div style="display:grid;gap:1.25rem;flex:1;min-width:16rem;max-width:30rem">
            <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                <span>Ontwerp</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select data-design>
                        @foreach ([
                            'polaroid' => 'Polaroid — foto met tape op de golven',
                            'photo' => 'Grote foto — foto over de hele story',
                            'simple' => 'Gewoon de foto — alleen een handgeschreven regel',
                            'journal' => 'Dagboek — schriftblad met stempel',
                            'quote' => 'Citaat — je eigen woorden, groot',
                            'collage' => 'Collage — meerdere foto\'s uit het verhaal',
                            'route' => 'Routekrabbel — waar ben ik op weg naar Hanoi',
                            'counter' => 'Teller — dag, kilometers, voortgang',
                            'ticket' => 'Kaartje — van Lisse naar Hanoi',
                            'postcard' => 'Ansichtkaart — groeten uit…',
                        ] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <fieldset style="display:grid;gap:.5rem">
                <legend style="font-size:.875rem;font-weight:500;margin-bottom:.5rem">Wat staat erop</legend>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    @foreach (['title' => 'Titel', 'excerpt' => 'Samenvatting', 'place' => 'Dag en land', 'date' => 'Datum', 'km' => 'Kilometers', 'progress' => 'Voortgang naar Hanoi', 'note' => 'Handgeschreven tekst', 'sticker' => 'Vak voor link-sticker', 'brand' => 'Naam en website'] as $value => $label)
                        <label class="twc-choice"><input type="checkbox" value="{{ $value }}" data-part> {{ $label }}</label>
                    @endforeach
                </div>
                <p style="font-size:.75rem;opacity:.7">Grijs = staat niet bij dit verhaal (bijv. nog geen kilometers) of past niet in dit ontwerp.</p>
            </fieldset>

            <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
                <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                    <span>Taal</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select data-locale>
                            @foreach (array_keys($data['locales']) as $locale)
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
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                <x-filament::button data-copy icon="heroicon-o-link" color="gray">1. Link kopiëren</x-filament::button>
                <x-filament::button data-share icon="heroicon-o-share">2. Delen</x-filament::button>
                <x-filament::button data-download icon="heroicon-o-arrow-down-tray" color="gray">Downloaden</x-filament::button>
                <x-filament::button data-tape icon="heroicon-o-arrow-path" color="gray">Andere variatie</x-filament::button>
            </div>
            <p data-status style="font-size:.875rem;color:rgb(22 163 74);min-height:1.25rem"></p>

            <ol style="font-size:.875rem;line-height:1.6;list-style:decimal;padding-left:1.25rem;opacity:.8">
                <li>Kies een ontwerp en wat erop staat (je keuze wordt onthouden per ontwerp).</li>
                <li>Kopieer de link.</li>
                <li>Deel de afbeelding naar Instagram → Story (of download hem).</li>
                <li>Kies in Instagram de sticker <strong>Link</strong>, plak de link en zet de sticker op het gestippelde vak.</li>
            </ol>
        </div>
    </div>

    @vite('resources/js/insta-story.js')
</x-filament-panels::page>
