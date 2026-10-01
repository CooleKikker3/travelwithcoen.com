<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Country;
use App\Models\CountryRoute;
use App\Models\EquipmentItem;
use App\Models\GalleryItem;
use App\Models\JourneyEvent;
use App\Models\Translation;
use App\Support\SiteTexts;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Coen writes in Dutch; the server translates to English (Google Cloud Translation) and nothing goes online until
 * he approves it on the "Vertalingen" page. Saving a changed Dutch text queues that field (HasTranslations,
 * SiteTexts::save); `translations:run` (every minute) translates the queue. Until approval the English site keeps
 * showing the old English text, or the Dutch text when there is none yet.
 */
class AutoTranslation
{
    public const SITE_TEXT = 'site_text';

    /** Rich text fields (HTML with image blocks); the rest is plain text. */
    public const HTML_FIELDS = ['body', 'story'];

    private const MAX_ATTEMPTS = 5;

    /** Models with automatic translation (see their $autoTranslate). */
    public const MODELS = [Article::class, EquipmentItem::class, Country::class, CountryRoute::class, JourneyEvent::class, GalleryItem::class];

    public function __construct(private GoogleTranslate $google) {}

    /** After a save: queue the fields whose Dutch text changed (unless the English was changed by hand in the same save). */
    public function queueModel(Model $model): void
    {
        foreach ($model->autoTranslateFields() as $field) {
            // An empty rich text tab ("<p></p>") counts as empty, not as English written by hand.
            $clean = fn (array $values) => array_map(fn ($value) => $model::isEmptyText($value) ? null : $value, $values);
            $old = $clean($model->wasRecentlyCreated ? [] : $this->values($model->getOriginal($field)));
            $new = $clean($this->values($model->getAttribute($field)));

            if (($old['en'] ?? null) !== ($new['en'] ?? null)) {
                // English written by hand: a waiting translation of this field is no longer needed.
                Translation::where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey(), 'field' => $field])->delete();

                continue;
            }
            if (($old['nl'] ?? null) === ($new['nl'] ?? null)) {
                continue;
            }
            $this->queue($model::class, $model->getKey(), $field, $new['nl'] ?? null);
        }
    }

    public function queue(string $type, int $id, string $field, ?string $source): void
    {
        $key = ['translatable_type' => $type, 'translatable_id' => $id, 'field' => $field];

        if (blank(trim(strip_tags((string) $source)))) {
            Translation::where($key)->delete();

            return;
        }

        Translation::updateOrCreate($key, ['source' => $source, 'suggestion' => null, 'status' => 'pending', 'error' => null, 'attempts' => 0]);
    }

    public function forget(Model $model): void
    {
        Translation::where('translatable_type', $model::class)->where('translatable_id', $model->getKey())->delete();
    }

    /** Translate the queue. @return int number translated */
    public function run(int $limit = 25): int
    {
        $done = 0;
        foreach (Translation::where('status', 'pending')->orderBy('id')->limit($limit)->get() as $translation) {
            try {
                $translation->update([
                    'suggestion' => $this->translate($translation->source, $translation->isHtml(), $translation->translatable_type === self::SITE_TEXT),
                    'status' => 'ready',
                    'error' => null,
                ]);
                $done++;
            } catch (Throwable $e) {
                $attempts = $translation->attempts + 1;
                $translation->update(['attempts' => $attempts, 'error' => $e->getMessage(), 'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending']);
            }
        }

        return $done;
    }

    /** Put the (checked, possibly edited) English text online. */
    public function approve(Translation $translation, ?string $english = null): void
    {
        $english ??= $translation->suggestion;

        if ($translation->translatable_type === self::SITE_TEXT) {
            SiteTexts::setTranslation($translation->field, 'en', $english);
        } elseif ($record = $translation->record()) {
            $values = $this->values($record->getAttribute($translation->field));
            $values['en'] = $english;
            $record->setAttribute($translation->field, $values);
            $record->save();
        }

        $translation->delete();
    }

    /** Queue everything that is written in Dutch but has no English yet (once, e.g. after going live). @return int queued */
    public function queueMissing(): int
    {
        $count = 0;
        foreach (self::MODELS as $class) {
            foreach ($class::withoutGlobalScopes()->get() as $model) {
                foreach ($model->autoTranslateFields() as $field) {
                    $values = $this->values($model->getAttribute($field));
                    if (! $model::isEmptyText($values['nl'] ?? null) && $model::isEmptyText($values['en'] ?? null)
                        && ! Translation::where(['translatable_type' => $class, 'translatable_id' => $model->getKey(), 'field' => $field])->exists()) {
                        $this->queue($class, $model->getKey(), $field, $values['nl']);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Dutch → English. Plain text is sent as HTML (line breaks kept; in website texts ":words" are not translated).
     * In rich text, image blocks keep their settings; only their caption and description are translated.
     */
    public function translate(string $text, bool $html, bool $placeholders = false): string
    {
        if (! $html) {
            $prepared = nl2br(e($text), false);
            if ($placeholders) {
                $prepared = preg_replace('/:([A-Za-z_]+)/', '<span translate="no">:$1</span>', $prepared);
            }
            $out = $this->google->translate([$prepared])[0];
            $out = preg_replace('/<\/?span[^>]*>/i', '', $out);
            $out = preg_replace('/[ \t]*<br\s*\/?>\s?/i', "\n", $out);

            return trim(html_entity_decode($out, ENT_QUOTES | ENT_HTML5));
        }

        $configs = [];
        $prepared = preg_replace_callback('/\sdata-config=("|\')(.*?)\1/s', function (array $m) use (&$configs) {
            $configs[] = json_decode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5), true) ?? [];

            return ' data-n="'.(count($configs) - 1).'"';
        }, $text);

        $texts = [$prepared];
        $refs = [];
        foreach ($configs as $i => $config) {
            foreach (['caption', 'alt'] as $key) {
                if (filled($config[$key] ?? null)) {
                    $refs[] = [$i, $key];
                    $texts[] = e($config[$key]);
                }
            }
        }

        $out = $this->google->translate($texts);
        foreach ($refs as $j => [$i, $key]) {
            $configs[$i][$key] = html_entity_decode($out[$j + 1], ENT_QUOTES | ENT_HTML5);
        }

        return preg_replace_callback('/\sdata-n="(\d+)"/', fn (array $m) => ' data-config="'.e(json_encode($configs[(int) $m[1]] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'"', $out[0]);
    }

    private function values(mixed $value): array
    {
        return is_array($value) ? $value : (json_decode((string) $value, true) ?? []);
    }
}
