<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
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
                TextColumn::make('title')
                    ->state(fn (Article $record) => $record->translate('title', 'en'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('tags')->badge()->separator(',')->toggleable(),
                IconColumn::make('dutch')
                    ->label('NL')
                    ->state(fn (Article $record) => $record->isTranslated('nl'))
                    ->boolean(),
                TextColumn::make('country')
                    ->state(fn (Article $record) => $record->country?->translate('name', 'en')),
                TextColumn::make('published_at')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(ArticleType::class),
                SelectFilter::make('status')->options(ArticleStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
