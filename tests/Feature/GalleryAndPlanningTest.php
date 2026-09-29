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
use App\Support\RouteGeometry;
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
            'published_at' => now()->subDays(20),
            'tags' => ['camping', 'germany'],
        ]);
    }

    public function test_images_in_the_text_render_with_caption_and_appear_in_the_gallery(): void
    {
        // The cover image is not a gallery item; only images in the text are.
        $article = $this->article(['cover_image' => 'articles/covers/cover.jpg']);

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

    public function test_sensitive_images_are_blurred_with_a_warning(): void
    {
        $config = htmlspecialchars(json_encode(['path' => 'articles/images/sand.jpg', 'caption' => 'My foot', 'sensitive' => true]), ENT_QUOTES);
        $this->article(['body' => ['en' => "<div data-type=\"customBlock\" data-config=\"{$config}\" data-id=\"image\"></div>"]]);

        $this->assertTrue(GalleryItem::sole()->is_sensitive);
        $this->get('/diary/sand-roads')->assertSee('data-sensitive', false)->assertSee('Show anyway');
        $this->get('/gallery')->assertSee('wall-tile--sensitive', false)->assertSee('data-sensitive="1"', false);
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
        Article::create(['type' => ArticleType::Diary, 'title' => ['en' => 'Other'], 'status' => ArticleStatus::Published, 'published_at' => now()->subDays(20), 'tags' => ['food']]);

        // All stories on their own page, filterable on tags; the Journey page shows the latest with a link.
        $this->get('/stories?tag=camping')->assertOk()->assertSee('Sand roads')->assertDontSee('>Other<', false);
        $this->get('/nl/verhalen')->assertOk()->assertSee('#camping')->assertSee('#food');
        $this->get('/journey')->assertSeeInOrder([__('site.preparation.title'), __('site.journey.stories_title')])->assertSee(url('/stories'));

        // Old addresses keep working.
        $this->get('/journey?tag=camping')->assertRedirect(url('/stories?tag=camping'));
        $this->get('/diary?tag=camping')->assertRedirect(url('/stories?type=diary&tag=camping'));
        $this->get('/nl/voorbereiding')->assertRedirect(url('/nl/verhalen?type=preparation'));

        // Menu: Stories in, Live out (the page itself still works, e.g. for family).
        $this->get('/')->assertSee(url('/stories'))->assertDontSee('href="'.url('/live').'"', false);
        $this->get('/live')->assertOk();
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

        // A video from a previously configured channel is cleaned up.
        GalleryItem::create(['kind' => 'youtube', 'source' => 'youtube', 'youtube_id' => 'oldchannel1', 'youtube_channel_id' => 'UColdoldoldoldoldoldold1', 'taken_at' => now()]);

        $this->assertSame(1, app(YouTubeSync::class)->sync());
        $this->assertSame(0, app(YouTubeSync::class)->sync()); // already known: nothing added
        $this->assertDatabaseMissing('gallery_items', ['youtube_id' => 'oldchannel1']);

        $video = GalleryItem::sole();
        $this->assertTrue($video->isYoutube());
        $this->assertSame('Walking across Europe', $video->translate('caption'));

        // Mixed with uploads in one gallery, newest first.
        GalleryItem::create(['kind' => 'image', 'source' => 'upload', 'path' => 'gallery/old.jpg', 'taken_at' => '2026-07-01', 'caption' => ['en' => 'Older photo']]);
        $this->get('/gallery')->assertSeeInOrder(['data-src="dQw4w9WgXcQ"', 'Older photo'], false);
    }

    public function test_the_wall_links_to_older_items_for_infinite_scroll(): void
    {
        foreach (range(1, 30) as $i) {
            GalleryItem::create(['kind' => 'youtube', 'source' => 'youtube', 'youtube_id' => sprintf('vid%08d', $i), 'taken_at' => now()->subDays($i), 'caption' => ['en' => "Video {$i}"]]);
        }

        $this->get('/gallery')->assertOk()->assertSee('data-wall-next', false)->assertSee('?page=2', false)->assertSee('wall-tile--wide', false);
        $this->get('/nl/galerij?page=2')->assertOk()->assertSee('Video 30')->assertDontSee('data-wall-next', false);
    }

    public function test_unused_media_files_are_pruned_after_a_week(): void
    {
        $this->article(['cover_image' => 'articles/covers/cover.jpg']); // body uses articles/images/sand.jpg
        $disk = Storage::disk('public');
        foreach (['articles/covers/cover.jpg', 'articles/covers/old.jpg', 'gallery/removed.jpg', 'gallery/fresh.jpg', 'site/hero.jpg'] as $path) {
            $disk->put($path, 'x');
            touch($disk->path($path), now()->subDays(8)->getTimestamp());
        }
        touch($disk->path('articles/images/sand.jpg'), now()->subDays(8)->getTimestamp());
        touch($disk->path('gallery/fresh.jpg')); // uploaded just now: kept for a week
        \App\Support\Settings::set(['home_image' => 'site/hero.jpg']);

        $this->artisan('media:prune --dry-run')->assertSuccessful();
        $this->assertTrue($disk->exists('gallery/removed.jpg'));

        $this->artisan('media:prune')->expectsOutput('2 unused file(s) deleted.')->assertSuccessful();
        $this->assertFalse($disk->exists('gallery/removed.jpg'));
        $this->assertFalse($disk->exists('articles/covers/old.jpg'));
        foreach (['articles/images/sand.jpg', 'articles/covers/cover.jpg', 'site/hero.jpg', 'gallery/fresh.jpg'] as $path) {
            $this->assertTrue($disk->exists($path), $path);
        }
    }

    public function test_country_maps_on_the_journey_page_only_show_that_country(): void
    {
        Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands']]);

        $this->assertNotNull(RouteGeometry::border('nl'));
        $this->get('/journey')->assertOk()->assertSee('data-border', false);
    }

    public function test_a_route_piece_is_built_from_gpx_files_and_drawn_parts(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $country = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland'], 'is_published' => true]);
        $line = fn (array $points) => \App\Support\Polyline::encode($points);

        // Four days of the Vierdaagse as GPX parts (day 2 starts where day 1 ended), plus a drawn part to the start.
        $payload = [
            'meta' => ['countryId' => $country->id, 'type' => 'planned', 'titleNl' => 'Nijmeegse Vierdaagse', 'titleEn' => null, 'descriptionNl' => "Vier dagen rond Nijmegen.\n\nMet 40.000 anderen.", 'descriptionEn' => null],
            'segments' => [
                ['kind' => 'drawn', 'label' => 'Naar de start', 'routing' => 'straight', 'waypoints' => [[51.80, 5.80, 0], [51.84, 5.86, 1]], 'line' => $line([[51.80, 5.80], [51.84, 5.86]])],
                ['kind' => 'gpx', 'label' => 'Dag 1', 'line' => $line([[51.84, 5.86], [51.90, 5.95], [51.84, 5.87]])],
                ['kind' => 'gpx', 'label' => 'Dag 2', 'line' => $line([[51.84, 5.87], [51.78, 5.95]])],
                ['kind' => 'gpx', 'label' => 'Dag 3', 'line' => $line([[51.78, 5.95], [51.75, 5.85]])],
                ['kind' => 'gpx', 'label' => 'Dag 4', 'line' => $line([[51.75, 5.85], [51.84, 5.86]])],
            ],
        ];

        $component = Livewire::test(RoutePlanner::class)->call('save', $payload);

        $route = $country->routes()->sole();
        $component->assertSet('route', $route->id);
        $this->assertSame(5, $route->segments()->count());
        $this->assertSame(['Naar de start', 'Dag 1', 'Dag 2', 'Dag 3', 'Dag 4'], $route->segments->pluck('label')->all());
        $this->assertSame(7, $route->points()->count()); // joins are not doubled
        $this->assertSame('Nijmeegse Vierdaagse', $route->translate('title', 'nl'));

        // Editing again restores the parts; the story shows on the country page.
        $this->get('/admin/route-planner?route='.$route->id)->assertOk()->assertSee('data-route-editor', false);
        $this->assertCount(5, Livewire::withQueryParams(['route' => $route->id])->test(RoutePlanner::class)->instance()->initialData()['segments']);
        $this->get('/nl/landen/nederland')->assertOk()->assertSee('Nijmeegse Vierdaagse')->assertSee('Met 40.000 anderen.');

        // A private note per part (Routes list → Notities), kept when the piece is saved again in the planner.
        $this->get('/admin/country-routes/'.$route->id.'/edit')->assertOk()->assertSee('Dag 2')->assertSee('Notities per deel');
        $edit = Livewire::test(\App\Filament\Resources\CountryRoutes\Pages\EditCountryRoute::class, ['record' => $route->id]);
        $key = collect($edit->get('data.segments'))->search(fn ($part) => $part['label'] === 'Dag 2');
        $edit->set("data.segments.{$key}.notes", 'Slapen bij camping De Wolfsberg')->call('save')->assertHasNoFormErrors();
        $this->assertSame(['Naar de start', 'Dag 1', 'Dag 2', 'Dag 3', 'Dag 4'], $route->segments()->pluck('label')->all());
        $planner = Livewire::withQueryParams(['route' => $route->id])->test(RoutePlanner::class);
        $planner->call('save', ['meta' => $payload['meta'], 'segments' => $planner->instance()->initialData()['segments']]);
        $this->assertSame('Slapen bij camping De Wolfsberg', $route->segments()->where('label', 'Dag 2')->value('notes'));
        $this->get('/nl/landen/nederland')->assertDontSee('De Wolfsberg');
    }

    public function test_countries_and_route_pieces_are_ordered_by_dragging(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $nl = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland'], 'sort_order' => 1]);
        $de = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany', 'nl' => 'Duitsland'], 'sort_order' => 2]);
        $first = $nl->routes()->create(['type' => 'planned', 'name' => 'Lisse → Nijmegen', 'sort_order' => 1]);
        $second = $nl->routes()->create(['type' => 'planned', 'name' => 'Vierdaagse', 'sort_order' => 2]);
        $de->routes()->create(['type' => 'planned', 'name' => 'Kleve → Köln', 'sort_order' => 1]);

        $this->get('/admin/countries')->assertOk()->assertSee('Volgorde slepen');
        Livewire::test(\App\Filament\Resources\Countries\Pages\ListCountries::class)->call('reorderTable', [$de->id, $nl->id]);
        $this->assertSame([$de->id, $nl->id], Country::orderBy('sort_order')->pluck('id')->all());

        // Route pieces: a tab per country, dragging within the country.
        $this->get('/admin/country-routes')->assertOk()->assertSee('Nederland')->assertSee('Duitsland')->assertSee('Volgorde slepen');
        Livewire::test(\App\Filament\Resources\CountryRoutes\Pages\ListCountryRoutes::class, ['activeTab' => 'NL'])
            ->assertCanSeeTableRecords([$first, $second])->assertCanNotSeeTableRecords($de->routes)
            ->call('reorderTable', [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], $nl->routes()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_routes_and_gps_points_get_the_country_they_lie_in(): void
    {
        $nl = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland'], 'is_published' => true, 'sort_order' => 1, 'status' => \App\Enums\CountryStatus::Current]);
        $de = Country::create(['iso_code' => 'DE', 'name' => ['en' => 'Germany', 'nl' => 'Duitsland'], 'is_published' => true, 'sort_order' => 2]);

        // One piece, made for the Netherlands, from Nijmegen across the border to Kleve.
        $route = $nl->routes()->create(['type' => 'planned', 'name' => 'Nijmegen → Kleve']);
        $route->replacePoints([['lat' => 51.84, 'lng' => 5.86], ['lat' => 51.83, 'lng' => 5.95], ['lat' => 51.80, 'lng' => 6.05], ['lat' => 51.79, 'lng' => 6.14]]);

        $this->assertSame([$nl->id, $nl->id, $de->id, $de->id], $route->points()->pluck('country_id')->all());
        $this->assertGreaterThan(0, $route->kmIn($nl->id));
        $this->assertGreaterThan(0, $route->kmIn($de->id));
        $this->assertEqualsWithDelta($route->distance_km, $route->kmIn($nl->id) + $route->kmIn($de->id), 0.01);
        $this->assertSame([$route->id], $de->routesThrough()->pluck('id')->all()); // shows on Germany's page too

        // GPS points: the country from the location; the newest point's country is "walking here now".
        app(\App\Services\TrackingRecorder::class)->store([
            ['lat' => 51.84, 'lng' => 5.86, 'time' => now()->subHours(3)],
            ['lat' => 51.79, 'lng' => 6.14, 'time' => now()->subHour()],
        ], 'test');

        $this->assertSame([$nl->id, $de->id], \App\Models\TrackingPoint::orderBy('recorded_at')->pluck('country_id')->all());
        $this->assertSame(\App\Enums\CountryStatus::Current, $de->fresh()->status);
        $this->assertSame(\App\Enums\CountryStatus::Visited, $nl->fresh()->status);
    }

    public function test_concept_route_pieces_stay_off_the_website(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $nl = Country::create(['iso_code' => 'NL', 'name' => ['en' => 'Netherlands', 'nl' => 'Nederland'], 'is_published' => true]);
        $concept = $nl->routes()->create(['type' => 'planned', 'name' => 'Alternatief via de dijk', 'title' => ['nl' => 'Alternatief via de dijk'], 'description' => ['nl' => 'Misschien.'], 'is_draft' => true]);
        $concept->replacePoints([['lat' => 51.84, 'lng' => 5.86], ['lat' => 51.83, 'lng' => 5.95]]);

        // Website: no line, kilometres, story or "still to be planned" start from the concept.
        auth()->logout();
        $this->get('/nl/landen/nederland')->assertOk()->assertDontSee('Alternatief via de dijk')->assertDontSee('[5.86,51.84]', false);
        $this->get('/nl/reis')->assertOk()->assertDontSee('[5.86,51.84]', false);
        $this->assertSame(0.0, \App\Support\JourneyStats::for(null)['planned_km']);
        $open = collect(\App\Support\RouteGeometry::withOpenPlan(['features' => []])['features'])->firstWhere('properties.type', 'open');
        $this->assertSame([4.557, 52.2575], $open['geometry']['coordinates'][0]); // still from Lisse

        // CMS: listed under "Concepten", selectable in the planner, publishable with one click.
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Livewire::test(\App\Filament\Resources\CountryRoutes\Pages\ListCountryRoutes::class, ['activeTab' => 'concepten'])
            ->assertCanSeeTableRecords([$concept])
            ->callTableAction('draft', $concept);
        $this->assertFalse($concept->fresh()->is_draft);
        $this->assertStringContainsString('Alternatief via de dijk', implode(' ', Livewire::test(RoutePlanner::class)->instance()->routeOptions()));
    }

    public function test_the_planner_refuses_a_route_without_country_or_parts(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        Livewire::test(RoutePlanner::class)->call('save', ['meta' => ['countryId' => null, 'type' => 'planned'], 'segments' => []])->assertSet('route', null);

        $this->assertSame(0, \App\Models\CountryRoute::count());
    }
}
