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

    public function test_dutch_visitors_get_the_dutch_home_page_unless_they_choose_english(): void
    {
        $this->get('/', ['Accept-Language' => 'nl-NL,nl;q=0.9,en;q=0.8'])->assertRedirect(url('/nl'));
        $this->get('/', ['CF-IPCountry' => 'NL', 'Accept-Language' => 'en-US'])->assertRedirect(url('/nl'));
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk();
        $this->get('/about', ['Accept-Language' => 'nl-NL'])->assertOk(); // only the home page redirects

        // Choosing English with the language switch is remembered.
        $this->get('/?lang=en', ['Accept-Language' => 'nl-NL'])->assertRedirect(url('/'))->assertCookie('locale', 'en');
        $this->withCookie('locale', 'en')->get('/', ['Accept-Language' => 'nl-NL'])->assertOk();
        $this->withCookie('locale', 'nl')->get('/', ['Accept-Language' => 'en-US'])->assertRedirect(url('/nl'));
    }

    public function test_the_home_map_runs_an_open_line_from_the_end_of_the_plan_to_hanoi(): void
    {
        $open = fn () => collect($this->get('/')->viewData('overview')['features'])->firstWhere('properties.type', 'open')['geometry']['coordinates'];

        // Nothing planned yet: straight from the start (Lisse) to Hanoi.
        $this->assertSame([[4.557, 52.2575], [105.8542, 21.0285]], $open());

        $country = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands'], 'is_published' => true]);
        $route = $country->routes()->create(['type' => RouteType::Planned, 'name' => 'Plan']);
        foreach ([[52.26, 4.56], [51.85, 5.86]] as $i => [$lat, $lng]) {
            $route->points()->create(['latitude' => $lat, 'longitude' => $lng, 'sequence' => $i]);
        }

        $this->assertSame([[5.86, 51.85], [105.8542, 21.0285]], $open());
    }

    public function test_a_closed_website_shows_coming_soon_except_to_admins_and_preview_ips(): void
    {
        \App\Support\Settings::set(['site_open' => false]);
        config(['travel.preview_ips' => ['10.0.0.5']]);

        $this->get('/about')->assertStatus(503)->assertSee('Something is coming')->assertDontSee('About the project');
        $this->get('/nl')->assertStatus(503)->assertSee('Er komt iets aan');
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->get('/about')->assertOk();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get('/about')->assertOk();

        \App\Support\Settings::set(['site_open' => true]);
        $this->get('/about')->assertOk();
    }

    public function test_journey_days_can_be_started_and_ended_later_from_the_dashboard(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $widget = \App\Filament\Widgets\QuickActions::class;

        // GPS locations: the latest is chosen by default, and only the newest 5 are loaded.
        foreach (range(1, 7) as $i) {
            \App\Models\TrackingPoint::create(['latitude' => 52 + $i / 100, 'longitude' => 4.5, 'source' => 'test', 'recorded_at' => now()->subDays(2)->addMinutes($i), 'received_at' => now()]);
        }
        $latest = \App\Models\TrackingPoint::latest('recorded_at')->first();
        $select = \App\Filament\Support\TrackingPointSelect::make('p', 'P');
        $this->assertSame($latest->id, $select->getDefaultState());

        // No signal yesterday: started afterwards, with yesterday's time.
        Livewire::test($widget)->mountAction('startDay')->assertActionDataSet(['start_point_id' => $latest->id]);
        Livewire::test($widget)
            ->assertActionHidden('endDay')
            ->callAction('startDay', ['started_at_other' => true, 'started_at' => now()->subDay()->setTime(8, 30), 'start_location' => 'Lisse']);
        $day = \App\Models\JourneyDay::sole();
        $this->assertSame(now()->subDay()->toDateString(), $day->date->toDateString());
        $this->assertSame($latest->id, $day->start_point_id);

        // Today started too, before yesterday was ended: choose which day to end.
        Livewire::test($widget)->callAction('startDay', ['started_at_other' => true, 'started_at' => now()->startOfDay()]);
        Livewire::test($widget)->assertActionDisabled('startDay')->assertActionDisabled('restDay'); // today is set
        Livewire::test($widget)
            ->assertActionVisible('endDay')
            ->callAction('endDay', ['day_id' => $day->id, 'ended_at_other' => true, 'ended_at' => now()->subDay()->setTime(16, 0), 'end_location' => 'Haarlem', 'distance_km' => 21.5, 'sleep_photo' => \Illuminate\Http\UploadedFile::fake()->image('tent.jpg', 400, 300)]);

        $day->refresh();
        $this->assertSame('Haarlem', $day->end_location);
        $this->assertSame(450, $day->walking_minutes);
        $this->assertSame(1, \App\Models\JourneyDay::whereNull('ended_at')->count()); // today is still open

        // Photo of the sleeping spot: in the gallery, but for visitors only after the tracking delay.
        auth()->logout();
        $photo = \App\Models\GalleryItem::sole();
        $this->assertSame([$day->id, 'Waar ik sliep: Haarlem', true], [$photo->journey_day_id, $photo->translate('caption', 'nl'), $photo->is_sleeping_spot]);
        $this->assertSame(0, \App\Models\GalleryItem::public()->count());
        $this->travel(15)->days();
        $this->assertSame(1, \App\Models\GalleryItem::public()->count());
    }

    public function test_a_journey_day_gets_the_local_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Support\Facades\FilamentTimezone::set('Asia/Ho_Chi_Minh');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-11-02 23:30', 'UTC')); // 06:30 on 3 November in Hanoi

        Livewire::test(\App\Filament\Widgets\QuickActions::class)->callAction('startDay');

        $this->assertSame('2027-11-03', \App\Models\JourneyDay::sole()->date->toDateString());
    }

    public function test_a_day_can_be_marked_as_rest_day(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $widget = \App\Filament\Widgets\QuickActions::class;

        Livewire::test($widget)->assertActionEnabled('restDay')->callAction('restDay', ['date' => today()->toDateString()]);

        $this->assertSame(\App\Enums\DayType::Rest, \App\Models\JourneyDay::sole()->type);
        Livewire::test($widget)->assertActionDisabled('restDay')->assertActionDisabled('startDay');

        // Days are numbered in order, rest days included: walk, rest (today), walk.
        \App\Models\JourneyDay::create(['date' => today()->subDay(), 'type' => \App\Enums\DayType::Walk]);
        \App\Models\JourneyDay::create(['date' => today()->addDay(), 'type' => \App\Enums\DayType::Walk]);
        $names = \App\Models\JourneyDay::select('journey_days.*')->withNumber()->orderBy('date')->get()->map->name()->all();
        $this->assertSame(['Day 1', 'Day 2', 'Day 3'], $names);
        $this->get('/admin/journey-days')->assertOk()->assertSeeInOrder(['Dag 3', 'Dag 2', 'Dag 1']);
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
        $this->article(['type' => ArticleType::Diary, 'title' => ['en' => 'First week in Germany'], 'country_id' => $country->id, 'published_at' => now()->subDays(20)]);

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
