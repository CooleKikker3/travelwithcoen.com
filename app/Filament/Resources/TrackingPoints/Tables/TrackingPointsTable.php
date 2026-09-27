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
                TextColumn::make('recorded_at')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('received_at')->since()->label('Received'),
                TextColumn::make('public_from')
                    ->state(fn (TrackingPoint $record) => $record->publicFrom())
                    ->dateTime('j M Y, H:i')
                    ->description(fn (TrackingPoint $record) => $record->publicFrom()->isPast() ? 'public' : 'hidden from public'),
                TextColumn::make('latitude'),
                TextColumn::make('longitude'),
                TextColumn::make('altitude')->suffix(' m')->placeholder('—'),
                TextColumn::make('source')->badge(),
                TextColumn::make('country.iso_code')->label('Country'),
            ])
            ->filters([
                SelectFilter::make('source')->options(fn () => TrackingPoint::distinct()->pluck('source', 'source')->all()),
                SelectFilter::make('country_id')->label('Country')->options(fn () => Options::countries()),
            ])
            ->headerActions([
                Action::make('importGpx')
                    ->label('Import GPX')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('file')->label('GPX file (points need timestamps)')->disk('local')->directory('gpx/tracking')->required()->rules(['extensions:gpx']),
                        Select::make('country_id')->label('Country')->options(fn () => Options::countries())->searchable()
                            ->helperText('Empty = the country marked "walking here now".'),
                        TextInput::make('source')->default('gpx')->required()->alphaDash()->maxLength(30),
                    ])
                    ->action(function (array $data) {
                        $points = app(GpxImporter::class)->parse(Storage::disk('local')->get($data['file']));
                        $stored = app(TrackingRecorder::class)->store($points, $data['source'], $data['country_id'] ?? null);

                        Notification::make()->success()->title("{$stored} tracking points imported")->body('Points without a timestamp or already imported are skipped.')->send();
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
