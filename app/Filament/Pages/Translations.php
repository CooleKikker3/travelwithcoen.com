<?php

namespace App\Filament\Pages;

use App\Filament\Blocks\ImageBlock;
use App\Models\Translation;
use App\Services\AutoTranslation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Checking the automatic English translations (AutoTranslation) before they go online: per item the Dutch text
 * and the English suggestion (editable) side by side; approve, translate again or skip.
 */
class Translations extends Page
{
    protected string $view = 'filament.pages.translations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $title = 'Vertalingen';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public static function getNavigationBadge(): ?string
    {
        return ($count = Translation::where('status', 'ready')->count()) ? (string) $count : null;
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    /** Translations waiting for a check, grouped per item. */
    public function items(): Collection
    {
        return Translation::where('status', 'ready')->orderBy('id')->get()
            ->groupBy(fn (Translation $t) => $t->translatable_type.'#'.$t->translatable_id);
    }

    /** @return array{pending: int, failed: Collection} */
    public function queue(): array
    {
        return [
            'pending' => Translation::where('status', 'pending')->count(),
            'failed' => Translation::where('status', 'failed')->get(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->items()->map(function (Collection $rows, string $key) {
                $ids = $rows->pluck('id')->all();
                $name = md5($key);

                return Section::make($rows->first()->itemLabel())
                    ->headerActions([
                        Action::make("approve_{$name}")->label('Goedkeuren')->icon(Heroicon::OutlinedCheck)->color('success')
                            ->action(fn () => $this->approve($ids)),
                        Action::make("retry_{$name}")->label('Opnieuw vertalen')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                            ->action(fn () => $this->retry($ids)),
                        Action::make("skip_{$name}")->label('Overslaan')->icon(Heroicon::OutlinedXMark)->color('gray')
                            ->requiresConfirmation()->modalDescription('De Engelse tekst blijft zoals hij is.')
                            ->action(fn () => $this->skip($ids)),
                    ])
                    ->schema($rows->map(fn (Translation $t) => Grid::make(['default' => 1, 'lg' => 2])->schema([
                        TextEntry::make("nl_{$t->id}")->label('Nederlands · '.$t->fieldLabel())
                            ->state(new HtmlString($t->isHtml()
                                ? RichContentRenderer::make($t->source)->customBlocks([ImageBlock::class])->toUnsafeHtml()
                                : nl2br(e($t->source))))
                            ->extraAttributes(['class' => 'prose dark:prose-invert max-w-none']),
                        $t->isHtml()
                            ? RichEditor::make("t{$t->id}")->label('Engels')->customBlocks([ImageBlock::class])->fileAttachments(false)
                            : Textarea::make("t{$t->id}")->label('Engels')->autosize(),
                    ]))->all());
            })->values()->all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('costs')->label('Kosten bij Google')->icon(Heroicon::OutlinedBanknotes)->color('gray')
                ->url(config('services.google_translate.billing_url'), shouldOpenInNewTab: true)
                ->visible(fn () => filled(config('services.google_translate.billing_url'))),
            Action::make('approveAll')->label('Alles goedkeuren')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn () => $this->items()->isNotEmpty())
                ->requiresConfirmation()->modalDescription('Alle vertalingen op deze pagina gaan online, met jouw aanpassingen.')
                ->action(fn () => $this->approve($this->items()->flatten()->pluck('id')->all())),
        ];
    }

    /** Failed translations: try again on the next run. */
    public function retryFailed(): void
    {
        Translation::where('status', 'failed')->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);
        Notification::make()->success()->title('Wordt opnieuw geprobeerd')->send();
    }

    private function approve(array $ids): void
    {
        $state = $this->form->getState();
        foreach (Translation::whereIn('id', $ids)->where('status', 'ready')->get() as $translation) {
            app(AutoTranslation::class)->approve($translation, $state["t{$translation->id}"] ?? null);
        }
        Notification::make()->success()->title('Vertaling staat online')->send();
        $this->fillForm();
    }

    private function retry(array $ids): void
    {
        Translation::whereIn('id', $ids)->update(['status' => 'pending', 'attempts' => 0, 'suggestion' => null, 'error' => null]);
        Notification::make()->success()->title('Wordt opnieuw vertaald')->body('Binnen een minuut staat de nieuwe vertaling hier.')->send();
        $this->fillForm();
    }

    private function skip(array $ids): void
    {
        Translation::whereIn('id', $ids)->delete();
        $this->fillForm();
    }

    private function fillForm(): void
    {
        $this->form->fill(Translation::where('status', 'ready')->pluck('suggestion', 'id')
            ->mapWithKeys(fn (?string $text, int $id) => ["t{$id}" => $text])->all());
    }
}
