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

        $this->getJson('/api/public/tracking')->assertJsonPath('last.lat', self::OLD[0])->assertJsonCount(1, 'points');
    }

    public function test_private_api_requires_a_trusted_login(): void
    {
        $this->getJson('/api/private/tracking')->assertUnauthorized();

        $this->actingAs($this->trusted())->getJson('/api/private/tracking')
            ->assertOk()
            ->assertJsonPath('last.lat', self::RECENT[0])
            ->assertJsonCount(2, 'points');
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
