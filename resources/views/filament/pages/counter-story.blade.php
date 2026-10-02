{{-- Counter story generator (CounterStory + resources/js/insta-story.js). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
<x-filament-panels::page>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:800|caveat:700|nunito:700,800&display=swap" rel="stylesheet">
    <style>
        .twc-choice { display: flex; align-items: center; gap: .4rem; padding: .4rem .75rem; border-radius: 9999px; font-size: .875rem; font-weight: 600; cursor: pointer;
            border: 1px solid rgb(0 0 0 / .12); transition: background .15s, border-color .15s; }
        .dark .twc-choice { border-color: rgb(255 255 255 / .15); }
        .twc-choice:has(input:checked) { background: rgb(22 163 74 / .12); border-color: rgb(22 163 74); }
        .twc-choice:has(input:disabled) { display: none; }
        .twc-choice input { accent-color: rgb(22 163 74); }
    </style>

    @php($data = $this->storyData())
    <div wire:ignore data-insta-story data-story="{{ json_encode($data) }}" style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start">
        <canvas data-canvas width="1080" height="1920" style="width:min(100%,20rem);aspect-ratio:9/16;border-radius:1rem;box-shadow:0 10px 30px rgb(0 0 0 / .25);background:#1c3a26"></canvas>

        <div style="display:grid;gap:1.25rem;flex:1;min-width:16rem;max-width:30rem">
            <label class="twc-choice" style="border-radius:.75rem;align-items:flex-start">
                <input type="checkbox" data-delayed checked style="margin-top:.2rem">
                <span>Met vertraging, zoals bezoekers het zien ({{ $data['delayDays'] }} {{ $data['delayDays'] == 1 ? 'dag' : 'dagen' }} terug)
                    <span data-live-warning style="display:block;font-weight:400;font-size:.8rem;color:rgb(161 98 7)">Uit: de story laat zien waar je <strong>nu</strong> bent.</span>
                </span>
            </label>

            <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
                <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                    <span>Teller</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select data-mode>
                            <option value="today" @selected($mode === 'today')>Vandaag</option>
                            <option value="total" @selected($mode === 'total')>Tot nu toe (all-time)</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                    <span>Ontwerp</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select data-design>
                            <option value="big">Grote getallen</option>
                            <option value="photo">Op de foto</option>
                            <option value="notebook">Notitieboekje</option>
                            <option value="crow">Hemelsbreed</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>

            {{-- Photo: automatic, from the gallery, or uploaded (only used to draw the story, never saved). --}}
            <fieldset style="display:grid;gap:.5rem">
                <legend style="font-size:.875rem;font-weight:500;margin-bottom:.5rem">Foto</legend>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    <x-filament::button data-photo-auto color="gray" size="sm" icon="heroicon-o-sparkles">Automatisch</x-filament::button>
                    <x-filament::button data-photo-gallery color="gray" size="sm" icon="heroicon-o-photo">Uit de galerij</x-filament::button>
                    <x-filament::button data-photo-upload color="gray" size="sm" icon="heroicon-o-arrow-up-tray">Uploaden</x-filament::button>
                    <input type="file" accept="image/*" data-photo-file hidden>
                </div>
                <div data-photo-grid hidden style="display:grid;grid-template-columns:repeat(auto-fill,minmax(4.5rem,1fr));gap:.4rem;max-height:15rem;overflow-y:auto;padding:.25rem">
                    @forelse ($data['gallery'] as $url)
                        <button type="button" data-pick="{{ $url }}" style="aspect-ratio:1;border-radius:.5rem;overflow:hidden;border:2px solid transparent;padding:0">
                            <img src="{{ $url }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
                        </button>
                    @empty
                        <p style="font-size:.875rem;opacity:.7">Nog geen foto's in de galerij.</p>
                    @endforelse
                </div>
                <p style="font-size:.75rem;opacity:.7">Een geüploade foto wordt alleen gebruikt voor deze story en niet opgeslagen.</p>
            </fieldset>

            <fieldset style="display:grid;gap:.5rem">
                <legend style="font-size:.875rem;font-weight:500;margin-bottom:.5rem">Wat staat erop</legend>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    @foreach ([
                        'headline' => 'Kop (dag / tot nu toe)', 'sub' => 'Land en datum', 'route' => 'Van → naar', 'photo' => 'Foto',
                        'km' => 'Kilometers', 'time' => 'Looptijd', 'hours' => 'Uren gelopen', 'days' => 'Dagen onderweg', 'countries' => 'Landen',
                        'tent' => 'Nachten in de tent', 'progress' => 'Procent naar Hanoi', 'crow_day' => 'Hemelsbreed vandaag',
                        'crow_home' => 'Hemelsbreed van huis', 'crow_hanoi' => 'Hemelsbreed tot Hanoi', 'crowline' => 'Lijntje Lisse → Hanoi',
                        'note' => 'Handgeschreven tekst', 'sticker' => 'Vak voor link-sticker', 'brand' => 'Naam en website',
                    ] as $value => $label)
                        <label class="twc-choice"><input type="checkbox" value="{{ $value }}" data-part> {{ $label }}</label>
                    @endforeach
                </div>
                <p style="font-size:.75rem;opacity:.7">Alleen wat er is staat in de lijst (bijv. geen looptijd als je die niet hebt ingevuld).</p>
            </fieldset>

            <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr">
                <label style="display:grid;gap:.25rem;font-size:.875rem;font-weight:500">
                    <span>Taal</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select data-locale>
                            <option value="nl">Nederlands</option>
                            <option value="en">Engels</option>
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
                <li>Kies vandaag of tot nu toe, een ontwerp en wat erop staat (wordt onthouden).</li>
                <li>Kopieer de link (naar de pagina Reis).</li>
                <li>Deel de afbeelding naar Instagram → Story (of download hem).</li>
                <li>Kies in Instagram de sticker <strong>Link</strong>, plak de link en zet de sticker op het gestippelde vak.</li>
            </ol>
        </div>
    </div>

    @vite('resources/js/insta-story.js')
</x-filament-panels::page>
