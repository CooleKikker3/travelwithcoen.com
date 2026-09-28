<?php

namespace App\Filament\Resources\TrackingPoints\Tables;

use App\Filament\Support\Options;
use App\Models\TrackingPoint;
use App\Services\GpxImporter;
use App\Services\TrackingRecorder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TrackingPointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('recorded_at', 'desc')
            ->columns([
                TextColumn::make('recorded_at')->label('Tijdstip')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('received_at')->since()->label('Ontvangen'),
                TextColumn::make('public_from')->label('Openbaar vanaf')
                    ->state(fn (TrackingPoint $record) => $record->publicFrom())
                    ->dateTime('j M Y, H:i')
                    ->description(fn (TrackingPoint $record) => $record->publicFrom()->isPast() ? 'public' : 'verborgen voor bezoekers'),
                TextColumn::make('latitude')->label('Breedtegraad'),
                TextColumn::make('longitude')->label('Lengtegraad'),
                TextColumn::make('altitude')->label('Hoogte')->suffix(' m')->placeholder('—'),
                TextColumn::make('source')->label('Bron')->badge(),
                TextColumn::make('country.iso_code')->label('Land'),
            ])
            ->filters([
                SelectFilter::make('source')->label('Bron')->options(fn () => TrackingPoint::distinct()->pluck('source', 'source')->all()),
                SelectFilter::make('country_id')->label('Land')->options(fn () => Options::countries()),
            ])
            ->headerActions([
                Action::make('importGpx')
                    ->label('GPX importeren')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('file')->label('GPX-bestand (punten met tijden)')->disk('local')->directory('gpx/tracking')->required()->rules(['extensions:gpx']),
                        Select::make('country_id')->label('Land')->options(fn () => Options::countries())->searchable()
                            ->helperText('Leeg = het land dat op "loop ik nu" staat.'),
                        TextInput::make('source')->label('Bron')->default('gpx')->required()->alphaDash()->maxLength(30),
                    ])
                    ->action(function (array $data) {
                        $points = app(GpxImporter::class)->parse(Storage::disk('local')->get($data['file']));
                        $stored = app(TrackingRecorder::class)->store($points, $data['source'], $data['country_id'] ?? null);

                        Notification::make()->success()->title("{$stored} tracking points imported")->body('Punten zonder tijd of die er al zijn, worden overgeslagen.')->send();
                    }),
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
