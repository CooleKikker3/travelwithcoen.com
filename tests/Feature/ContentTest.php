<?php

namespace Tests\Feature;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use App\Models\EquipmentItem;
use App\Models\GalleryItem;
use App\Models\User;
use App\Models\Video;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Widgets\JourneyOverview;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_pages_render_in_both_languages(): void
    {
        $pages = ['/live' => '/nl/live', '/gear' => '/nl/uitrusting', '/gallery' => '/nl/galerij', '/login' => '/nl/inloggen'];

        foreach ($pages as $en => $nl) {
            $this->get($en)->assertOk();
            $this->get($nl)->assertOk()->assertSee('<html lang="nl">', false);
        }

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/nl/reis'))->assertDontSee(url('/nl/dagboek'))
            ->assertSee('hreflang="nl" href="'.url('/nl/reis').'"', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'))->assertSee('Disallow: /admin');
    }

    public function test_home_shows_the_plan(): void
    {
        $this->get('/')->assertSee('Road to Hanoi')->assertSee(__('site.home.direction_title'));
    }

    public function test_family_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['role' => Role::TrustedViewer, 'password' => 'a-long-password']);

        $this->post('/nl/inloggen', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/nl/inloggen', ['email' => $user->email, 'password' => 'a-long-password'])->assertRedirect('/nl/live');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_gear_items_have_their_own_page_like_articles(): void
    {
        Storage::fake('public');
        foreach (['equipment/covers/tent.jpg', 'equipment/images/tent.jpg'] as $path) {
            ob_start();
            imagejpeg(imagecreatetruecolor(3000, 1500));
            Storage::disk('public')->put($path, ob_get_clean());
        }
        $config = e(json_encode(['path' => 'equipment/images/tent.jpg', 'caption' => 'Mijn tent bij zonsondergang']));

        $tent = EquipmentItem::create([
            'name' => ['nl' => 'Tent'], 'category' => EquipmentCategory::Shelter, 'brand' => 'Durston', 'model' => 'X-Mid',
            'excerpt' => ['nl' => 'Mijn huis onderweg.'], 'cover_image' => 'equipment/covers/tent.jpg',
            'specs' => [['label' => 'Gewicht', 'value' => '1150 g'], ['label' => 'Personen', 'value' => '1']],
            'body' => ['nl' => "<p>Hij staat in twee minuten.</p><div data-type=\"customBlock\" data-config=\"{$config}\" data-id=\"image\"></div><p>En hij is licht.</p>"],
        ]);
        EquipmentItem::create(['name' => ['nl' => 'Geheim'], 'category' => EquipmentCategory::Other, 'is_public' => false]);

        // Cover and photos are re-encoded like gallery photos.
        $this->assertSame(2400, getimagesize(Storage::disk('public')->path('equipment/covers/tent.jpg'))[0]);
        $this->assertSame(2400, getimagesize(Storage::disk('public')->path('equipment/images/tent.jpg'))[0]);

        // Overview: cards with cover and short description, linking to the item; hidden items stay hidden.
        $this->get('/nl/uitrusting')->assertOk()->assertSee('Durston X-Mid')->assertSee('Mijn huis onderweg.')->assertSee($tent->url('nl'))->assertDontSee('Geheim');

        // Item page, also in English (Dutch text with a note).
        $this->get($tent->url('nl'))->assertOk()->assertSee('equipment/covers/tent.jpg')->assertSeeInOrder(['Gewicht', '1150 g', 'Hij staat in twee minuten.', 'Mijn tent bij zonsondergang', 'En hij is licht.']);
        $this->get($tent->url('en'))->assertOk()->assertSee('Hij staat in twee minuten.')->assertSee('You are reading the Dutch version');
        $this->get('/sitemap.xml')->assertSee($tent->url('nl'));
    }

    public function test_uploaded_photos_are_resized_and_stripped(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(3000, 1500);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('photos/big.jpg', ob_get_clean());

        $item = GalleryItem::create(['path' => 'photos/big.jpg']);
        $this->assertSame([2400, 1200], [$item->width, $item->height]);

        [$width] = getimagesize(Storage::disk('public')->path('photos/big.jpg'));
        $this->assertSame(2400, $width);
        $this->assertEmpty(@exif_read_data(Storage::disk('public')->path('photos/big.jpg'), 'GPS') ?: []);
    }

    public function test_photos_on_external_storage_are_processed_via_a_temporary_copy(): void
    {
        // The "r2" disk is S3-compatible; faked here, so processing takes the download → process → upload route.
        config(['travel.media_disk' => 'r2']);
        Storage::fake('r2');
        ob_start();
        imagejpeg(imagecreatetruecolor(3000, 2000));
        Storage::disk('r2')->put('gallery/remote.jpg', ob_get_clean());

        $item = GalleryItem::create(['path' => 'gallery/remote.jpg']);

        $this->assertSame([2400, 1600], [$item->width, $item->height]);
        $this->assertSame([2400, 1600], array_slice(getimagesizefromstring(Storage::disk('r2')->get('gallery/remote.jpg')), 0, 2));
        $this->assertStringStartsWith('/storage/gallery/remote.jpg', parse_url($item->url(), PHP_URL_PATH));
    }

    public function test_articles_are_written_in_dutch_first_and_story_links_count_clicks(): void
    {
        $article = \App\Models\Article::create([
            'type' => \App\Enums\ArticleType::Preparation, 'title' => ['nl' => 'Mijn nieuwe schoenen'], 'body' => ['nl' => '<p>Ze lopen fijn.</p>'],
            'status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subDay(),
        ]);

        // The English site shows the Dutch text with a note; both languages have a URL.
        $this->get($article->url('en'))->assertOk()->assertSee('Mijn nieuwe schoenen')->assertSee('Ze lopen fijn.')->assertSee('You are reading the Dutch version');
        $this->get($article->url('nl'))->assertOk()->assertDontSee('Je leest de Engelse versie');

        // The story link counts people, not link previews, and leads to the article.
        $this->get('/s/'.$article->id.'/nl')->assertRedirect($article->url('nl'));
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/s/'.$article->id.'/en')->assertRedirect($article->url('en'));
        $this->assertSame(1, \App\Models\StoryClick::count());

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->get('/admin/statistics')->assertOk();
        Livewire::test(\App\Filament\Widgets\StoryClicks::class)->assertSee('Insta-story-klikken')->assertSee('Mijn nieuwe schoenen');
        $this->get('/admin/insta-story?article='.$article->id)->assertSee('\/s\/'.$article->id.'\/nl', false); // the tracking link (JSON in the page)
    }

    public function test_all_cms_screens_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $this->get('/admin')->assertOk()->assertSee('Snel naar')->assertSee('admin-drafts', false);
        $this->get('/admin/settings')->assertOk()->assertSee('Bezoekers zien je locatie van');

        $this->get('/admin')->assertDontSee('Laatste locatie-update');
        $this->get('/admin/statistics')->assertOk()->assertSee('Statistieken');
        $story = \App\Models\Article::create(['type' => \App\Enums\ArticleType::Diary, 'title' => ['en' => 'Over the Alps', 'nl' => 'Over de Alpen']]);
        $this->get('/admin/insta-story?article='.$story->id)->assertOk()->assertSee('Link kopiëren')->assertSee('Over de Alpen')->assertSee('nieuw verhaal!');
        $dutchOnly = \App\Models\Article::create(['type' => \App\Enums\ArticleType::Diary, 'title' => ['nl' => 'Alleen Nederlands']]);
        $this->get('/admin/insta-story?article='.$dutchOnly->id)->assertOk()->assertSee('Alleen Nederlands')->assertDontSee('<option value="en">', false);
        $this->get('/admin/counter-story')->assertOk()->assertSee('Tot nu toe (all-time)'); // no journey days yet

        // Counter story: today's numbers and straight-line distances from home (Lisse) to the newest GPS point.
        $start = \App\Models\TrackingPoint::create(['latitude' => 52.2575, 'longitude' => 4.5570, 'recorded_at' => now()->subHours(8), 'received_at' => now(), 'source' => 'manual']);
        $end = \App\Models\TrackingPoint::create(['latitude' => 52.3676, 'longitude' => 4.9041, 'recorded_at' => now()->subHour(), 'received_at' => now(), 'source' => 'manual']);
        \App\Models\JourneyDay::create(['date' => now()->toDateString(), 'type' => \App\Enums\DayType::Walk, 'distance_km' => 31.5, 'walking_minutes' => 412,
            'start_point_id' => $start->id, 'end_point_id' => $end->id, 'start_location' => 'Lisse', 'end_location' => 'Amsterdam']);
        $story = (new \App\Filament\Pages\CounterStory)->storyData();
        $data = $story['variants']['live'];
        $today = collect($data['locales']['nl']['today']['items'])->pluck('value', 'key');
        $this->assertSame(['Dag 1', '31,5', '6:52', '26,6', '27'], [$data['locales']['nl']['today']['headline'], $today['km'], $today['time'], $today['crow_day'], $today['crow_home']]);
        $this->assertSame('Lisse → Amsterdam', $data['locales']['nl']['today']['route']);
        $this->assertSame('so far', strtolower($data['locales']['en']['total']['headline']));
        $this->assertGreaterThan(0, $data['crow']);
        // With the delay (default) visitors' numbers: today's walk is not visible yet.
        $this->assertSame('Vandaag', $story['variants']['delayed']['locales']['nl']['today']['headline']);
        $this->assertSame([], $story['variants']['delayed']['locales']['nl']['today']['items']);
        $this->get('/admin/counter-story')->assertSee('Met vertraging, zoals bezoekers het zien (14 dagen terug)')->assertSee('Uit de galerij');
        Livewire::test(JourneyOverview::class)->assertOk()->assertSee('Laatste locatie-update');
        Livewire::test(SettingsPage::class)
            ->set('data.public_tracking_delay_hours', 168)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(168, Settings::get('public_tracking_delay_hours'));

        foreach (['tracking-points', 'journey-days', 'journey-events', 'equipment-items', 'gallery-items'] as $resource) {
            $this->get("/admin/{$resource}")->assertOk();
            $this->get("/admin/{$resource}/create")->assertOk();
        }
    }
}
