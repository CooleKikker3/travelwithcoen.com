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
