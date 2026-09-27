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
- **Roles**: `App\Enums\Role` = admin | trusted_viewer. Visitors without an account are guests.
- Never invent route, visa, border or Garmin facts; mark uncertain things as not final.
- Keep it simple; build in small, working steps. Run `php artisan test` after changes.

## Roadmap
- Done: phase 0 (skeleton, i18n, layout), phase 1 (CMS: countries, articles, users, website texts), phase 2 (route data per country, GPX import, maps, journey timeline).
- Next: tracking with server-side 14-day public delay (`public_tracking_delay_hours`, default 336) and trusted-viewer live access. Design polish is postponed by Coen.
