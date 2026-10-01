<?php

namespace App\Filament\Blocks;

use App\Support\MediaStorage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * "Image with caption" block for article texts. Images placed this way also appear
 * in the gallery, linked to the article (see ArticleGallerySync).
 */
class ImageBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'image';
    }

    public static function getLabel(): string
    {
        return 'Afbeelding met bijschrift';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Afbeelding')
            ->schema([
                FileUpload::make('path')
                    ->label('Afbeelding')
                    ->image()
                    ->disk(MediaStorage::diskName())
                    // Resize in the browser first: much smaller uploads on a weak connection.
                    ->imageResizeTargetWidth('2400')
                    ->imageResizeTargetHeight('2400')
                    ->imageResizeMode('contain')
                    ->imageResizeUpscale(false)
                    ->directory(static::directory())
                    ->maxSize(20480)
                    ->required()
                    ->helperText(static::uploadHelp()),
                TextInput::make('caption')->label('Bijschrift')
                    ->placeholder('bijv. Lopen over een zandweg bij Berlijn')
                    ->maxLength(300),
                TextInput::make('alt')
                    ->label('Beschrijving voor schermlezers (optioneel)')
                    ->maxLength(300),
                Toggle::make('sensitive')
                    ->label('Gevoelige inhoud')
                    ->helperText('Bijv. een blessure: vervaagd met een waarschuwing tot de lezer hem wil zien.'),
            ]);
    }

    /** Where uploaded images go (media:prune keeps the ones still used). */
    protected static function directory(): string
    {
        return 'articles/images';
    }

    protected static function uploadHelp(): string
    {
        return 'Komt ook in de galerij. Locatiegegevens worden verwijderd.';
    }

    public static function getPreviewLabel(array $config): string
    {
        return (empty($config['sensitive']) ? 'Image' : 'Image (sensitive)').(filled($config['caption'] ?? null) ? ': '.$config['caption'] : '');
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return self::render($config, 'max-height: 14rem');
    }

    public static function toHtml(array $config, array $data): ?string
    {
        return self::render($config);
    }

    /** The stored path of an image block config (FileUpload may keep it as an array). */
    public static function path(array $config): ?string
    {
        $path = $config['path'] ?? null;

        return is_array($path) ? (array_values($path)[0] ?? null) : $path;
    }

    private static function render(array $config, ?string $imageStyle = null): ?string
    {
        if (! $path = self::path($config)) {
            return null;
        }

        return view('components.article-image', [
            'url' => MediaStorage::url($path),
            'caption' => $config['caption'] ?? null,
            'alt' => $config['alt'] ?? $config['caption'] ?? '',
            'imageStyle' => $imageStyle,
            // Blurred in the editor preview too, so it is clear which images carry a warning.
            'sensitive' => ! empty($config['sensitive']),
        ])->render();
    }
}
