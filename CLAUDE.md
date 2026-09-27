# Travel with Coen — project notes

Website + CMS for Coen's walk from the Netherlands to Hanoi. Full requirements: `project_context.md` (read the relevant sections, not the whole file, when needed).

## Stack
- Laravel 13 monolith, PHP 8.5, SQLite locally. No separate API/frontend without Coen's explicit approval.
- Public site: Blade + Tailwind v4 (`resources/css/app.css` holds the green palette tokens: forest/moss/olive/fern/sage/mist, bark/sand accents). No JS framework.
- CMS: Filament 5 at `/admin`, admins only (`User::canAccessPanel`).

## Conventions
- **i18n**: English is default (no prefix), Dutch under `/nl`. Public routes are registered once per locale in `routes/web.php`; link with `lroute('name', $params)`. All UI text lives in `lang/{en,nl}/*.php` — never hardcode strings. URL segments are translated in `lang/{locale}/routes.php` (/nl/dagboek).
- **Editable texts**: every text in the `site`, `articles`, `countries` lang groups can be overridden in the CMS ("Website texts", `App\Support\SiteTexts` + `DatabaseOverridesLoader`). New keys appear there automatically; keep texts as strings, not arrays.
- **Translatable content**: JSON columns keyed by locale via `App\Models\Concerns\HasTranslations` (`$model->translate('title')`, fallback to English; `isTranslated('nl')`). Slugs are generated per locale on save. In Filament use `TranslatableTabs::make(fn ($locale, $isDefault) => [...])` with fields named `title.{$locale}`.
- **Routes/maps**: `CountryRoute` (planned|actual, per country) with `RoutePoint`s imported from GPX (`GpxImporter`). Global map, journey timeline and country pages all read these via `RouteGeometry::featureCollection()`. Leaflet in `resources/js/map.js`. Country maps on the Journey timeline show only that country (`RouteGeometry::border()`, Natural Earth outlines in `resources/data/country-borders/{ISO}.json`, sharp inside the border and blurred around it). All maps use NASA Blue Marble satellite imagery (public domain, no credit on the map, native detail up to zoom 8). Flags: `<x-flag :country>` (flag-icons), emoji flags only in the CMS.
- **Tracking privacy**: all location-bearing data (tracking points, journey days, events) is filtered in queries via `TrackingPrivacy` / `visibleTo($user)` scopes — guests only see data older than `public_tracking_delay_hours` (Settings, default 336). Never filter in JS. `/api/public/tracking` is always delayed; `/api/private/tracking` needs a trusted login; `POST /api/tracking` ingests with a bearer token (`TRACKING_INGEST_TOKEN`). No Garmin-specific code yet: research first.
- **Statistics** are computed (`JourneyStats`) from journey days/routes/tracking, never stored.
- **Gallery** (`GalleryItem`): photos, uploaded videos and YouTube videos in one table (kind image|video|youtube, mixed by date), shown as a Freewall-style brick wall (`x-gallery-wall` + `resources/js/wall.js`: dense CSS grid where wide/tall/big bricks fill gaps, FLIP slide on resize, pop-in, infinite scroll, lightbox with description; videos only play on request). Images placed in article text via the `ImageBlock` custom block (with caption) are synced to the gallery by `ArticleGallerySync`, linked to the article, and only shown once the article is published. Images are re-encoded (`ImageProcessor`, EXIF/GPS stripped); videos via ffmpeg when available (`VideoProcessor`).
- **Media storage**: always go through `App\Support\MediaStorage` (`R2_ENABLED=true` → Cloudflare R2 disk `r2`, else local `public`; check with `php artisan media:check`). Uploads get metadata in R2 via `MediaStorage::describe()` (`php artisan media:describe` backfills). Tests always run with R2 and YouTube disabled (phpunit.xml).. Never use `Storage::disk('public')` or local paths for media; edit files via `MediaStorage::editLocally()`. Image uploads are resized in the browser first (weak connections).
- **Weak connections in the CMS**: `resources/js/admin-drafts.js` keeps form drafts in localStorage (restore banner, cleared after a successful save); `SESSION_LIFETIME` is long (7 days).
- **Sensitive images** (`is_sensitive` on gallery items, `sensitive` in the ImageBlock config): blurred with `x-sensitive-overlay` until revealed (`[data-sensitive]` + `.is-revealed`, handler in app.js); always blurred on the wall.
- **Articles** have a type (diary|preparation) and free tags. There are no separate diary/preparation pages: all stories live on the Journey page (`#stories`, filter `?type=&tag=`, link with `stories_url()`); the timeline starts with a Preparation item. Old /diary and /preparation lists redirect there; render bodies with `$article->bodyHtml()`.
- **YouTube** videos are never added by hand: `youtube:sync` (hourly, `YouTubeSync`) adds the channel from `.env` `YOUTUBE_CHANNEL` to the gallery as kind "youtube" (RSS: only the newest 10, only new ones are added; items of a previous channel are removed).
- **Route planner** (Filament page): click waypoints, straight lines or BRouter walking paths; waypoints stored on `CountryRoute`.
- **Roles**: `App\Enums\Role` = admin | trusted_viewer. Visitors without an account are guests.
- Never invent route, visa, border or Garmin facts; mark uncertain things as not final.
- Keep it simple; build in small, working steps. Run `php artisan test` after changes.

## Roadmap
- Done: phases 0–6 — i18n, CMS, website texts, routes/maps, tracking + privacy + family login, journey days/events/statistics, equipment, media, private budget, dashboard, settings, sitemap, security headers.
- Open: Garmin integration (after research), deployment + backups + map tile provider, 2FA, design polish (postponed by Coen).

## Open notes (for next session)
- Maps: NASA Blue Marble (no credit). Interactive maps switch to Esri imagery + place names beyond zoom 8 and faded road/street names from zoom 14 (`sharpWhenZoomed()` in map.js), credit shown only then — not yet visually checked. Check the Esri terms before going live.
- Overview, Live and the Dutch country map start at Lisse (`start-home` on `x-route-map`) until there is a visible location.
- Country widget still blurs the surroundings (`.map-surroundings` in app.css).
