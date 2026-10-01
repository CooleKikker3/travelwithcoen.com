{{-- Clicks on Instagram story links (StoryClicks widget). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Insta-story-klikken" description="Hoe vaak er op de link in je stories is getikt.">
        <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1rem">
            <div><div style="font-size:.875rem;opacity:.7">Totaal</div><div style="font-size:1.75rem;font-weight:700">{{ $total }}</div></div>
            <div><div style="font-size:.875rem;opacity:.7">Afgelopen 7 dagen</div><div style="font-size:1.75rem;font-weight:700">{{ $week }}</div></div>
        </div>
        @if ($articles->isEmpty())
            <p style="font-size:.875rem;opacity:.7">Nog geen klikken. Maak bij een artikel een Insta-story en zet de gekopieerde link in de link-sticker.</p>
        @else
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;opacity:.7">
                        <th style="padding:.4rem 0">Artikel</th>
                        <th style="padding:.4rem .5rem;text-align:right">Totaal</th>
                        <th style="padding:.4rem .5rem;text-align:right">7 dagen</th>
                        <th style="padding:.4rem 0;text-align:right">Laatste klik</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr style="border-top:1px solid rgb(128 128 128 / .2)">
                            <td style="padding:.4rem 0">{{ $article->translate('title', 'nl') }}</td>
                            <td style="padding:.4rem .5rem;text-align:right;font-weight:600">{{ $article->story_clicks_count }}</td>
                            <td style="padding:.4rem .5rem;text-align:right">{{ $article->week_count }}</td>
                            <td style="padding:.4rem 0;text-align:right">{{ \Illuminate\Support\Carbon::parse($article->story_clicks_max_clicked_at)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
