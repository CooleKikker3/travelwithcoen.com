# Garmin inReach koppelen aan de website

De website haalt **elke 10 minuten** nieuwe posities op uit je **MapShare**-feed bij Garmin.
Je inReach stuurt zijn positie via satelliet naar Garmin, Garmin zet die op MapShare, en de website leest MapShare uit.
Op het apparaat zelf hoeft niets geïnstalleerd te worden.

---

## Stap 1 — MapShare aanzetten (website van Garmin)

1. Ga naar **explore.garmin.com** en log in met je Garmin-account.
2. Open **Social** → het onderdeel **MapShare** (de menunaam kan iets afwijken).
3. Zet **MapShare aan**.
4. Kies een **MapShare-naam**, bijvoorbeeld `coenvink`. Je pagina wordt dan `share.garmin.com/coenvink`.
5. **Aanrader:** stel een **MapShare-wachtwoord** in. Anders kan iedereen via Garmin je live locatie zien, en dat omzeilt de vertraging van 2 weken op de website.

## Stap 2 — De website vertellen waar de feed staat (`.env`)

Zet deze twee regels in `.env` (lokaal én op de server):

```
GARMIN_MAPSHARE_URL=https://share.garmin.com/Feed/Share/coenvink
GARMIN_MAPSHARE_PASSWORD=jouw-mapshare-wachtwoord
```

- Vervang `coenvink` door je eigen MapShare-naam.
- Geen wachtwoord ingesteld? Laat de tweede regel weg.
- Op de server daarna: `php artisan optimize`.

## Stap 3 — Tracking aanzetten op je inReach

1. Op het apparaat (of in de Garmin Explore-app): **Tracking → Starten**.
2. Kies een **interval**: **10 minuten** is een goede balans tussen detail, batterij en je abonnement.

## Stap 4 — Testen

1. Zet tracking aan, maak een wandeling en wacht een kwartiertje.
2. Voer uit in de map van het project:
   ```
   php artisan garmin:sync
   ```
3. Je ziet dan *"X new position(s) from Garmin"*, en de punten staan in het beheer onder **Locatiepunten**.

---

## Daarna gaat het vanzelf

- **Elke 10 minuten** haalt de site nieuwe posities op, zolang de scheduler draait
  (lokaal: `php artisan schedule:work`, online: de cronjob uit `DEPLOYMENT.md`, stap 7).
- **Vertraging en privacy:** bezoekers zien posities pas na 2 weken, en niet binnen 1 km van thuis.
  Ingelogde familie ziet alles live.
- **Landen:** elke positie krijgt het juiste land, en "loop ik nu" werkt zichzelf bij.
- **Geen bereik:** stuurt de inReach posities later door, dan worden ze alsnog opgehaald (tot 6 uur terug vanaf de laatste positie).
- **Dubbel:** hetzelfde punt twee keer ophalen wordt genegeerd.

## Werkt het niet?

- **"No GARMIN_MAPSHARE_URL set."** → stap 2: de regel staat niet (goed) in `.env`, of op de server is `php artisan optimize` vergeten.
- **Fout 401 / 403** → het MapShare-wachtwoord in `.env` klopt niet, of MapShare staat uit.
- **"0 new position(s)"** terwijl je wel liep → staat tracking aan op het apparaat? Zie je de punten op je MapShare-pagina (`share.garmin.com/<naam>`)? Pas als ze daar staan, kan de website ze ophalen.
- Iets anders → de foutmelding van `php artisan garmin:sync` aan Claude sturen.

*Technisch: `App\Services\GarminMapShare` leest de KML-feed; ingesteld via `config/travel.php` (`garmin`). Andere locatie-apps kunnen ook posities sturen naar `POST /api/tracking` met `TRACKING_INGEST_TOKEN`.*
