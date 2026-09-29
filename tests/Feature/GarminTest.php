<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\TrackingPoint;
use App\Services\GarminMapShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GarminTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = <<<'KML'
        <?xml version="1.0" encoding="utf-8"?>
        <kml xmlns="http://www.opengis.net/kml/2.2">
          <Document><Folder>
            <Placemark>
              <TimeStamp><when>2027-06-28T08:00:00Z</when></TimeStamp>
              <ExtendedData><Data name="Event"><value>Tracking message received.</value></Data></ExtendedData>
              <Point><coordinates>5.860000,51.840000,12.50</coordinates></Point>
            </Placemark>
            <Placemark>
              <TimeStamp><when>2027-06-28T08:10:00Z</when></TimeStamp>
              <Point><coordinates>5.950000,51.830000,14.00</coordinates></Point>
            </Placemark>
            <Placemark>
              <LineString><coordinates>5.86,51.84,0 5.95,51.83,0</coordinates></LineString>
            </Placemark>
          </Folder></Document>
        </kml>
        KML;

    public function test_positions_are_fetched_from_the_mapshare_feed(): void
    {
        Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands']]);
        config(['travel.garmin.mapshare_url' => 'https://share.garmin.com/Feed/Share/coen', 'travel.garmin.mapshare_password' => 'secret']);
        Http::fake(['share.garmin.com/*' => Http::response(self::FEED)]);

        $this->artisan('garmin:sync')->expectsOutput('2 new position(s) from Garmin.')->assertSuccessful();
        $this->artisan('garmin:sync')->expectsOutput('0 new position(s) from Garmin.'); // same points again: ignored

        $this->assertSame(2, TrackingPoint::where('source', 'garmin')->count());
        $this->assertSame([51.84, 5.86, 12.5], [(float) TrackingPoint::first()->latitude, (float) TrackingPoint::first()->longitude, (float) TrackingPoint::first()->altitude]);
        $this->assertNotNull(TrackingPoint::first()->country_id);

        // With a MapShare password: sent as basic auth; only points since shortly before the last one are asked.
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode(':secret')) && str_contains($request->url(), 'd1='));
    }

    public function test_nothing_happens_without_a_mapshare_address(): void
    {
        Http::fake();
        $this->assertSame(0, app(GarminMapShare::class)->sync());
        Http::assertNothingSent();
    }
}
