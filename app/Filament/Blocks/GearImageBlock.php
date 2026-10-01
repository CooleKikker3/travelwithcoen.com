<?php

namespace App\Filament\Blocks;

/** "Image with caption" in the story of a gear item: same as in articles, but not added to the gallery. */
class GearImageBlock extends ImageBlock
{
    protected static function directory(): string
    {
        return 'equipment/images';
    }

    protected static function uploadHelp(): string
    {
        return 'Staat tussen de tekst bij dit item. Locatiegegevens worden verwijderd.';
    }
}