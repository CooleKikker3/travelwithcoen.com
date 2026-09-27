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
- **Routes/maps**: `CountryRoute` (planned|actual, per country) with `RoutePoint`s imported from GPX (`GpxImporter`). Global map, journey timeline and country pages all read these via `RouteGeometry::featureCollection()`. Leaflet in `resources/js/map.js`.
- **Tracking privacy**: all location-bearing data (tracking points, journey days, events) is filtered in queries via `TrackingPrivacy` / `visibleTo($user)` scopes — guests only see data older than `public_tracking_delay_hours` (Settings, default 336). Never filter in JS. `/api/public/tracking` is always delayed; `/api/private/tracking` needs a trusted login; `POST /api/tracking` ingests with a bearer token (`TRACKING_INGEST_TOKEN`). No Garmin-specific code yet: research first.
- **Statistics** are computed (`JourneyStats`) from journey days/routes/tracking, never stored.
- **Gallery** (`GalleryItem`: photos + uploaded videos). Images placed in article text via the `ImageBlock` custom block (with caption) are synced to the gallery by `ArticleGallerySync`, linked to the article, and only shown once the article is published. Images are re-encoded (`ImageProcessor`, EXIF/GPS stripped); videos via ffmpeg when available (`VideoProcessor`).
- **Articles** have a type (diary|preparation) and free tags (filter `?tag=`); render bodies with `$article->bodyHtml()`.
- **YouTube** videos are never added by hand: `youtube:sync` (hourly, `YouTubeSync`) imports the channel from Settings (RSS, or full history with `YOUTUBE_API_KEY`).
- **Route planner** (Filament page): click waypoints, straight lines or BRouter walking paths; waypoints stored on `CountryRoute`.
- **Roles**: `App\Enums\Role` = admin | trusted_viewer. Visitors without an account are guests.
- Never invent route, visa, border or Garmin facts; mark uncertain things as not final.
- Keep it simple; build in small, working steps. Run `php artisan test` after changes.

## Roadmap
- Done: phases 0–6 — i18n, CMS, website texts, routes/maps, tracking + privacy + family login, journey days/events/statistics, equipment, media, private budget, dashboard, settings, sitemap, security headers.
- Open: Garmin integration (after research), deployment + backups + map tile provider, 2FA, design polish (postponed by Coen).
