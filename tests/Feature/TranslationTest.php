<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\Role;
use App\Filament\Pages\Translations;
use App\Models\Article;
use App\Models\Translation;
use App\Models\User;
use App\Services\AutoTranslation;
use App\Support\SiteTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_translate.key' => 'test-key']);
        // Fake Google: "translates" by prefixing every text.
        Http::fake(['translation.googleapis.com/*' => fn ($request) => Http::response(['data' => [
            'translations' => array_map(fn (string $q) => ['translatedText' => '[EN] '.$q], $request->data()['q']),
        ]])]);
    }

    private function translateQueue(): void
    {
        app(AutoTranslation::class)->run();
    }

    public function test_dutch_texts_are_translated_on_the_server_and_only_go_online_after_approval(): void
    {
        $article = Article::create([
            'type' => ArticleType::Preparation, 'status' => ArticleStatus::Published, 'published_at' => now()->subDay(),
            'title' => ['nl' => 'Nieuwe schoenen'], 'body' => ['nl' => '<p>Ze lopen fijn.</p>'],
        ]);
        $this->assertSame(['body', 'title'], Translation::where('status', 'pending')->orderBy('field')->pluck('field')->all());

        $this->translateQueue();
        $title = Translation::where('field', 'title')->first();
        $this->assertSame(['ready', '[EN] Nieuwe schoenen'], [$title->status, $title->suggestion]);
        Http::assertSent(fn ($request) => $request['source'] === 'nl' && $request['target'] === 'en' && str_contains($request->url(), 'key=test-key'));

        // Not checked yet: the English site still shows the Dutch text.
        $this->get($article->url('en'))->assertSee('Nieuwe schoenen')->assertSee('You are reading the Dutch version');

        // Approved (with a correction): online in English.
        app(AutoTranslation::class)->approve($title, 'New shoes');
        app(AutoTranslation::class)->approve(Translation::where('field', 'body')->first());
        $this->assertSame(0, Translation::count());
        $this->get($article->fresh()->url('en'))->assertSee('New shoes')->assertSee('Ze lopen fijn.')->assertDontSee('You are reading the Dutch version');

        // Changing the Dutch text: translated again, the old English stays online until approved.
        $article = $article->fresh();
        $article->update(['title' => ['nl' => 'Mijn nieuwe schoenen', 'en' => 'New shoes']]);
        $this->assertSame('pending', Translation::where('field', 'title')->value('status'));
        $this->assertSame('New shoes', $article->fresh()->translate('title', 'en'));

        // Writing the English by hand in the same save: no translation needed.
        $article->update(['title' => ['nl' => 'Schoenen', 'en' => 'Shoes']]);
        $this->assertSame(0, Translation::where('field', 'title')->count());
    }

    public function test_an_empty_english_editor_tab_counts_as_empty(): void
    {
        $article = Article::create([
            'type' => ArticleType::Preparation, 'status' => ArticleStatus::Published, 'published_at' => now()->subDay(),
            'title' => ['nl' => 'Test'], 'body' => ['nl' => '<p>De lange versie.</p>', 'en' => '<p></p>'],
        ]);

        $this->assertSame('pending', Translation::where('field', 'body')->value('status'));
        $this->get($article->url('en'))->assertSee('De lange versie.');
    }

    public function test_photo_captions_in_texts_are_translated_and_their_settings_kept(): void
    {
        $config = e(json_encode(['path' => 'articles/images/tent.jpg', 'caption' => 'Mijn tent', 'sensitive' => false]));
        Article::create(['type' => ArticleType::Diary, 'title' => ['nl' => 'Kamperen'], 'body' => ['nl' => "<p>Tekst</p><div data-type=\"customBlock\" data-config=\"{$config}\" data-id=\"image\"></div>"]]);

        $this->translateQueue();
        $html = Translation::where('field', 'body')->value('suggestion');
        $block = app(\App\Services\ArticleGallerySync::class)->imageBlocks($html)[0];
        $this->assertSame(['articles/images/tent.jpg', '[EN] Mijn tent', false], [$block['path'], $block['caption'], $block['sensitive']]);
        $this->assertStringContainsString('[EN] <p>Tekst</p>', $html);
    }

    public function test_website_texts_are_translated_with_their_placeholders_kept(): void
    {
        SiteTexts::save(['site.home.lead' => ['nl' => 'Vanaf :departure loop ik naar Hanoi.', 'en' => __('site.home.lead', [], 'en')]]);
        $this->translateQueue();

        $translation = Translation::firstOrFail();
        $this->assertSame('[EN] Vanaf :departure loop ik naar Hanoi.', $translation->suggestion);
        Http::assertSent(fn ($request) => str_contains($request['q'][0], '<span translate="no">:departure</span>'));

        app(AutoTranslation::class)->approve($translation, 'From :departure I walk to Hanoi.');
        $this->assertSame('From :departure I walk to Hanoi.', SiteTexts::overrides()['site.home.lead']['en']);
    }

    public function test_failures_are_retried_and_shown(): void
    {
        config(['services.google_translate.key' => null]);
        Article::create(['type' => ArticleType::Diary, 'title' => ['nl' => 'Hoi']]);

        foreach (range(1, 5) as $attempt) {
            $this->translateQueue();
        }
        $this->assertSame(['failed', 5], [Translation::value('status'), Translation::value('attempts')]);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Livewire::test(Translations::class)->assertSee('Vertalen lukte niet')->assertSee('Vertalen is nog niet ingesteld')
            ->call('retryFailed');
        $this->assertSame('pending', Translation::value('status'));
    }

    public function test_the_check_page_shows_dutch_and_english_side_by_side(): void
    {
        Article::create(['type' => ArticleType::Diary, 'title' => ['nl' => 'Over de dijken']]);
        $this->translateQueue();

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get('/admin/translations')->assertOk();
        Livewire::test(Translations::class)
            ->assertSee('Verhaal · Over de dijken')->assertSee('Nederlands · Titel')->assertSee('Kosten bij Google')
            ->assertSet('data.t'.Translation::value('id'), '[EN] Over de dijken')
            ->set('data.t'.Translation::value('id'), 'Along the dikes')
            ->callAction('approveAll');

        $this->assertSame('Along the dikes', Article::first()->translate('title', 'en'));
        $this->assertNull(Translations::getNavigationBadge());
    }
}
