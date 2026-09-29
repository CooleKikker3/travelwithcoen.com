# Live zetten — checklist

Alles wat goed moet staan als Travel with Coen online gaat. Werk het van boven naar beneden af en vink af.
Tip: zet de website eerst **dicht** (stap 9), dan kun je rustig testen terwijl bezoekers "Er komt iets aan…" zien.

---

## 1. Server

- [ ] PHP **8.5** met de extensies: `gd`, `exif`, `zip`, `mbstring`, `pdo_mysql` (of `pdo_sqlite`), `intl`, `fileinfo`, `curl`, `openssl`.
- [ ] Composer, en Node 22+ (alleen nodig om de CSS/JS te bouwen; kan ook lokaal).
- [ ] **ffmpeg** installeren (aanrader): dan wordt de locatie uit video's gehaald. Staat het niet op het standaardpad, zet dan `FFMPEG_PATH` in `.env`.
- [ ] De webserver wijst naar de map **`public/`** (niet naar de hoofdmap van het project).
- [ ] HTTPS (bij Cloudflare: SSL-modus **Full (strict)**).
- [ ] PHP-uploadlimiet ruim genoeg voor video's: `upload_max_filesize` en `post_max_size` op bijv. `512M` (php.ini).

## 2. Code en installatie

```bash
git clone https://github.com/CooleKikker3/travelwithcoen.com.git
cd travelwithcoen.com
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # of lokaal bouwen en public/build meesturen
cp .env.example .env             # daarna invullen, zie stap 3
php artisan key:generate         # alleen de eerste keer!
php artisan migrate --force
php artisan optimize             # config, routes, views en events cachen
```

Bij elke volgende update:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

> Na een wijziging in `.env` altijd opnieuw `php artisan optimize` draaien: de instellingen staan gecachet.

## 3. `.env` op de server

| Instelling | Waarde |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (**nooit** `true` online: dan zie je foutmeldingen met geheimen) |
| `APP_URL` | `https://travelwithcoen.com` |
| `DB_CONNECTION` + `DB_*` | database van de host (MySQL/MariaDB), of `sqlite` |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | eerste beheerder (daarna kun je het wachtwoord in het beheer wijzigen) |
| `TRACKING_INGEST_TOKEN` | lange willekeurige code voor de locatie-app (`php -r "echo bin2hex(random_bytes(32));"`) |
| `YOUTUBE_CHANNEL` | je kanaal, bijv. `https://www.youtube.com/@...` |
| `SESSION_LIFETIME` | `10080` (7 dagen ingelogd blijven, handig bij slecht bereik) |
| `PREVIEW_IPS` | `127.0.0.1,::1,<je eigen IP>` (ziet de site ook als die dicht staat) |
| `TRUSTED_PROXIES` | `*` als de site achter Cloudflare staat (zie stap 5) |
| `R2_ENABLED` + `R2_*` | zie stap 4; `R2_URL` = je eigen media-domein |
| `MAIL_*` | zie stap 8 |

## 4. Foto's en video's (Cloudflare R2)

- [ ] **Eigen domein aan de bucket koppelen** (belangrijk voor snelheid): Cloudflare → R2 → bucket `travelwithcoen` → Settings → **Custom Domains** → bijv. `media.travelwithcoen.com`.
  Het `r2.dev`-adres is alleen voor testen: geen caching op het netwerk van Cloudflare en een snelheidslimiet.
- [ ] `R2_URL=https://media.travelwithcoen.com` in `.env`, daarna `php artisan optimize`.
- [ ] Oude `r2.dev`-adressen: die staan nergens vast in de database (adressen worden steeds uit `R2_URL` gemaakt), dus niets om om te zetten.
- [ ] **CORS** voor het beheer (anders blijven afbeeldingen in het beheer op "Loading" staan): `php artisan media:cors https://travelwithcoen.com` — of plak de getoonde JSON in Cloudflare → R2 → bucket → Settings → CORS policy.
- [ ] Cachetijd staat al goed: elke upload krijgt een jaar (`Cache-Control: immutable`). Oudere bestanden: `php artisan media:cache-headers`.
- [ ] Controle: `php artisan media:check`.

## 5. Cloudflare voor de website

- [ ] Domein via Cloudflare (proxy aan, oranje wolkje).
- [ ] `TRUSTED_PROXIES=*` in `.env`. Zonder deze instelling ziet de site het IP van Cloudflare in plaats van de bezoeker, en werken `PREVIEW_IPS` en de herkenning van Nederlandse bezoekers niet goed.
  Zet dit alleen als de server **uitsluitend** via Cloudflare bereikbaar is.
- [ ] Cloudflare stuurt het land van de bezoeker mee (`CF-IPCountry`): Nederlandse bezoekers komen dan automatisch op `/nl`. Dit staat standaard aan.

## 6. Cachetijden van de eigen bestanden

CSS, JavaScript en lettertypes in `public/build` krijgen per versie een unieke naam, dus browsers mogen ze een jaar bewaren.

- **Apache**: staat al in `public/.htaccess` (vereist `mod_headers`; bij de meeste hosts aan).
- **nginx**: voeg toe in het `server`-blok:

```nginx
location /build/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
location /brand/ {
    add_header Cache-Control "public, max-age=604800";
    try_files $uri =404;
}
```

- [ ] Controle: `curl -I https://travelwithcoen.com/build/assets/<een bestand uit public/build/assets>` moet `Cache-Control: public, max-age=31536000, immutable` tonen.

## 7. Geplande taken (scheduler)

Eén cronjob, elke minuut:

```cron
* * * * * cd /pad/naar/travelwithcoen.com && php artisan schedule:run >> /dev/null 2>&1
```

Die draait automatisch:
- `youtube:sync` — elk uur nieuwe YouTube-video's in de galerij;
- `media:prune` — maandag 04:00, ongebruikte bestanden opruimen;
- `geo:countries` — maandag 04:30, route- en GPS-punten aan het juiste land koppelen;
- `garmin:sync` — elke 10 minuten nieuwe posities van je Garmin inReach (stap 12).

- [ ] Controle: `php artisan schedule:list`.

## 8. E-mail (wachtwoord vergeten)

Zonder e-mail werkt "wachtwoord vergeten" niet — en onderweg kan niemand je dan helpen.

- [ ] `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` invullen (bijv. via de host, Postmark of Brevo).
- [ ] Testen: uitloggen → "Wachtwoord vergeten" → mail komt aan.

## 9. Eerste start

- [ ] Inloggen op `/admin` met `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- [ ] **Instellingen → Website is open: uit** zolang je nog test. Beheerders en `PREVIEW_IPS` zien de hele site.
- [ ] Hoofdfoto, fase van het project, vertraging van je locatie controleren.
- [ ] Een foto uploaden → verschijnt de preview in het beheer (CORS) en op de site (R2)?
- [ ] `https://travelwithcoen.com/sitemap.xml` en `/robots.txt` openen.
- [ ] Klaar? **Website is open: aan**. Daarna de sitemap indienen in Google Search Console.

## 10. Back-ups

- [ ] **Database**: dagelijkse back-up via de host, of een cronjob met `mysqldump` (SQLite: kopie van `database/database.sqlite`). Bewaar ook een kopie buiten de server.
- [ ] **`.env`**: veilig bewaren (wachtwoordmanager). Zonder `APP_KEY` zijn sessies en versleutelde gegevens niet te herstellen.
- [ ] **Foto's/video's** staan in R2; Cloudflare bewaart die redundant. Een extra kopie is optioneel.
- [ ] Een keer een terugzet-test doen vóór vertrek.

## 11. Tijdens de reis

- [ ] **Monitoring**: een gratis dienst (bijv. UptimeRobot) die `https://travelwithcoen.com/up` elke 5 minuten controleert en je mailt als de site plat ligt.
- [ ] Iemand thuis met toegang tot de host en deze checklist, voor als het misgaat.
- [ ] Domeinnaam en hosting **automatisch verlengen** (anders loopt het af terwijl je in Azië bent).
- [ ] Beveiligingsupdates: af en toe `composer update` + tests (`php artisan test`) + deploy.

## 12. Garmin inReach koppelen

Uitgebreide uitleg met foutoplossing: [`docs/garmin-inreach.md`](docs/garmin-inreach.md).

De site haalt elke 10 minuten nieuwe posities op uit je **MapShare**-feed (via de scheduler, stap 7).

- [ ] explore.garmin.com → **Social** (of **MapShare**) → MapShare **aanzetten**. Kies een MapShare-naam; je pagina wordt `share.garmin.com/<naam>`.
- [ ] Optioneel een **MapShare-wachtwoord** instellen (aanrader: dan kan niemand je live positie via Garmin zien; de website doet de vertraging).
- [ ] In `.env`: `GARMIN_MAPSHARE_URL=https://share.garmin.com/Feed/Share/<naam>` en eventueel `GARMIN_MAPSHARE_PASSWORD=<wachtwoord>`, daarna `php artisan optimize`.
- [ ] Op het apparaat: **Tracking aan** met een interval (10 min is een goede balans tussen detail, batterij en abonnement).
- [ ] Testen: tracking even aan, een kwartier wachten, dan `php artisan garmin:sync` → posities verschijnen onder **Locatiepunten** in het beheer.

## 13. Nog te regelen vóór het echt live gaat

- [ ] **Voorwaarden kaartbeelden**: de scherpe satellietbeelden bij inzoomen komen van Esri (World Imagery). Controleer of hun voorwaarden dit gebruik toestaan. NASA (uitgezoomd) en OpenFreeMap (plaatsnamen) zijn vrij te gebruiken.
- [ ] Garmin koppelen (stap 12). Andere locatie-apps kunnen posities sturen naar `POST /api/tracking` met `TRACKING_INGEST_TOKEN`.
- [ ] Vóórdat een locatie-apparaat elke minuut een punt stuurt: de gelopen route cachen (zie "Open notes" in `CLAUDE.md`).
