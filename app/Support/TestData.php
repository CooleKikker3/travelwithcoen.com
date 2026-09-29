<?php

namespace App\Support;

use App\Enums\CountryStatus;
use App\Enums\DayType;
use App\Enums\Overnight;
use App\Models\Country;
use App\Models\JourneyDay;
use App\Models\TrackingPoint;
use App\Services\TrackingRecorder;
use Illuminate\Support\Carbon;

/**
 * Test data for trying out and performance checks: journey days with GPS points every 10 minutes along a route
 * Lisse → Utrecht → Germany → Poland → Slovakia → Hungary → Romania → Bulgaria → Istanbul (like a Garmin inReach). Everything is marked (days: notes "Testdata",
 * points: source "test") so it can be removed again; country statuses are restored on removal.
 * Commands: testdata:add {days}, testdata:remove.
 */
class TestData
{
    public const NOTE = 'Testdata';

    public const SOURCE = 'test';

    private const STATUS_KEY = 'testdata_country_status';

    /** Waypoints [lat, lng, place] — roughly 25 km apart, so about one per walking day. */
    private const ROUTE = [
        [52.2575, 4.5570, 'Lisse'], [52.1290, 4.6570, 'Alphen aan den Rijn'], [52.0907, 5.1214, 'Utrecht'], [52.0240, 5.5580, 'Veenendaal'],
        [51.9851, 5.8987, 'Arnhem'], [51.8420, 6.1000, 'Emmerich'], [51.7890, 6.1370, 'Kleve'], [51.6590, 6.4460, 'Kalkar'],
        [51.5510, 6.7290, 'Dinslaken'], [51.4556, 7.0116, 'Essen'], [51.5136, 7.4653, 'Dortmund'], [51.5710, 7.8930, 'Unna'],
        [51.5700, 8.1070, 'Soest'], [51.5540, 8.5580, 'Geseke'], [51.4640, 8.8960, 'Brilon'], [51.4050, 9.2100, 'Korbach'],
        [51.3127, 9.4797, 'Kassel'], [51.2700, 9.8600, 'Hessisch Lichtenau'], [51.1880, 10.0480, 'Eschwege'], [51.0200, 10.3700, 'Bad Langensalza'],
        [50.9848, 11.0299, 'Erfurt'], [50.9800, 11.3200, 'Weimar'], [50.9270, 11.5890, 'Jena'], [50.8800, 12.0800, 'Gera'],
        [50.9020, 12.4300, 'Altenburg'], [51.0300, 12.7200, 'Rochlitz'], [51.0500, 13.0300, 'Döbeln'], [51.0504, 13.7373, 'Dresden'],
        [51.1100, 14.0400, 'Radeberg'], [51.1800, 14.4300, 'Bautzen'], [51.1500, 14.9800, 'Görlitz'], [51.1600, 15.3200, 'Lubań'],
        [51.1600, 15.6100, 'Bolesławiec'], [51.2100, 16.1600, 'Legnica'], [51.1500, 16.5300, 'Środa Śląska'], [51.1079, 17.0385, 'Wrocław'],
        [50.9800, 17.3100, 'Oława'], [50.8600, 17.4700, 'Brzeg'], [50.6751, 17.9213, 'Opole'], [50.5500, 18.3000, 'Strzelce Opolskie'],
        [50.4000, 18.6500, 'Gliwice'], [50.2649, 19.0238, 'Katowice'], [50.1500, 19.4000, 'Chrzanów'], [50.0647, 19.9450, 'Kraków'],
        [49.6800, 20.0700, 'Mszana Dolna'], [49.4800, 20.0300, 'Nowy Targ'], [49.2900, 20.2300, 'Łysa Polana'], [49.0600, 20.3000, 'Poprad'],
        [48.9500, 20.5600, 'Spišská Nová Ves'], [48.7200, 21.2600, 'Košice'], [48.5100, 21.1900, 'Hidasnémeti'], [48.1035, 20.7784, 'Miskolc'],
        [47.9000, 21.0600, 'Tiszaújváros'], [47.5316, 21.6273, 'Debrecen'], [47.2000, 21.8500, 'Berettyóújfalu'], [47.0465, 21.9189, 'Oradea'],
        [46.9400, 22.3700, 'Aleșd'], [46.7712, 23.6236, 'Cluj-Napoca'], [46.3200, 23.7200, 'Turda'], [46.0700, 23.5800, 'Alba Iulia'],
        [45.7983, 24.1256, 'Sibiu'], [45.4300, 24.2800, 'Călimănești'], [45.1000, 24.3700, 'Râmnicu Vâlcea'], [44.8565, 24.8692, 'Pitești'],
        [44.4268, 26.1025, 'București'], [44.0500, 26.0000, 'Giurgiu'], [43.8356, 25.9657, 'Ruse'], [43.4200, 25.6300, 'Byala'],
        [43.0757, 25.6172, 'Veliko Tarnovo'], [42.6500, 25.4000, 'Kazanlak'], [42.4258, 25.6345, 'Stara Zagora'], [41.9344, 25.5554, 'Haskovo'],
        [41.7400, 26.2000, 'Svilengrad'], [41.6771, 26.5557, 'Edirne'], [41.4000, 27.3500, 'Lüleburgaz'], [41.1500, 27.8000, 'Çorlu'],
        [41.0700, 28.3000, 'Silivri'], [41.0082, 28.9784, 'İstanbul'],
    ];

    /** @return array{days: int, points: int} */
    public function add(int $days, float $intervalMinutes = 10): array
    {
        $this->remove();
        $this->addCountries();
        Settings::set([self::STATUS_KEY => Country::pluck('status', 'id')->map(fn ($status) => $status->value)->all()]);

        $zone = 'Europe/Amsterdam';
        $recorder = app(TrackingRecorder::class);
        // The whole route is spread over the walking days: each walking day covers an equal share.
        $walkDays = max(1, $days - intdiv($days, 6));
        $walked = 0;
        $points = 0;

        for ($d = 0; $d < $days; $d++) {
            $date = now($zone)->subDays($days - $d)->toDateString();
            if (JourneyDay::whereDate('date', $date)->exists()) {
                continue; // never touch a real journey day
            }
            $rest = $d > 0 && $d % 6 === 5;
            [$fromAt, $toAt] = [$walked / $walkDays, ($walked + 1) / $walkDays];
            $from = self::along($fromAt);

            if ($rest) {
                JourneyDay::create(['date' => $date, 'type' => DayType::Rest, 'distance_km' => 0, 'end_location' => $from[2], 'overnight' => Overnight::Host, 'notes' => self::NOTE]);

                continue;
            }

            $to = self::along($toAt);
            $walked++;
            $start = Carbon::parse("{$date} 08:00", $zone)->addMinutes(mt_rand(0, 60));
            $minutes = 360 + mt_rand(0, 150);
            // A point every interval, and never more than 1 km apart (about 10 minutes of walking).
            $steps = (int) max(ceil($minutes / $intervalMinutes), ceil(($toAt - $fromAt) * self::routeKm() / self::MAX_STEP_KM));
            $track = [];
            for ($step = 0; $step <= $steps; $step++) {
                $m = $minutes * $step / $steps;
                $here = self::along($fromAt + ($toAt - $fromAt) * $step / $steps);
                $wobble = ($step === 0 || $step === $steps) ? 0 : mt_rand(-2, 2) / 10000; // a few metres of GPS noise
                $track[] = [
                    'lat' => round($here[0] + $wobble, 6),
                    'lng' => round($here[1] - $wobble, 6),
                    'ele' => mt_rand(0, 3000) / 10,
                    'time' => $start->copy()->addSeconds((int) round($m * 60))->utc()->toIso8601String(),
                ];
            }
            $points += $recorder->store($track, self::SOURCE);

            JourneyDay::create([
                'date' => $date,
                'type' => DayType::Walk,
                'started_at' => $start->copy()->utc(),
                'ended_at' => $start->copy()->addMinutes($minutes)->utc(),
                'start_location' => $from[2],
                'end_location' => $to[2],
                'distance_km' => round(RouteGeometry::distanceKm(array_map(fn ($p) => [$p['lat'], $p['lng']], $track)), 1),
                'walking_minutes' => $minutes,
                'overnight' => [Overnight::WildCamping, Overnight::Campsite, Overnight::Hostel][$d % 3],
                'notes' => self::NOTE,
            ]);
        }

        // Each day gets the country of its last point.
        JourneyDay::where('notes', self::NOTE)->where('type', DayType::Walk)->get()->each(fn (JourneyDay $day) => $day->update([
            'country_id' => TrackingPoint::where('source', self::SOURCE)->where('recorded_at', '<=', $day->ended_at)->latest('recorded_at')->value('country_id'),
        ]));
        JourneyDay::where('notes', self::NOTE)->where('type', DayType::Rest)->get()->each(fn (JourneyDay $day) => $day->update([
            'country_id' => JourneyDay::where('date', '<', $day->date)->whereNotNull('country_id')->latest('date')->value('country_id'),
        ]));

        return ['days' => $days, 'points' => $points];
    }

    private const MAX_STEP_KM = 1.0;

    /** @return list<float> km of each stretch between two waypoints */
    private static function lengths(): array
    {
        static $lengths;

        return $lengths ??= array_map(fn ($i) => RouteGeometry::distanceKm([array_slice(self::ROUTE[$i], 0, 2), array_slice(self::ROUTE[$i + 1], 0, 2)]), range(0, count(self::ROUTE) - 2));
    }

    private static function routeKm(): float
    {
        return array_sum(self::lengths());
    }

    /** Point at a fraction (0–1) of the whole route, with the name of the nearest waypoint: [lat, lng, place]. */
    private static function along(float $fraction): array
    {
        $lengths = self::lengths();
        $target = max(0, min(1, $fraction)) * array_sum($lengths);

        foreach ($lengths as $i => $length) {
            if ($target <= $length || $i === count($lengths) - 1) {
                $t = $length > 0 ? min(1, $target / $length) : 0;
                [$a, $b] = [self::ROUTE[$i], self::ROUTE[$i + 1]];

                return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $t < 0.5 ? $a[2] : $b[2]];
            }
            $target -= $length;
        }

        return self::ROUTE[0];
    }

    private const COUNTRIES_KEY = 'testdata_countries';

    /** Countries of the test route; missing ones are created hidden (quietly: no re-assigning while adding). */
    private function addCountries(): void
    {
        $created = [];
        foreach (['SK' => ['Slovakia', 'Slowakije'], 'HU' => ['Hungary', 'Hongarije'], 'RO' => ['Romania', 'Roemenië'], 'BG' => ['Bulgaria', 'Bulgarije']] as $iso => [$en, $nl]) {
            if (! Country::where('iso_code', $iso)->exists()) {
                $created[] = Country::createQuietly(['iso_code' => $iso, 'name' => ['en' => $en, 'nl' => $nl], 'slug' => ['en' => strtolower($en), 'nl' => strtolower($nl)], 'is_published' => false, 'sort_order' => 900])->id;
            }
        }
        Settings::set([self::COUNTRIES_KEY => $created]);
        app()->forgetScopedInstances(); // CountryLocator must know the new countries
    }

    /** @return array{days: int, points: int} */
    public function remove(): array
    {
        $days = JourneyDay::where('notes', self::NOTE)->delete();
        $points = TrackingPoint::where('source', self::SOURCE)->delete();

        // Country statuses back to how they were before the test data.
        foreach (Settings::get(self::STATUS_KEY) ?? [] as $id => $status) {
            Country::whereKey($id)->update(['status' => $status]);
        }
        Settings::set([self::STATUS_KEY => null]);
        Country::whereIn('id', Settings::get(self::COUNTRIES_KEY) ?? [])->each(fn (Country $country) => $country->deleteQuietly());
        Settings::set([self::COUNTRIES_KEY => null]);
        app()->forgetScopedInstances();
        if ($days || $points) {
            app(TrackingRecorder::class)->updateCurrentCountry();
            WalkedTrack::rebuildAll();
        }

        return ['days' => $days, 'points' => $points];
    }
}
