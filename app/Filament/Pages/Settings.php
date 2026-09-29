<?php

namespace App\Filament\Pages;

use App\Enums\JourneyPhase;
use App\Services\ImageProcessor;
use App\Support\MediaStorage;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Http\Middleware\ComingSoon;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class Settings extends Page
{
    protected string $view = 'filament.pages.site-texts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Toegang tot de website')->schema([
                    Toggle::make('site_open')
                        ->label('Website is open'),
                ]),
                Section::make('Reis')->columns(2)->schema([
                    Select::make('journey_phase')
                        ->label('Fase van het project')
                        ->options(JourneyPhase::class)
                        ->required()
                        ->helperText('Bepaalt waar de homepagina de nadruk op legt.'),
                ]),
                Section::make('Homepagina')->schema([
                    FileUpload::make('home_image')
                        ->label('Hoofdfoto')
                        ->helperText('Grote foto bovenaan de homepagina. Liggend werkt het best.')
                        ->image()
                        ->disk(MediaStorage::diskName())
                        ->imageResizeTargetWidth('2400')
                        ->imageResizeTargetHeight('2400')
                        ->imageResizeMode('contain')
                        ->imageResizeUpscale(false)
                        ->directory('site')
                        ->maxSize(8192),
                ]),
                Section::make('Privacy van je locatie')->columns(2)->schema([
                    Select::make('public_tracking_delay_hours')
                        ->label('Bezoekers zien je locatie van')
                        ->options([0 => 'Nu (geen vertraging)', 168 => '1 week geleden', 336 => '2 weken geleden', 504 => '3 weken geleden', 720 => '1 maand geleden'])
                        ->required()
                        ->helperText('Familie (ingelogd) ziet je locatie altijd live.'),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['journey_phase'] = $state['journey_phase'] instanceof JourneyPhase ? $state['journey_phase']->value : $state['journey_phase'];

        if (! $state['home_image']) {
            $state['home_image_variants'] = null;
        } elseif ($state['home_image'] !== SiteSettings::get('home_image')) {
            app(ImageProcessor::class)->process($state['home_image']); // re-encode, strip EXIF/GPS
            $state['home_image_variants'] = app(ImageProcessor::class)->variants($state['home_image']);
        }

        SiteSettings::set($state);
        Log::info('Settings changed', ['user' => auth()->id(), 'settings' => $state]);

        Notification::make()->success()->title('Instellingen opgeslagen')->send();
    }
}
