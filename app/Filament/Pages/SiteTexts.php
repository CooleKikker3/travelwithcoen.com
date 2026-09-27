<?php

namespace App\Filament\Pages;

use App\Support\SiteTexts as Texts;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Edit every fixed text of the public site, per language. Empty field = default text.
 */
class SiteTexts extends Page
{
    protected string $view = 'filament.pages.site-texts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Website texts';

    protected static ?string $title = 'Website texts';

    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    // Field names can't contain dots, so "site.home.title" becomes "site__home__title".
    private const SEPARATOR = '__';

    public function mount(): void
    {
        $overrides = Texts::overrides();
        $state = [];

        foreach ($this->keys() as $key) {
            foreach (array_keys(config('travel.locales')) as $locale) {
                [$group, $path] = explode('.', $key, 2);
                $state[$this->field($key)][$locale] = $overrides[$key][$locale] ?? Texts::defaults($group, $locale)[$path] ?? null;
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $locales = config('travel.locales');

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Groups')->tabs(collect(Texts::GROUPS)->map(fn (string $group) => Tab::make(Str::headline($group))
                    ->schema(collect($this->keys($group))
                        ->groupBy(fn (string $key) => explode('.', $key)[1])
                        ->map(fn ($keys, string $section) => Section::make(Str::headline($section))
                            ->collapsible()
                            ->collapsed()
                            ->schema($keys->map(fn (string $key) => Grid::make(count($locales))
                                ->schema(collect($locales)->map(fn (string $label, string $locale) => $this->input($key, $locale, $label))->values()->all()))
                                ->all()))
                        ->values()
                        ->all()))
                    ->all()),
            ]);
    }

    public function save(): void
    {
        $texts = collect($this->form->getState())
            ->mapWithKeys(fn (array $values, string $field) => [str_replace(self::SEPARATOR, '.', $field) => $values])
            ->all();

        Texts::save($texts);

        Notification::make()->success()->title('Texts saved')->send();
    }

    private function input(string $key, string $locale, string $label): TextInput|Textarea
    {
        [$group, $path] = explode('.', $key, 2);
        $default = Texts::defaults($group, $locale)[$path] ?? '';
        $long = mb_strlen($default) > 90 || str_contains($default, "\n");

        return ($long ? Textarea::make("{$this->field($key)}.{$locale}")->autosize() : TextInput::make("{$this->field($key)}.{$locale}"))
            ->label($locale === config('app.fallback_locale') ? $path : $label)
            ->hint($locale === config('app.fallback_locale') ? $label : null)
            ->placeholder($default)
            ->helperText(str_contains($default, ':') ? 'Keep words starting with ":" as they are (placeholders).' : null);
    }

    /** @return array<int, string> all editable keys as "group.dotted.path" */
    private function keys(?string $only = null): array
    {
        return collect($only ? [$only] : Texts::GROUPS)
            ->flatMap(fn (string $group) => collect(Texts::defaults($group, config('app.fallback_locale')))
                ->keys()
                ->map(fn (string $path) => "{$group}.{$path}"))
            ->all();
    }

    private function field(string $key): string
    {
        return str_replace('.', self::SEPARATOR, $key);
    }
}
