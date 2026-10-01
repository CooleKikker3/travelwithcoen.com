<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Cloud Translation (Basic, v2): Dutch → English, as HTML (tags and translate="no" are kept). */
class GoogleTranslate
{
    /**
     * @param  list<string>  $texts  HTML
     * @return list<string> HTML, in the same order
     */
    public function translate(array $texts): array
    {
        $key = config('services.google_translate.key') ?: throw new RuntimeException('Vertalen is nog niet ingesteld op de server.');

        $response = Http::timeout(60)->retry(2, 2000, throw: false)
            ->post('https://translation.googleapis.com/language/translate/v2?key='.urlencode($key), [
                'q' => array_values($texts), 'source' => 'nl', 'target' => 'en', 'format' => 'html',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Google Translate: '.($response->json('error.message') ?? 'fout '.$response->status()));
        }

        return array_map(fn (array $translation) => $translation['translatedText'], $response->json('data.translations') ?? []);
    }
}
