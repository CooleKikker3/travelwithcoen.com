<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** Every text used by the site must exist in every language (otherwise the raw key shows up). */
class TranslationsTest extends TestCase
{
    private const GROUPS = ['site', 'articles', 'countries'];

    public function test_all_texts_used_in_the_code_exist_in_every_language(): void
    {
        $keys = [];

        foreach ([...File::allFiles(resource_path('views')), ...File::allFiles(app_path())] as $file) {
            preg_match_all("/(?:__|trans_choice)\('((?:".implode('|', self::GROUPS).")\.[a-z_.]+)'/", $file->getContents(), $matches);
            $keys = [...$keys, ...$matches[1]];
        }

        $missing = [];
        foreach (array_keys(config('travel.locales')) as $locale) {
            foreach (array_unique($keys) as $key) {
                if (! Lang::hasForLocale($key, $locale)) {
                    $missing[] = "{$locale}: {$key}";
                }
            }
        }

        $this->assertSame([], $missing, 'Missing translations');
        $this->assertGreaterThan(100, count(array_unique($keys)));
    }

    public function test_all_status_and_category_labels_are_translated(): void
    {
        $raw = [];

        foreach (array_keys(config('travel.locales')) as $locale) {
            app()->setLocale($locale);

            foreach (File::files(app_path('Enums')) as $file) {
                $enum = 'App\\Enums\\'.$file->getFilenameWithoutExtension();
                if (! enum_exists($enum) || ! method_exists($enum, 'getLabel')) {
                    continue;
                }

                foreach ($enum::cases() as $case) {
                    // An untranslated label comes back as its key, e.g. "site.phase.preparation".
                    if (preg_match('/^('.implode('|', self::GROUPS).')\./', $case->getLabel())) {
                        $raw[] = "{$locale}: {$case->getLabel()}";
                    }
                }
            }
        }

        $this->assertSame([], $raw, 'Untranslated labels');
    }
}
