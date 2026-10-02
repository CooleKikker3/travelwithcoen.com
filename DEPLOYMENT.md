# Travel with Coen online zetten — stap voor stap

Deze handleiding zet de site op je VPS (`217.154.118.71`, zie je VPS-handleiding), naast de andere projecten.
Werk hem van boven naar beneden af. Elk commando kun je kopiëren en plakken.

Afspraken in dit bestand:

| | |
| --- | --- |
| Projectnaam (map, pool, socket, nginx, cron) | `travelwithcoen` |
| Map op de server | `/var/www/travelwithcoen` |
| Domein | `travelwithcoen.com` (+ `www.travelwithcoen.com`, dat doorstuurt) |
| Tweede domein | `travelwithcoen.nl` (+ `www`): stuurt door naar de Nederlandse site, `travelwithcoen.com/nl` |
| Domeinen geregistreerd bij | TransIP; DNS loopt via Cloudflare |
| Foto's en video's | Cloudflare R2, bucket `travelwithcoen-live`, via `media.travelwithcoen.com` |
| Database | SQLite (`database/database.sqlite`), leeg begonnen |

Het plan heeft twee delen:

- **Deel 1 (stap 1–3)** doe je in je browser, bij TransIP en Cloudflare. Begin hiermee: het omzetten van de domeinen kan een paar uur duren.
- **Deel 2 (stap 4–18)** doe je op de server, ingelogd als root (`ssh root@217.154.118.71`).

**Artisan draai je op de server altijd met `sudo -u www-data`** (zo blijven logbestanden en de database schrijfbaar voor de website). Uitzonderingen staan er steeds bij.

> Plakken in de terminal zet er soms rommel voor, zoals `^[[200~`. Krijg je `command not found` op een commando dat gewoon bestaat: typ of plak het opnieuw.

> De knoppen bij TransIP en Cloudflare heten soms net iets anders dan hier staat (hun schermen veranderen af en toe). Zoek dan naar het woord dat er het meest op lijkt.

---

# Deel 1 — In je browser

## Stap 1 — Domeinen van TransIP naar Cloudflare

De domeinen blijven geregistreerd bij TransIP (daar betaal je ze ook), maar Cloudflare gaat de DNS doen. Dat is nodig voor R2 (stap 3) en maakt de site sneller en veiliger.

Doe deze stap **eerst voor `travelwithcoen.com`, daarna precies zo voor `travelwithcoen.nl`**.

1. **Cloudflare**: log in op `dash.cloudflare.com` → **Add a domain** (of **Add site**) → vul `travelwithcoen.com` in → kies **Quick scan for DNS records** → **Continue** → kies het **Free**-plan.
2. Cloudflare laat de DNS-records zien die hij bij TransIP vond.
   - Gebruik je e-mail op dit domein (bijv. via TransIP)? Controleer dan dat de **MX**-records (en TXT-records met `spf`) in de lijst staan. Staan ze er niet: neem ze over uit TransIP (TransIP → domein → **DNS**).
   - Verwijder A- en AAAA-records voor `@` en `www` die naar TransIP wijzen; de goede zet je in stap 2.
3. Cloudflare toont nu **twee nameservers**, zoiets als `anna.ns.cloudflare.com` en `bob.ns.cloudflare.com`. Laat dit tabblad open.
4. **TransIP**: log in op `transip.nl` → **Domeinen** → klik op `travelwithcoen.com`.
   1. **DNSSEC uitzetten** (staat bij TransIP meestal aan). Doe je dit niet, dan werkt het domein na het omzetten niet meer. Opslaan.
   2. Bij **Nameservers**: zet **"TransIP-instellingen gebruiken"** (of "Standaard nameservers") **uit** en vul de twee nameservers van Cloudflare in. De overige velden leeg laten. Opslaan.
5. **Cloudflare**: klik op **Check nameservers** (of **Done, check nameservers**). Na een tijdje (meestal binnen een uur, soms langer) krijg je een mail en staat het domein op **Active**.
6. Pas als het domein **Active** is: DNSSEC via Cloudflare weer aanzetten (aanrader, niet verplicht):
   Cloudflare → domein → **DNS** → **Settings** → **DNSSEC** → **Enable**. Cloudflare toont een **DS-record**; zet de gegevens daarvan bij TransIP → domein → **DNSSEC** (TransIP vraagt om de *key tag*, het *algoritme* en de *public key* / *digest*; die staan allemaal in het scherm van Cloudflare).

Herhaal 1 t/m 6 voor `travelwithcoen.nl`.

## Stap 2 — DNS-records naar de server

Cloudflare → `travelwithcoen.com` → **DNS** → **Records** → **Add record**. Voeg deze vier toe, **met het wolkje op grijs (DNS only)**. Certbot heeft in stap 10 een directe verbinding met de server nodig; in stap 11 zet je het wolkje op oranje.

| Type | Name | IPv4/IPv6 address | Proxy status |
| --- | --- | --- | --- |
| A | `@` | `217.154.118.71` | DNS only (grijs) |
| AAAA | `@` | `2a02:2479:13:7700::1` | DNS only (grijs) |
| A | `www` | `217.154.118.71` | DNS only (grijs) |
| AAAA | `www` | `2a02:2479:13:7700::1` | DNS only (grijs) |

Doe daarna **precies hetzelfde** bij `travelwithcoen.nl`.

Controle (op je eigen computer, kan even duren): `nslookup travelwithcoen.com` en `nslookup travelwithcoen.nl` moeten allebei `217.154.118.71` geven.

## Stap 3 — Cloudflare R2 voor foto's en video's

De site zet alle foto's en video's in R2, de opslag van Cloudflare. Online krijgt de site een **eigen, nieuwe bucket**. Je lokale site houdt de bucket `travelwithcoen` die je al had.

> **Waarom twee buckets?** Elke week ruimt de site bestanden op die hij zelf niet (meer) gebruikt. Delen je lokale site en de online site één bucket, dan gooit de één de foto's van de ander weg. Zet dus **nooit** de gegevens van `travelwithcoen-live` in je lokale `.env`.

**3a. Bucket aanmaken**

1. Cloudflare → linkermenu **R2 Object Storage** (onder "Storage & Databases").
2. **Create bucket** → naam `travelwithcoen-live` → Location: **Automatic** (of een hint "Western Europe") → **Create bucket**.
3. Laat **Public Development URL** (`r2.dev`) **uit**: dat adres is traag en heeft een limiet. De site gebruikt een eigen domein (3b).

**3b. Eigen domein voor de bestanden** (kan pas als `travelwithcoen.com` op **Active** staat, stap 1)

1. R2 → bucket `travelwithcoen-live` → **Settings** → **Custom Domains** → **Add** (of **Connect Domain**).
2. Vul `media.travelwithcoen.com` in → **Continue** → **Connect domain**. Cloudflare maakt het DNS-record zelf aan.
3. Wacht tot de status **Active** is (een paar minuten).

**3c. CORS** (anders blijven afbeeldingen in het beheer op "Loading" staan)

1. R2 → bucket `travelwithcoen-live` → **Settings** → **CORS Policy** → **Add CORS policy** (of **Edit**).
2. Vervang alles door dit en klik **Save**:

   ```json
   [
     {
       "AllowedOrigins": ["https://travelwithcoen.com"],
       "AllowedMethods": ["GET", "HEAD"],
       "AllowedHeaders": ["*"],
       "MaxAgeSeconds": 3600
     }
   ]
   ```

**3d. Toegangssleutel voor de server**

1. R2 → overzicht → **Manage API tokens** (rechts, of via **API** → **Manage API Tokens**) → **Create API token** (kies een *Account API token* als je de keuze krijgt).
2. Instellingen:
   - Token name: `travelwithcoen-live`
   - Permissions: **Object Read & Write**
   - Specify bucket(s): **Apply to specific buckets only** → `travelwithcoen-live`
   - TTL: **Forever**
3. **Create API Token**. Je ziet nu drie dingen. **Kopieer ze meteen naar je wachtwoordmanager: het geheim zie je maar één keer.**

   | Cloudflare noemt het | Komt in `.env` (stap 6) als |
   | --- | --- |
   | Access Key ID | `R2_ACCESS_KEY_ID` |
   | Secret Access Key | `R2_SECRET_ACCESS_KEY` |
   | Endpoint voor S3 clients (`https://<lange code>.r2.cloudflarestorage.com`) | `R2_ENDPOINT` |

---

# Deel 2 — Op de server

## Stap 4 — Eenmalig: wat deze site extra nodig heeft

```bash
# PHP-extensies (exif en fileinfo zitten in php8.5-common)
apt update
apt install -y php8.5-gd php8.5-intl php8.5-zip php8.5-mbstring php8.5-sqlite3 php8.5-curl php8.5-xml php8.5-bcmath

# ffmpeg: haalt de GPS-locatie uit video's
apt install -y ffmpeg

# Controle
php -m | grep -Ei 'gd|intl|zip|mbstring|sqlite|curl|exif|bcmath'
node -v        # v22.x: staat er al op, nodig om de CSS en JavaScript te bouwen
ffmpeg -version | head -1
```

Kijk ook even in `https://monitor.coenvink.com` (of met `free -h`) of er genoeg geheugen vrij is voor een extra project.

**Swap (aanrader bij 2 GB geheugen of minder):** het bouwen van de CSS/JS (`npm run build`) en video-'s verwerken met ffmpeg kunnen even veel geheugen vragen. Zonder swap breekt Linux dan een proces af. Staat er bij `free -h` op de regel `Swap` `0B`, maak dan eenmalig 2 GB swap aan (geldt voor de hele server):

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
sysctl -p /etc/sysctl.d/99-swappiness.conf
free -h      # Swap: 2.0Gi
```

## Stap 5 — Code op de server

```bash
git clone https://github.com/CooleKikker3/travelwithcoen.com /var/www/travelwithcoen
cd /var/www/travelwithcoen
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

> Is de repository privé? Maak dan een deploy key, zoals in `UpManagerAPI/DEPLOY.md` stap 5.

## Stap 6 — `.env` invullen

```bash
cp .env.example .env
nano .env
```

Zet of wijzig deze regels (de rest mag blijven staan):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://travelwithcoen.com
LOG_LEVEL=warning

DB_CONNECTION=sqlite
QUEUE_CONNECTION=sync

ADMIN_NAME="Coen"
ADMIN_EMAIL=jouw@mailadres.nl
ADMIN_PASSWORD=een-sterk-wachtwoord

TRACKING_INGEST_TOKEN=
YOUTUBE_CHANNEL=https://www.youtube.com/@jouwkanaal
SESSION_LIFETIME=10080

# Zie de site ook als hij dicht staat (je eigen IP thuis; komma's ertussen)
PREVIEW_IPS=127.0.0.1,::1,<jouw IP>

# De site staat achter Cloudflare (oranje wolkje, stap 11)
TRUSTED_PROXIES=*

# Foto's en video's: de gegevens uit stap 3d
R2_ENABLED=true
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=travelwithcoen-live
R2_ENDPOINT=
R2_URL=https://media.travelwithcoen.com

# Garmin inReach (stap 18; mag later)
GARMIN_MAPSHARE_URL=
GARMIN_MAPSHARE_PASSWORD=

# Automatische vertalingen Nederlands → Engels (stap 19; mag later)
GOOGLE_TRANSLATE_KEY=

# Google Analytics, metings-ID G-... (stap 20; mag later)
GOOGLE_ANALYTICS_ID=

# E-mail voor "wachtwoord vergeten" (stap 16; mag later)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@travelwithcoen.com
MAIL_FROM_NAME="Travel with Coen"
```

Een lange code voor `TRACKING_INGEST_TOKEN` maak je zo:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Je eigen IP (voor `PREVIEW_IPS`) zie je op je eigen computer op `https://ifconfig.me`.

Dan de sleutel en de rechten (als root, dit is de uitzondering):

```bash
php artisan key:generate

chmod 755 /var/www/travelwithcoen
chown root:www-data .env
chmod 640 .env
```

> **Bewaar de hele `.env` in je wachtwoordmanager**, vooral `APP_KEY`: zonder die sleutel kan niemand meer inloggen.

## Stap 7 — De database (leeg)

```bash
cd /var/www/travelwithcoen
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database
sudo -u www-data php artisan migrate --force --seed
sudo -u www-data php artisan optimize
```

`--seed` maakt je beheerdersaccount (uit `ADMIN_EMAIL` / `ADMIN_PASSWORD`) en zet de landen uit het globale plan klaar (Nederland, Duitsland, Turkije, China, Vietnam; nog niet gepubliceerd). Routes, teksten, foto's en instellingen voer je daarna in via het beheer.

> Eerst `--seed`, dan `optimize`: na `optimize` leest de seeder de `.env` niet meer.

## Stap 8 — PHP-FPM-pool

```bash
nano /etc/php/8.5/fpm/pool.d/travelwithcoen.conf
```

```ini
[travelwithcoen]
user = www-data
group = www-data

listen = /run/php/php8.5-fpm-travelwithcoen.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 30s
pm.max_requests = 500

php_admin_value[error_log] = /var/log/php-fpm-travelwithcoen.log
php_admin_flag[log_errors] = on
php_admin_value[expose_php] = off

; Video's uploaden en verwerken (ffmpeg) kost ruimte en tijd
php_admin_value[upload_max_filesize] = 512M
php_admin_value[post_max_size] = 512M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
```

```bash
systemctl restart php8.5-fpm
```

## Stap 9 — nginx

```bash
nano /etc/nginx/sites-available/travelwithcoen
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name travelwithcoen.com www.travelwithcoen.com;

    # www → zonder www
    if ($host = www.travelwithcoen.com) {
        return 301 https://travelwithcoen.com$request_uri;
    }

    root /var/www/travelwithcoen/public;
    index index.php;

    charset utf-8;
    client_max_body_size 512M;

    access_log /var/log/nginx/travelwithcoen.access.log;
    error_log  /var/log/nginx/travelwithcoen.error.log;

    # CSS/JS/lettertypes hebben per versie een unieke naam: browsers mogen ze een jaar bewaren
    location /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }
    location /brand/ {
        add_header Cache-Control "public, max-age=604800";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm-travelwithcoen.sock;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# travelwithcoen.nl → de Nederlandse site op travelwithcoen.com (één adres voor Google, geen dubbele inhoud)
server {
    listen 80;
    listen [::]:80;
    server_name travelwithcoen.nl www.travelwithcoen.nl;

    access_log /var/log/nginx/travelwithcoen.access.log;
    error_log  /var/log/nginx/travelwithcoen.error.log;

    location = / {
        return 301 https://travelwithcoen.com/nl;
    }
    # Adressen die al "/nl" hebben, en het beheer: alleen het domein wisselen
    location ~ ^/(nl|admin|login|livewire)(/|$) {
        return 301 https://travelwithcoen.com$request_uri;
    }
    location / {
        return 301 https://travelwithcoen.com/nl$request_uri;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/travelwithcoen /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
curl -i http://travelwithcoen.com/up
curl -I http://travelwithcoen.nl/     # 301 naar https://travelwithcoen.com/nl
```

Bij `travelwithcoen.com/up` moet je een `200` zien. Zo niet: kijk in de tabel "Wat je ziet" in je VPS-handleiding, en in
`tail -50 /var/log/nginx/travelwithcoen.error.log` en `tail -50 storage/logs/laravel.log`.

## Stap 10 — HTTPS

```bash
certbot --nginx -d travelwithcoen.com -d www.travelwithcoen.com
certbot --nginx -d travelwithcoen.nl -d www.travelwithcoen.nl
curl -I https://travelwithcoen.com/up
curl -I https://travelwithcoen.nl/    # 301 naar https://travelwithcoen.com/nl
```

> Twee aparte certificaten: certbot zet elk in het juiste `server`-blok. Vraagt certbot welk blok hij moet aanpassen, kies dan het blok met dezelfde `server_name`.

> Faalt certbot op IPv6: haal de AAAA-records in Cloudflare even weg, probeer opnieuw en zet ze terug.

## Stap 11 — Cloudflare-proxy aan

1. Cloudflare → `travelwithcoen.com` → **DNS** → **Records** → bij de vier records uit stap 2 op **Edit** → **Proxy status** op **Proxied** (oranje wolkje) → **Save**.
2. Cloudflare → `travelwithcoen.com` → **SSL/TLS** → **Overview** → modus **Full (strict)**.
3. Doe 1 en 2 ook bij `travelwithcoen.nl`.

`TRUSTED_PROXIES=*` staat al in `.env`: daardoor ziet de site het IP van de bezoeker in plaats van dat van Cloudflare (nodig voor `PREVIEW_IPS` en het doorsturen van Nederlandse bezoekers naar `/nl`).

## Stap 12 — Controle foto's en video's

```bash
cd /var/www/travelwithcoen
sudo -u www-data php artisan media:check
```

Dit schrijft een testbestand naar R2, leest het terug via `media.travelwithcoen.com` en ruimt het op. Gaat er iets mis, dan zegt het commando wat: meestal een typfout in de `R2_*`-regels (daarna `sudo -u www-data php artisan optimize`) of het eigen domein uit stap 3b is nog niet **Active**.

## Stap 13 — Geplande taken

```bash
nano /etc/cron.d/travelwithcoen
```

```cron
* * * * * www-data cd /var/www/travelwithcoen && php artisan schedule:run >> /dev/null 2>&1

```

(Laat de lege regel onderaan staan, anders slaat cron het bestand over.)

Controle: `sudo -u www-data php artisan schedule:list`. Dit draait er automatisch:

- `garmin:sync` — elke 10 minuten nieuwe posities van je Garmin inReach;
- `youtube:sync` — elk uur nieuwe YouTube-video's in de galerij;
- `media:prune` — maandag 04:00, ongebruikte bestanden opruimen;
- `geo:countries` — maandag 04:30, punten aan het juiste land koppelen.

## Stap 14 — Back-up van de database

```bash
nano /usr/local/bin/backup-travelwithcoen
```

```bash
#!/bin/bash
set -e
DOEL=/var/backups/travelwithcoen
mkdir -p "$DOEL"
sqlite3 /var/www/travelwithcoen/database/database.sqlite ".backup '$DOEL/database-$(date +%F).sqlite'"
find "$DOEL" -name 'database-*.sqlite' -mtime +30 -delete
```

```bash
chmod +x /usr/local/bin/backup-travelwithcoen
echo '15 4 * * * root /usr/local/bin/backup-travelwithcoen' > /etc/cron.d/backup-travelwithcoen
/usr/local/bin/backup-travelwithcoen && ls -l /var/backups/travelwithcoen
```

Af en toe een kopie naar je eigen computer halen (op je eigen computer):

```bash
scp root@217.154.118.71:/var/backups/travelwithcoen/*.sqlite .
```

Foto's en video's staan in R2 (Cloudflare bewaart die redundant); die zitten niet in deze back-up.

## Stap 15 — In de monitor zetten

```bash
nano /var/www/monitor/projects.json
```

Zet dit blok achteraan in de lijst, met een komma na het blok ervoor:

```json
  {
    "name": "travelwithcoen",
    "label": "Travel with Coen",
    "type": "laravel",
    "domain": "travelwithcoen.com",
    "url": "https://travelwithcoen.com/up",
    "dir": "/var/www/travelwithcoen",
    "pool": "travelwithcoen"
  }
```

```bash
systemctl restart monitor
journalctl -u monitor -n 20 --no-pager
```

Staat er `Kon .../projects.json niet lezen`: typfout in de JSON, meestal een komma.
Verander `name` hierna niet meer: de historie wordt onder die naam bewaard.

## Stap 16 — E-mail (wachtwoord vergeten)

Zonder e-mail werkt "wachtwoord vergeten" niet — en onderweg kan niemand je dan helpen.

1. Vul de `MAIL_*`-regels in `.env` in (bijv. via Brevo of Postmark; die geven je de host, poort, gebruikersnaam en wachtwoord).
2. `sudo -u www-data php artisan optimize`
3. Testen: uitloggen → "Wachtwoord vergeten" → komt de mail aan?

## Stap 17 — Eerste start (met de site nog dicht)

- [ ] Inloggen op `https://travelwithcoen.com/admin` met `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- [ ] **Instellingen → Website is open: uit.** Bezoekers zien dan "Er komt iets aan…"; jij (ingelogd) en `PREVIEW_IPS` zien alles.
- [ ] De site vullen: landen publiceren, routes in de routeplanner, hoofdfoto, teksten, uitrusting.
- [ ] Een foto uploaden → verschijnt de preview in het beheer (CORS, stap 3c) en op de site?
- [ ] `https://travelwithcoen.com/sitemap.xml` en `/robots.txt` openen.
- [ ] `https://travelwithcoen.nl` en `https://www.travelwithcoen.nl/verhalen` openen: je komt uit op `travelwithcoen.com/nl` en `travelwithcoen.com/nl/verhalen`.
- [ ] Cachetijd: een bestand uit `public/build/assets` geeft bij `curl -I https://travelwithcoen.com/build/assets/<bestand>` de regel `Cache-Control: public, max-age=31536000, immutable`.
- [ ] Vul in je VPS-handleiding de tabel "Wat al draait" aan: `| Travel with Coen | Laravel | travelwithcoen.com | /var/www/travelwithcoen | travelwithcoen | pool travelwithcoen |`.
- [ ] Klaar om open te gaan? **Website is open: aan**, en de sitemap indienen in Google Search Console.

## Stap 18 — Garmin inReach koppelen

Uitgebreide uitleg met foutoplossing: [`docs/garmin-inreach.md`](docs/garmin-inreach.md).

- [ ] explore.garmin.com → **Social** (of **MapShare**) → MapShare **aanzetten**. Je pagina wordt `share.garmin.com/<naam>`.
- [ ] Optioneel een **MapShare-wachtwoord** (aanrader: dan ziet niemand je live positie via Garmin; de website doet de vertraging).
- [ ] In `.env`: `GARMIN_MAPSHARE_URL=https://share.garmin.com/Feed/Share/<naam>` en eventueel `GARMIN_MAPSHARE_PASSWORD=...`, daarna `sudo -u www-data php artisan optimize`.
- [ ] Op het apparaat: **Tracking aan**, interval 10 minuten.
- [ ] Testen: een kwartier wachten, dan `sudo -u www-data php artisan garmin:sync` → posities onder **Locatiepunten** in het beheer.

## Stap 19 — Automatische vertalingen (Google)

Alles wat je in het Nederlands schrijft, vertaalt de server naar het Engels. Het gaat pas online als je het hebt
nagekeken op de pagina **Vertalingen** in het beheer (daar staat ook een teller in het menu).

1. Google Cloud Console → je project → **APIs & Services** → **Library** → **Cloud Translation API** → **Enable**.
   Het project moet een betaalrekening hebben (**Billing**).
2. **APIs & Services** → **Credentials** → **Create credentials** → **API key**. Beperk de sleutel:
   - **API restrictions**: alleen **Cloud Translation API**;
   - **Application restrictions** → **IP addresses**: `217.154.118.71` en `2a02:2479:13:7700::1`.
3. In `.env`: `GOOGLE_TRANSLATE_KEY=<de sleutel>`, daarna `sudo -u www-data php artisan optimize`.
4. Het vertalen loopt elke minuut via de geplande taken (stap 13): `translations:run`.
5. Bestaande teksten die nog geen Engels hebben, eenmalig in de wachtrij zetten:

   ```bash
   sudo -u www-data php artisan translations:queue-missing
   ```

6. Controle: schrijf een zin in het Nederlands (bijv. een bijschrift), wacht een minuut en kijk op **Vertalingen**.
   Staat er "Vertalen lukte niet", dan staat de reden erbij (meestal de sleutel of de beperkingen).

Kosten: Google rekent per teken. Er staat een budgetwaarschuwing op € 5. Wat je verbruikt zie je via de knop
**Kosten bij Google** op de pagina **Vertalingen**, of direct:
<https://console.cloud.google.com/billing/0114D4-D24B5E-64CFB7?project=travelwithcoen>

## Stap 20 — Google Analytics (mag later)

Bezoekers krijgen eerst een kleine vraag of ze geteld mogen worden; Google Analytics laadt pas na "Prima".
Als je zelf ingelogd bent als beheerder, telt Analytics je niet mee.

1. <https://analytics.google.com> → **Beheer** → **Property maken** (naam: Travel with Coen, tijdzone Nederland).
2. **Gegevensstreams** → **Web** → URL `https://travelwithcoen.com` → **Stream maken**.
3. Kopieer de **Metings-ID** (begint met `G-`).
4. In `.env`: `GOOGLE_ANALYTICS_ID=G-...`, daarna `sudo -u www-data php artisan optimize`.
5. Controle: open de site in een privévenster, klik op **Prima** en kijk in Analytics bij **Rapporten** → **Realtime**.

Tip: zet in Analytics bij **Beheer** → **Gegevensverzameling en -aanpassing** → **Gegevensbewaring** op 2 maanden
(het kortst mogelijke) en laat "Google-signalen" uit.

Social-medialinks (Facebook, Instagram, YouTube) vul je zelf in het beheer in: **Instellingen** → **Social media**.

---

## Een nieuwe versie uitrollen

Eenmalig het script aanmaken:

```bash
nano /var/www/travelwithcoen/deploy.sh
```

```bash
#!/bin/bash
set -e
cd /var/www/travelwithcoen

sudo -u www-data php artisan down || true
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
chown -R www-data:www-data storage bootstrap/cache database
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo -u www-data php artisan up
```

```bash
chmod +x /var/www/travelwithcoen/deploy.sh
```

Daarna is elke update (nadat de code op GitHub staat):

```bash
/var/www/travelwithcoen/deploy.sh
```

> Een wijziging in `.env` doet pas iets na `sudo -u www-data php artisan optimize`.

## Als er iets misgaat

| Wat je ziet | Oplossing |
| --- | --- |
| Domein doet niets meer na het omzetten | DNSSEC stond nog aan bij TransIP (stap 1.4): uitzetten, en na **Active** via Cloudflare opnieuw instellen |
| `attempt to write a readonly database` / `Permission denied` | `cd /var/www/travelwithcoen && chown -R www-data:www-data storage bootstrap/cache database` |
| Wijziging in `.env` werkt niet | `sudo -u www-data php artisan optimize` |
| Upload van een video mislukt | limieten in stap 8 (pool) en `client_max_body_size` in stap 9; Cloudflare laat op het gratis plan maximaal **100 MB** per upload door |
| Afbeeldingen in het beheer blijven "Loading" | CORS, stap 3c |
| Foto's laden niet op de site | `sudo -u www-data php artisan media:check` (stap 12) |
| Iedereen krijgt "Er komt iets aan", ook jij | inloggen op `/admin`, of je IP in `PREVIEW_IPS` (+ `TRUSTED_PROXIES=*` achter Cloudflare) |

```bash
tail -f /var/www/travelwithcoen/storage/logs/laravel.log
tail -f /var/log/nginx/travelwithcoen.error.log
```

## Tijdens de reis

- [ ] **Monitoring**: `monitor.coenvink.com` volgt de site (stap 15), maar draait op dezelfde server: ligt de hele VPS plat, dan ziet hij dat niet. Zet daarom ook een gratis dienst van buitenaf (bijv. UptimeRobot) op `https://travelwithcoen.com/up`, die je mailt als de site plat ligt.
- [ ] Iemand thuis met toegang tot de server, TransIP, Cloudflare en dit bestand, voor als het misgaat.
- [ ] Beide domeinnamen (`.com` en `.nl`) bij TransIP en de VPS **automatisch verlengen** (anders loopt het af terwijl je in Azië bent).
- [ ] Een keer een back-up terugzetten als test, vóór vertrek.

## Nog te regelen vóór de site echt open gaat

- [ ] **Voorwaarden kaartbeelden**: de scherpe satellietbeelden bij inzoomen komen van Esri (World Imagery). Controleer of hun voorwaarden dit gebruik toestaan. NASA (uitgezoomd) en OpenFreeMap (plaatsnamen) zijn vrij te gebruiken.
- [ ] Garmin koppelen (stap 18). Andere locatie-apps kunnen posities sturen naar `POST /api/tracking` met `TRACKING_INGEST_TOKEN`.
