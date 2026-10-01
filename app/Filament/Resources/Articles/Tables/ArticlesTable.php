<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Filament\Pages\InstaStory;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Titel')
                    ->state(fn (Article $record) => $record->translate('title', 'nl'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('tags')->label('Tags')->badge()->separator(',')->toggleable(),
                IconColumn::make('english')
                    ->label('EN')
                    ->state(fn (Article $record) => $record->isTranslated('en'))
                    ->boolean(),
                TextColumn::make('country')->label('Land')
                    ->state(fn (Article $record) => $record->country?->translate('name', 'en')),
                TextColumn::make('published_at')->label('Gepubliceerd')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Soort')->options(ArticleType::class),
                SelectFilter::make('status')->label('Status')->options(ArticleStatus::class),
            ])
            ->recordActions([
                Action::make('story')->label('Insta-story')->icon('heroicon-o-camera')->color('gray')
                    ->url(fn (Article $record) => InstaStory::getUrl(['article' => $record->id])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
