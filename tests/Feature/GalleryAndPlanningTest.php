<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\Role;
use App\Filament\Pages\RoutePlanner;
use App\Models\Article;
use App\Models\Country;
use App\Models\GalleryItem;
use App\Models\User;
use App\Models\Video;
use App\Services\YouTubeSync;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryAndPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function imageBlock(string $path, string $caption): string
    {
        $config = htmlspecialchars(json_encode(['path' => $path, 'caption' => $caption]), ENT_QUOTES);

        return "<p>Intro</p><div data-type=\"customBlock\" data-config=\"{$config}\" data-id=\"image\"></div><p>Outro</p>";
    }

    private function article(array $attributes = []): Article
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/images/sand.jpg', (function () {
            ob_start();
            imagejpeg(imagecreatetruecolor(20, 20));

            return ob_get_clean();
        })());

        return Article::create($attributes + [
            'type' => ArticleType::Diary,
            'title' => ['en' => 'Sand roads', 'nl' => 'Zandwegen'],
            'body' => [
                'en' => $this->imageBlock('articles/images/sand.jpg', 'Walking on a sandy road near Berlin'),
                'nl' => $this->imageBlock('articles/images/sand.jpg', 'Lopen over een zandweg bij Berlijn'),
            ],
            'status' => ArticleStatus::Published,
            'published_at' => now()->subHour(),
            'tags' => ['camping', 'germany'],
        ]);
    }

    public function test_images_in_the_text_render_with_caption_and_appear_in_the_gallery(): void
    {
        $article = $this->article();

        $this->get('/diary/sand-roads')->assertOk()->assertSee('<figcaption>Walking on a sandy road near Berlin</figcaption>', false);
        $this->get('/nl/dagboek/zandwegen')->assertSee('Lopen over een zandweg bij Berlijn');

        $item = GalleryItem::sole();
        $this->assertSame($article->id, $item->article_id);
        $this->assertSame('Lopen over een zandweg bij Berlijn', $item->translate('caption', 'nl'));
        $this->get('/gallery')->assertSee('storage/articles/images/sand.jpg', false)->assertSee('From: Sand roads');

        // Removing the image from the text removes it from the gallery.
        $article->update(['body' => ['en' => '<p>No images</p>']]);
        $this->assertSame(0, GalleryItem::count());
    }

    public function test_images_of_draft_articles_stay_out_of_the_gallery(): void
    {
        $this->article(['status' => ArticleStatus::Draft]);

        $this->assertSame(1, GalleryItem::count());
        $this->get('/gallery')->assertDontSee('sand.jpg');
    }

    public function test_articles_can_be_filtered_on_tags(): void
    {
        $this->article();
        Article::create(['type' => ArticleType::Diary, 'title' => ['en' => 'Other'], 'status' => ArticleStatus::Published, 'published_at' => now()->subHour(), 'tags' => ['food']]);

        $this->get('/diary?tag=camping')->assertSee('Sand roads')->assertDontSee('>Other<', false);
        $this->get('/diary')->assertSee('#camping')->assertSee('#food');
    }

    public function test_youtube_videos_are_synced_from_the_channel_feed(): void
    {
        config(['travel.youtube_channel' => 'https://www.youtube.com/@SomeChannel']);
        Http::fake([
            'www.youtube.com/@SomeChannel' => Http::response('<script>{"externalId":"UCabcdefghijklmnopqrstuv"}</script>'),
            'www.youtube.com/feeds/*' => Http::response(<<<'XML'
                <feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/">
                  <entry>
                    <yt:videoId>dQw4w9WgXcQ</yt:videoId>
                    <title>Walking across Europe</title>
                    <published>2027-08-01T10:00:00+00:00</published>
                    <media:group><media:description>Episode 1</media:description></media:group>
                  </entry>
                </feed>
                XML),
        ]);

        $this->assertSame(1, app(YouTubeSync::class)->sync());
        $this->assertSame(1, app(YouTubeSync::class)->sync()); // idempotent

        $video = GalleryItem::sole();
        $this->assertTrue($video->isYoutube());
        $this->assertSame('Walking across Europe', $video->translate('caption'));

        // Mixed with uploads in one gallery, newest first.
        GalleryItem::create(['kind' => 'image', 'source' => 'upload', 'path' => 'gallery/old.jpg', 'taken_at' => '2027-07-01', 'caption' => ['en' => 'Older photo']]);
        $this->get('/gallery')->assertSeeInOrder(['data-src="dQw4w9WgXcQ"', 'Older photo'], false);
        $this->get('/gallery?kind=image')->assertDontSee('dQw4w9WgXcQ');
    }

    public function test_the_wall_links_to_older_items_for_infinite_scroll(): void
    {
        foreach (range(1, 30) as $i) {
            GalleryItem::create(['kind' => 'youtube', 'source' => 'youtube', 'youtube_id' => sprintf('vid%08d', $i), 'taken_at' => now()->subDays($i), 'caption' => ['en' => "Video {$i}"]]);
        }

        $this->get('/gallery')->assertOk()->assertSee('data-wall-next', false)->assertSee('?page=2', false)->assertSee('wall-tile--wide', false);
        $this->get('/nl/galerij?page=2')->assertOk()->assertSee('Video 30')->assertDontSee('data-wall-next', false);
    }

    public function test_a_route_can_be_drawn_in_the_planner(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $country = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands']]);

        Livewire::test(RoutePlanner::class)
            ->set('countryId', $country->id)
            ->set('routing', 'straight')
            ->call('save', [[52.26, 4.56, 0], [52.96, 4.76, 1]], [[52.26, 4.56], [52.96, 4.76]])
            ->assertSet('route', fn ($id) => $id !== null);

        $route = $country->routes()->sole();
        $this->assertSame(2, $route->points()->count());
        $this->assertEqualsWithDelta(79, $route->distance_km, 3);
        $this->get('/admin/route-planner?route='.$route->id)->assertOk()->assertSee('data-route-planner', false);
    }
}
