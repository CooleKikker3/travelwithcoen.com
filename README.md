# Travel with Coen

Website and CMS for a walk from the Netherlands to Hanoi (planned departure: late June 2027).

## Local setup

```sh
composer install
npm install
cp .env.example .env          # then fill in ADMIN_EMAIL and ADMIN_PASSWORD
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed     # creates the admin user and tentative countries
php artisan storage:link
composer run dev               # or: php artisan serve + npm run dev
```

- Site: http://localhost:8000 (English) and http://localhost:8000/nl (Dutch)
- CMS: http://localhost:8000/admin

Tests: `php artisan test`

## Tracking ingest

Set `TRACKING_INGEST_TOKEN` in `.env`, then any device or app can post points:

```sh
curl -X POST https://example.com/api/tracking \
  -H "Authorization: Bearer $TRACKING_INGEST_TOKEN" -H "Content-Type: application/json" \
  -d '{"source":"phone","points":[{"lat":52.26,"lng":4.56,"time":"2027-07-01T10:00:00Z","ele":2}]}'
```

Points get the country marked "walking here now" in the CMS. GPX files can also be imported under *Tracking points → Import GPX*.
Visitors only see tracking older than the public delay (Settings, default 14 days); logged-in family sees it live.
