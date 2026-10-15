<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Handy links for on the road and for running the site, in groups. Links open in a new tab. */
class UsefulLinks extends Page
{
    protected string $view = 'filament.pages.useful-links';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 98;

    protected static ?string $navigationLabel = 'Handige links';

    protected static ?string $title = 'Handige links';

    /** @return array<string, list<array{0: string, 1: string, 2: string}>> group => [title, url, description] */
    public function groups(): array
    {
        return [
            'Maken en delen' => [
                ['Map Animator', 'https://mapanimator.baldinger.se/', 'Geanimeerde kaartjes van een route, bijvoorbeeld voor een video of Insta-story.'],
                ['YouTube Studio', 'https://studio.youtube.com/', 'Video\'s uploaden; nieuwe video\'s komen vanzelf in de galerij.'],
                ['Instagram', 'https://www.instagram.com/', 'Stories delen (gebruik de Insta-story bij een artikel of de Teller-story).'],
            ],
            'Onderweg' => [
                ['Reisadviezen (Nederland Wereldwijd)', 'https://www.nederlandwereldwijd.nl/reisadvies', 'Officiële reisadviezen per land.'],
                ['BRouter', 'https://brouter.de/brouter-web/', 'Routes plannen over paden; dezelfde routeplanner als in "Route tekenen".'],
                ['Garmin Explore', 'https://explore.garmin.com/', 'Je inReach: berichten, tracking en MapShare.'],
            ],
            'De website draaien' => [
                ['De website', config('app.url'), 'Zoals bezoekers hem zien.'],
                ['Google Analytics', 'https://analytics.google.com/', 'Bezoekers en populaire pagina\'s (alleen van wie cookies accepteert).'],
                ['Kosten vertalingen (Google Cloud)', config('services.google_translate.billing_url'), 'Wat de automatische vertalingen kosten.'],
                ['Cloudflare', 'https://dash.cloudflare.com/', 'DNS, beveiliging en de foto-opslag (R2).'],
                ['TransIP', 'https://www.transip.nl/cp/', 'Domeinen en de server.'],
                ['GitHub', 'https://github.com/CooleKikker3/travelwithcoen.com', 'De code van de website.'],
            ],
        ];
    }
}
