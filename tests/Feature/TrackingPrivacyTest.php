<?php

namespace Tests\Feature;

use App\Enums\DayType;
use App\Enums\Role;
use App\Models\Country;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Models\User;
use App\Support\JourneyStats;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const RECENT = [47.1234567, 8.7654321];

    private const OLD = [52.2600000, 4.5600000];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[self::OLD, 20], [self::RECENT, 0]] as [[$lat, $lng], $daysAgo]) {
            TrackingPoint::create([
                'latitude' => $lat, 'longitude' => $lng, 'source' => 'test',
                'recorded_at' => now()->subDays($daysAgo)->subHour(), 'received_at' => now(),
            ]);
        }
    }

    private function trusted(): User
    {
        return User::factory()->create(['role' => Role::TrustedViewer]);
    }

    public function test_guests_never_receive_recent_locations_in_html_or_api(): void
    {
        foreach (['/', '/live', '/journey', '/nl/live', '/api/public/tracking'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('8.76543')->assertDontSee('47.12345');
        }

        $this->getJson('/api/public/tracking')->assertJsonPath('last.lat', self::OLD[0])->assertJsonCount(0, 'lines'); // one old point: no line yet
    }

    public function test_private_api_requires_a_trusted_login(): void
    {
        $this->getJson('/api/private/tracking')->assertUnauthorized();

        $this->actingAs($this->trusted())->getJson('/api/private/tracking')
            ->assertOk()
            ->assertJsonPath('last.lat', self::RECENT[0])
            ->assertJsonCount(0, 'lines');
    }

    public function test_public_api_stays_delayed_even_when_logged_in(): void
    {
        $this->actingAs($this->trusted())->getJson('/api/public/tracking')->assertJsonPath('last.lat', self::OLD[0]);
    }

    public function test_family_sees_the_latest_location_on_the_live_page(): void
    {
        $this->actingAs($this->trusted())->get('/live')->assertOk()->assertSee('8.76543')->assertSee('Live');
    }

    public function test_old_data_is_never_presented_as_live(): void
    {
        TrackingPoint::where('latitude', self::RECENT[0])->update(['recorded_at' => now()->subHours(5)]);

        $this->actingAs($this->trusted())->get('/live')->assertSee('not a live location');
    }

    public function test_the_delay_is_configurable(): void
    {
        Settings::set(['public_tracking_delay_hours' => 0]);

        $this->getJson('/api/public/tracking')->assertJsonPath('last.lat', self::RECENT[0]);
    }

    public function test_ingest_requires_the_token_and_ignores_duplicates(): void
    {
        config(['travel.tracking_ingest_token' => 'secret-token']);
        $payload = ['source' => 'phone', 'points' => [['lat' => 41.0, 'lng' => 29.0, 'time' => '2027-08-01T10:00:00Z', 'ele' => 40]]];

        $this->postJson('/api/tracking', $payload)->assertUnauthorized();
        $this->postJson('/api/tracking', $payload, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postJson('/api/tracking', $payload, ['Authorization' => 'Bearer secret-token'])->assertCreated()->assertJson(['stored' => 1]);
        $this->postJson('/api/tracking', $payload, ['Authorization' => 'Bearer secret-token'])->assertJson(['stored' => 0]);
    }

    public function test_diary_stories_and_new_uploads_are_delayed_for_guests(): void
    {
        $story = fn ($days) => \App\Models\Article::create(['type' => \App\Enums\ArticleType::Diary, 'title' => ['en' => "Story {$days}"], 'status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subDays($days)]);
        [$recent, $old] = [$story(1), $story(20)];
        \App\Models\Article::create(['type' => \App\Enums\ArticleType::Preparation, 'title' => ['en' => 'Gear list'], 'status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subHour()]);
        \App\Models\GalleryItem::create(['kind' => 'image', 'source' => 'upload', 'path' => 'gallery/today.jpg', 'taken_at' => now()]);
        \App\Models\GalleryItem::create(['kind' => 'image', 'source' => 'upload', 'path' => 'gallery/scotland.jpg', 'taken_at' => now()->subYear()]);

        $this->get('/journey')->assertSee('Story 20')->assertSee('Gear list')->assertDontSee('Story 1<', false);
        $this->get($recent->url())->assertNotFound();
        $this->assertSame(['gallery/scotland.jpg'], \App\Models\GalleryItem::public()->pluck('path')->all());

        $this->actingAs($this->trusted());
        $this->get($recent->url())->assertOk();
        $this->assertSame(2, \App\Models\GalleryItem::public()->count());
    }

    public function test_the_status_block_and_day_log_follow_the_delay(): void
    {
        $de = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany']]);
        JourneyDay::create(['date' => now()->subDays(20), 'type' => DayType::Walk, 'distance_km' => 25, 'start_location' => 'Nijmegen', 'end_location' => 'Kleve', 'country_id' => $de->id]);
        JourneyDay::create(['date' => now()->subDay(), 'type' => DayType::Rest, 'end_location' => 'Secret village', 'country_id' => $de->id]);

        // Visitors: the day of 20 days ago, with the delay explained; the recent rest day stays hidden.
        $this->get('/')->assertSee('Day 1')->assertSee('To Hanoi')->assertSee('runs 14 days behind')->assertDontSee('rest day');
        $this->get('/journey')->assertSee('Day by day')->assertSee('Nijmegen')->assertSee('Kleve')->assertDontSee('Secret village');
        $this->get('/')->assertDontSee('href="'.url('/live').'"', false);

        // Family: live, without the delay note, and "Live" in the menu.
        $this->actingAs($this->trusted());
        $this->get('/')->assertSee('Day 2')->assertSee('rest day')->assertDontSee('runs 14 days behind')->assertSee('href="'.url('/live').'"', false);
        $this->get('/journey')->assertSee('Secret village');
    }

    public function test_pages_have_a_share_preview_image(): void
    {
        Settings::set(['home_image' => 'site/hero.jpg']);

        $this->get('/about')->assertSee('<meta property="og:image" content="'.url('/storage/site/hero.jpg').'">', false)->assertSee('summary_large_image');
    }

    public function test_the_walked_route_is_served_by_level_of_detail(): void
    {
        // A day of walking 20 days ago (visible to visitors) and one yesterday (family only), a point every minute.
        foreach ([20, 1] as $daysAgo) {
            app(\App\Services\TrackingRecorder::class)->store(collect(range(0, 300))->map(fn ($m) => [
                'lat' => 51.0 + $m / 3000 + ($m % 2) / 20000, 'lng' => 10.0 + $m / 2000, 'time' => now()->subDays($daysAgo)->startOfDay()->addHours(8)->addMinutes($m),
            ])->all(), 'test');
        }
        $count = fn ($level, $user = null) => collect(\App\Support\WalkedTrack::lines($user, $level))->sum(fn ($line) => count($line));

        // Coarse levels are small; the finest keeps (almost) everything.
        $this->assertLessThan(10, $count(1));
        $this->assertGreaterThan(250, $count(4));

        // Visitors: only the old day; family: both days.
        $this->assertCount(1, \App\Support\WalkedTrack::lines(null, 2));
        $this->assertCount(2, \App\Support\WalkedTrack::lines($this->trusted(), 2));

        // Maps fetch detail for the visible area.
        $this->getJson('/api/track?level=4&bbox=10,51,10.1,51.05')->assertOk()->assertJsonPath('features.0.properties.type', 'actual');
        $this->getJson('/api/track?level=4&bbox=0,0,1,1')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/track?level=9&bbox=nope')->assertUnprocessable();
    }

    public function test_zoomed_in_maps_get_the_route_pieces_with_every_bend(): void
    {
        $country = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany'], 'is_published' => true]);
        $route = $country->routes()->create(['type' => \App\Enums\RouteType::Planned, 'name' => 'Path']);
        config(['travel.privacy_radius_m' => 0]); // the path starts at home
        // A zigzag path of ~10 m steps: the page embeds it straightened, zoomed in every bend comes back.
        foreach (range(0, 40) as $i) {
            $route->points()->create(['latitude' => 51.0 + $i / 10000, 'longitude' => 10.0 + ($i % 2) / 10000, 'sequence' => $i]);
        }
        $planned = fn ($level, $bbox) => collect($this->getJson("/api/track?level={$level}&bbox={$bbox}")->json('features'))->where('properties.type', 'planned');

        $this->assertCount(0, $planned(2, '9.9,50.9,10.1,51.1'));
        $this->assertCount(41, $planned(4, '9.9,50.9,10.1,51.1')->first()['geometry']['coordinates'][0]);
        $this->assertCount(0, $planned(4, '0,0,1,1'));
    }

    public function test_recent_journey_days_are_hidden_from_guest_statistics(): void
    {
        $country = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany']]);
        JourneyDay::create(['date' => now()->subDays(20), 'type' => DayType::Walk, 'distance_km' => 25, 'country_id' => $country->id]);
        JourneyDay::create(['date' => now()->subDay(), 'type' => DayType::Walk, 'distance_km' => 30, 'end_location' => 'Secret village', 'country_id' => $country->id]);

        $this->assertSame(25.0, JourneyStats::for(null)['distance_km']);
        $this->assertSame(55.0, JourneyStats::for($this->trusted())['distance_km']);
        $this->get('/journey')->assertOk()->assertSee('25 km')->assertDontSee('55 km');
        $this->get('/nl/statistieken')->assertRedirect(url('/nl/reis'));
    }
}
