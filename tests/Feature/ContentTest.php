<?php

namespace Tests\Feature;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
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

    public function test_equipment_page_shows_pack_weight(): void
    {
        EquipmentItem::create(['name' => ['en' => 'Tent'], 'category' => EquipmentCategory::Shelter, 'status' => EquipmentStatus::Carried, 'weight_g' => 1200]);
        EquipmentItem::create(['name' => ['en' => 'Boots'], 'category' => EquipmentCategory::Footwear, 'status' => EquipmentStatus::Carried, 'weight_g' => 900, 'is_worn' => true]);
        EquipmentItem::create(['name' => ['en' => 'Secret'], 'category' => EquipmentCategory::Other, 'status' => EquipmentStatus::Carried, 'weight_g' => 5000, 'is_public' => false]);

        $this->get('/gear')->assertSee('Tent')->assertSee('1.20 kg')->assertSee('0.90 kg')->assertDontSee('Secret');
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

    public function test_all_cms_screens_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $this->get('/admin')->assertOk()->assertSee('Snel naar')->assertSee('admin-drafts', false);
        $this->get('/admin/settings')->assertOk()->assertSee('Bezoekers zien je locatie van');

        $this->get('/admin')->assertDontSee('Laatste locatie-update');
        $this->get('/admin/statistics')->assertOk()->assertSee('Statistieken');
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
