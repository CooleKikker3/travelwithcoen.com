<?php

namespace App\Filament\Pages;

use App\Enums\JourneyPhase;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
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
                Section::make('Journey')->columns(2)->schema([
                    Select::make('journey_phase')
                        ->label('Phase of the project')
                        ->options(JourneyPhase::class)
                        ->required()
                        ->helperText('Changes the focus of the home page.'),
                ]),
                Section::make('Tracking privacy')->columns(2)->schema([
                    TextInput::make('public_tracking_delay_hours')
                        ->label('Public tracking delay')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->suffix('hours')
                        ->required()
                        ->helperText('Visitors only see locations older than this. 336 = 14 days, 168 = 7, 504 = 21, 720 = 30. Family sees everything live.'),
                ]),
                Section::make('Budget (private)')->columns(2)->schema([
                    TextInput::make('budget_total_eur')->label('Total budget')->numeric()->prefix('€')->required(),
                    TextInput::make('budget_reserve_eur')->label('Of which emergency reserve')->numeric()->prefix('€')->required(),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['journey_phase'] = $state['journey_phase'] instanceof JourneyPhase ? $state['journey_phase']->value : $state['journey_phase'];

        SiteSettings::set($state);
        Log::info('Settings changed', ['user' => auth()->id(), 'settings' => $state]);

        Notification::make()->success()->title('Settings saved')->send();
    }
}
