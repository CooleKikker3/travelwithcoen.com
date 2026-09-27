<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): Article
    {
        return Article::create($attributes + [
            'type' => ArticleType::Preparation,
            'title' => ['en' => 'First night in the tent', 'nl' => 'Eerste nacht in de tent'],
            'body' => ['en' => '<p>Cold.</p>', 'nl' => '<p>Koud.</p>'],
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_public_pages_render_in_both_languages(): void
    {
        foreach (['/', '/about', '/journey', '/diary', '/preparation'] as $path) {
            $this->get($path)->assertOk()->assertSee('<html lang="en">', false);
            $this->get('/nl'.rtrim($path, '/'))->assertOk()->assertSee('<html lang="nl">', false);
        }
    }

    public function test_slugs_are_generated_per_locale(): void
    {
        $article = $this->article();

        $this->assertSame(['en' => 'first-night-in-the-tent', 'nl' => 'eerste-nacht-in-de-tent'], $article->slug);
        $this->get('/preparation/first-night-in-the-tent')->assertOk()->assertSee('Cold.');
        $this->get('/nl/preparation/eerste-nacht-in-de-tent')->assertOk()->assertSee('Koud.');
    }

    public function test_wrong_locale_slug_redirects_to_the_right_one(): void
    {
        $this->article();

        $this->get('/nl/preparation/first-night-in-the-tent')->assertRedirect('/nl/preparation/eerste-nacht-in-de-tent');
    }

    public function test_untranslated_article_falls_back_to_english_with_notice(): void
    {
        $this->article(['title' => ['en' => 'Only English'], 'body' => ['en' => '<p>Hello.</p>']]);

        $this->get('/nl/preparation/only-english')
            ->assertOk()
            ->assertSee('Hello.')
            ->assertSee('nog niet in het Nederlands beschikbaar')
            ->assertSee('<link rel="canonical" href="'.url('/preparation/only-english').'">', false);
    }

    public function test_drafts_and_scheduled_articles_are_hidden(): void
    {
        $this->article(['title' => ['en' => 'Draft'], 'status' => ArticleStatus::Draft]);
        $this->article(['title' => ['en' => 'Future'], 'published_at' => now()->addWeek()]);

        $this->get('/preparation/draft')->assertNotFound();
        $this->get('/preparation/future')->assertNotFound();
    }

    public function test_country_page_lists_its_articles(): void
    {
        $country = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany', 'nl' => 'Duitsland']]);
        $this->article(['type' => ArticleType::Diary, 'title' => ['en' => 'First week in Germany'], 'country_id' => $country->id]);

        $this->get('/nl/countries/duitsland')->assertOk()->assertSee('First week in Germany');
    }

    public function test_only_admins_can_access_the_cms(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create(['role' => Role::TrustedViewer]))->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get('/admin')->assertOk();
    }

    public function test_cms_screens_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $article = $this->article(['country_id' => Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands']])->id]);

        foreach (['articles', 'countries', 'users'] as $resource) {
            $this->get("/admin/{$resource}")->assertOk();
            $this->get("/admin/{$resource}/create")->assertOk();
        }
        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('First night in the tent');
    }
}
