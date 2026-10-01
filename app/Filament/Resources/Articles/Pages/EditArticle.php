<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Pages\InstaStory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('story')->label('Insta-story')->icon('heroicon-o-camera')->color('gray')
                ->url(fn () => InstaStory::getUrl(['article' => $this->record->id])),
            DeleteAction::make(),
        ];
    }
}
