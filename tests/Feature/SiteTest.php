<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\Role;
use App\Enums\RouteType;
use App\Filament\Pages\SiteTexts as SiteTextsPage;
use App\Models\Article;
use App\Models\Country;
use App\Models\CountryRoute;
use App\Models\User;
use App\Support\SiteTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
        $pages = ['/' => '/nl', '/about' => '/nl/over', '/journey' => '/nl/reis'];

        foreach ($pages as $en => $nl) {
            $this->get($en)->assertOk()->assertSee('<html lang="en">', false)->assertSee('hreflang="nl" href="'.url($nl).'"', false);
            $this->get($nl)->assertOk()->assertSee('<html lang="nl">', false);
        }
    }

    public function test_the_home_page_shows_the_image_chosen_in_settings(): void
    {
        $this->get('/')->assertOk()->assertDontSee('fetchpriority', false);

        \App\Support\Settings::set(['home_image' => 'site/hero.jpg']);
        $this->get('/')->assertOk()->assertSee('site/hero.jpg', false);
    }

    public function test_stops_in_the_rough_direction_link_to_their_country(): void
    {
        Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland'], 'is_published' => true]);

        $this->get('/')->assertSee('href="'.url('/countries/netherlands').'" class="stamp', false);
    }

    public function test_cms_text_overrides_replace_default_texts(): void
    {
        SiteTexts::save(['site.home.title' => ['en' => 'Custom title', 'nl' => '']]);

        $this->get('/')->assertSee('Custom title');
        $this->get('/nl')->assertSee('Te voet van Nederland naar Hanoi');
        $this->assertDatabaseCount('site_texts', 1);

        SiteTexts::save(['site.home.title' => ['en' => '', 'nl' => '']]);
        $this->assertDatabaseCount('site_texts', 0);
    }

    public function test_texts_can_be_edited_on_the_cms_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        Livewire::test(SiteTextsPage::class)
            ->set('data.site__about__title.nl', 'Wie ben ik?')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/nl/over')->assertSee('Wie ben ik?');
        $this->assertDatabaseCount('site_texts', 1);
    }

    public function test_gpx_upload_creates_route_points_shown_on_the_maps(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('gpx/test.gpx', <<<'GPX'
            <?xml version="1.0"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <trk><trkseg>
                <trkpt lat="52.2600" lon="4.5600"><ele>1</ele></trkpt>
                <trkpt lat="52.5000" lon="4.7000"><ele>2</ele></trkpt>
                <trkpt lat="52.9600" lon="4.7600"><ele>3</ele></trkpt>
              </trkseg></trk>
            </gpx>
            GPX);

        $country = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland']]);
        $route = CountryRoute::create(['country_id' => $country->id, 'type' => RouteType::Planned, 'gpx_path' => 'gpx/test.gpx']);

        $this->assertSame(3, $route->points()->count());
        $this->assertEqualsWithDelta(79, $route->fresh()->distance_km, 3);

        $this->get('/journey')->assertOk()->assertSee('"type":"planned"', false)->assertSee('[4.56,52.26]', false);
        $this->get('/nl/landen/nederland')->assertOk()->assertSee('[4.76,52.96]', false);
    }

    public function test_slugs_are_generated_per_locale(): void
    {
        $article = $this->article();

        $this->assertSame(['en' => 'first-night-in-the-tent', 'nl' => 'eerste-nacht-in-de-tent'], $article->slug);
        $this->get('/preparation/first-night-in-the-tent')->assertOk()->assertSee('Cold.');
        $this->get('/nl/voorbereiding/eerste-nacht-in-de-tent')->assertOk()->assertSee('Koud.');
    }

    public function test_wrong_locale_slug_redirects_to_the_right_one(): void
    {
        $this->article();

        $this->get('/nl/voorbereiding/first-night-in-the-tent')->assertRedirect('/nl/voorbereiding/eerste-nacht-in-de-tent');
    }

    public function test_untranslated_article_falls_back_to_english_with_notice(): void
    {
        $this->article(['title' => ['en' => 'Only English'], 'body' => ['en' => '<p>Hello.</p>']]);

        $this->get('/nl/voorbereiding/only-english')
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

        $this->get('/nl/landen/duitsland')->assertOk()->assertSee('First week in Germany');
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

        foreach (['articles', 'countries', 'users', 'country-routes'] as $resource) {
            $this->get("/admin/{$resource}")->assertOk();
            $this->get("/admin/{$resource}/create")->assertOk();
        }
        $this->get('/admin/site-texts')->assertOk()->assertSee('site__home__title');
        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('First night in the tent');
    }
}
