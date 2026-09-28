<?php

namespace App\Filament\Widgets;

use App\Enums\CountryStatus;
use App\Enums\DayType;
use App\Enums\Overnight;
use App\Filament\Support\Options;
use App\Filament\Support\TrackingPointSelect;
use App\Models\Country;
use App\Models\GalleryItem;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Support\MediaStorage;
use App\Support\RouteGeometry;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard buttons. "Start/end journey day" work with times you can move back:
 * without signal you often only press them hours or days later.
 */
class QuickActions extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** Started days that have not been ended yet, oldest first. */
    public function openDays(): Collection
    {
        return JourneyDay::whereNotNull('started_at')->whereNull('ended_at')->orderBy('started_at')->get();
    }

    /** Today's date on the device (time zone), not the server's. */
    private function today(): string
    {
        return now(FilamentTimezone::get())->toDateString();
    }

    /** Today already started as a journey day or marked as rest day: those buttons are greyed out. */
    public function todayIsSet(): bool
    {
        return JourneyDay::whereDate('date', $this->today())
            ->where(fn ($q) => $q->whereNotNull('started_at')->orWhere('type', DayType::Rest))
            ->exists();
    }

    public function restDayAction(): Action
    {
        return Action::make('restDay')
            ->label('Markeren als rustdag')
            ->icon('heroicon-o-moon')
            ->color('gray')
            ->size('lg')
            ->extraAttributes(['style' => 'width:100%'])
            ->disabled(fn () => $this->todayIsSet())
            ->modalHeading('Rustdag')
            ->modalDescription('Geen bereik gehad? Kies de dag waarop je rustte.')
            ->modalSubmitActionLabel('Markeren')
            ->schema([
                DatePicker::make('date')->label('Dag')->default(fn () => $this->today())->required(),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())
                    ->default(fn () => Country::where('status', CountryStatus::Current)->value('id')),
                Select::make('overnight')->label('Waar ik sliep')->options(Overnight::class),
            ])
            ->action(function (array $data) {
                $day = JourneyDay::firstOrNew(['date' => $data['date']]);
                $day->fill([
                    'type' => DayType::Rest,
                    'distance_km' => $day->distance_km ?? 0,
                    'country_id' => $data['country_id'] ?: $day->country_id,
                    'overnight' => $data['overnight'] ?? $day->overnight,
                ])->save();

                Notification::make()->success()->title($day->name().' is een rustdag ('.$day->date->translatedFormat('l j F').')')->send();
            });
    }

    public function startDayAction(): Action
    {
        return Action::make('startDay')
            ->label('Reisdag starten')
            ->icon('heroicon-o-play')
            ->size('lg')
            ->extraAttributes(['style' => 'width:100%'])
            ->disabled(fn () => $this->todayIsSet())
            ->modalHeading('Reisdag starten')
            ->modalDescription('Start je later door slecht bereik? Zet de tijd terug naar wanneer je echt vertrok.')
            ->modalSubmitActionLabel('Starten')
            ->schema([
                ...self::timeFields('started_at', 'Vertrokken om', 'Ik vertrok op een ander moment'),
                TrackingPointSelect::make('start_point_id', 'GPS-locatie bij vertrek'),
                TextInput::make('start_location')->label('Plaatsnaam vertrek (zichtbaar op de website)')->placeholder('bijv. Lisse')->maxLength(150)
                    ->default(fn () => JourneyDay::whereNotNull('end_location')->latest('date')->value('end_location')),
                Select::make('country_id')->label('Land')->options(fn () => Options::countries())
                    ->default(fn () => Country::where('status', CountryStatus::Current)->value('id')),
            ])
            ->action(function (array $data) {
                $startedAt = self::chosenTime($data, 'started_at');
                // One day per date: starting again on a date that already exists updates that day.
                // The day's date is the local date where I am (in Vietnam 06:00 local is still the day before in UTC).
                $day = JourneyDay::firstOrNew(['date' => $startedAt->copy()->setTimezone(FilamentTimezone::get())->toDateString()]);
                $day->fill([
                    'type' => $day->type ?? DayType::Walk,
                    'started_at' => $startedAt,
                    'ended_at' => null,
                    'start_location' => $data['start_location'] ?: $day->start_location,
                    'country_id' => $data['country_id'] ?: $day->country_id,
                    'start_point_id' => $data['start_point_id'] ?? $day->start_point_id,
                ])->save();

                Notification::make()->success()->title($day->name().' gestart: '.$startedAt->copy()->setTimezone(FilamentTimezone::get())->translatedFormat('l j F, H:i'))->send();
            });
    }

    public function endDayAction(): Action
    {
        return Action::make('endDay')
            ->label('Reisdag eindigen')
            ->icon('heroicon-o-stop')
            ->size('lg')
            ->extraAttributes(['style' => 'width:100%'])
            ->color('success')
            ->visible(fn () => $this->openDays()->isNotEmpty())
            ->modalHeading('Reisdag eindigen')
            ->modalDescription('Eindig je later door slecht bereik? Zet de tijd terug naar wanneer je echt aankwam.')
            ->modalSubmitActionLabel('Eindigen')
            ->fillForm(function () {
                $day = $this->openDays()->first();

                return ['day_id' => $day?->id, 'end_point_id' => TrackingPoint::latest('recorded_at')->value('id'), 'distance_km' => $this->trackedKm($day, now())];
            })
            ->schema([
                // Only a choice when several days were started without being ended.
                Select::make('day_id')->label('Welke dag')
                    ->options(fn () => $this->openDays()->mapWithKeys(fn (JourneyDay $day) => [$day->id => $day->name().' — '.$day->started_at->copy()->setTimezone(FilamentTimezone::get())->translatedFormat('l j F, H:i')]))
                    ->visible(fn () => $this->openDays()->count() > 1)
                    ->required(),
                ...self::timeFields('ended_at', 'Aangekomen om', 'Ik kwam op een ander moment aan'),
                TrackingPointSelect::make('end_point_id', 'GPS-locatie bij aankomst'),
                TextInput::make('end_location')->label('Plaatsnaam aankomst (zichtbaar op de website)')->placeholder('bijv. Haarlem')->maxLength(150),
                Select::make('overnight')->label('Waar ik sliep')->options(Overnight::class),
                FileUpload::make('sleep_photo')
                    ->label('Foto van de slaapplek')
                    ->helperText('Komt in de galerij, maar pas na de vertraging van je locatie.')
                    ->image()
                    ->disk(MediaStorage::diskName())
                    ->directory('gallery')
                    // Resized in the browser first: much smaller upload on a weak connection.
                    ->imageResizeTargetWidth('2400')
                    ->imageResizeTargetHeight('2400')
                    ->imageResizeMode('contain')
                    ->imageResizeUpscale(false)
                    ->maxSize(8192),
                TextInput::make('distance_km')->label('Afstand')->numeric()->suffix('km')
                    ->helperText('Vooraf ingevuld uit je locaties van die dag, als die er zijn.'),
            ])
            ->action(function (array $data) {
                $day = JourneyDay::findOrFail($data['day_id'] ?? $this->openDays()->first()->id);
                $endedAt = self::chosenTime($data, 'ended_at');

                if ($endedAt->lte($day->started_at)) {
                    Notification::make()->danger()->title('Aankomst moet na vertrek ('.$day->started_at->copy()->setTimezone(FilamentTimezone::get())->format('H:i').') liggen')->send();

                    return;
                }

                $day->update([
                    'ended_at' => $endedAt,
                    'end_location' => $data['end_location'] ?: $day->end_location,
                    'end_point_id' => $data['end_point_id'] ?? $day->end_point_id,
                    'overnight' => $data['overnight'] ?? $day->overnight,
                    'distance_km' => $data['distance_km'] !== null && $data['distance_km'] !== '' ? $data['distance_km'] : $day->distance_km,
                    'walking_minutes' => $day->walking_minutes ?? (int) $day->started_at->diffInMinutes($endedAt),
                ]);

                if ($data['sleep_photo'] ?? null) {
                    $place = $day->end_location;
                    GalleryItem::create([
                        'path' => $data['sleep_photo'],
                        'source' => 'upload',
                        'journey_day_id' => $day->id,
                        'country_id' => $day->country_id,
                        'taken_at' => $endedAt,
                        'caption' => ['en' => 'Where I slept'.($place ? ": {$place}" : ''), 'nl' => 'Waar ik sliep'.($place ? ": {$place}" : '')],
                        'is_public' => true,
                        'is_sleeping_spot' => true,
                    ]);
                }

                Notification::make()->success()->title($day->name().' beëindigd')->send();
            });
    }

    /**
     * Time is "now" unless the box is ticked (no signal at the moment itself). No min/max on the
     * picker: with time zones and late entries a limit only gets in the way.
     */
    private static function timeFields(string $name, string $label, string $toggle): array
    {
        return [
            Toggle::make("{$name}_other")->label($toggle)->live(),
            DateTimePicker::make($name)->label($label)->seconds(false)->default(fn () => now())
                ->visible(fn (Get $get) => $get("{$name}_other"))
                ->required(fn (Get $get) => $get("{$name}_other")),
        ];
    }

    private static function chosenTime(array $data, string $name): Carbon
    {
        return ($data["{$name}_other"] ?? false) && ! empty($data[$name]) ? Carbon::parse($data[$name]) : now();
    }

    /** Distance over the tracking points between the start of the day and the end, or null without points. */
    private function trackedKm(?JourneyDay $day, Carbon $until): ?float
    {
        if (! $day) {
            return null;
        }

        $points = TrackingPoint::whereBetween('recorded_at', [$day->started_at, $until])->orderBy('recorded_at')
            ->get(['latitude', 'longitude'])->map(fn ($p) => [$p->latitude, $p->longitude])->all();

        return count($points) > 1 ? round(RouteGeometry::distanceKm($points), 1) : null;
    }
}
