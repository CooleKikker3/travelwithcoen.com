<?php

namespace App\Filament\Blocks;

use App\Support\MediaStorage;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
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
        return 'Image with caption';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Image')
            ->schema([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->disk(MediaStorage::diskName())
                    // Resize in the browser first: much smaller uploads on a weak connection.
                    ->imageResizeTargetWidth('2400')
                    ->imageResizeTargetHeight('2400')
                    ->imageResizeMode('contain')
                    ->imageResizeUpscale(false)
                    ->directory('articles/images')
                    ->maxSize(20480)
                    ->required()
                    ->helperText('Also added to the gallery. Location data is removed.'),
                TextInput::make('caption')
                    ->placeholder('e.g. Walking on a sandy road near Berlin')
                    ->maxLength(300),
                TextInput::make('alt')
                    ->label('Description for screen readers (optional)')
                    ->maxLength(300),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Image'.(filled($config['caption'] ?? null) ? ': '.$config['caption'] : '');
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
        ])->render();
    }
}
