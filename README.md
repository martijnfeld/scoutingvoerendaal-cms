# Scouting Voerendaal — website & CMS

De website van [Scouting Voerendaal](https://scoutingvoerendaal.nl/), met een eigen, eenvoudig
beheerpaneel (CMS). Gewone PHP-bestanden plus een MySQL-database: geen framework, geen build-stap,
geen Composer of npm. Het draait op vrijwel elke gewone shared hosting.

## Wat zit erin?

**Publieke website**
- Een one-pager met hero, programma per speltak, lid worden, verhuur en contact.
- Losse informatiepagina's (bv. ledeninformatie) op nette adressen zoals `/ledeninfo`, eventueel in
  het menu.
- Programma per speltak automatisch uit Scoutdash (opkomsten-feed): de server haalt de feeds op,
  bewaart ze 10 minuten in een cache en schoont de inhoud op, zodat de site gewoon blijft werken als
  Scoutdash even plat ligt.
- `sitemap.xml`, `robots.txt` en gestructureerde gegevens (JSON-LD) voor zoekmachines.

**Beheerpaneel** (`/admin/`)
- Alle teksten en gegevens van de site (contact, verhuur, footer, social media, …).
- Speltakken, "Doe mee!"-vakjes, PDF-documenten, informatiepagina's en uploads.
- Uitgebreide teksteditor (CKEditor 5) met afbeeldingen, tabellen en YouTube-video's.
- Beheerdersaccounts en wachtwoord wijzigen.
- **Back-ups** — database plus alle bestanden in één zip, handmatig of wekelijks via een cronjob.
- **Updates** — nieuwe versies met één klik installeren vanaf GitHub (met automatische back-up
  vooraf en databasemigraties achteraf).
- **Systeemcontrole** — één pagina die laat zien of de server, configuratie en beveiliging in orde
  zijn.

**Beveiliging** — o.a. CSRF-bescherming, veilige sessies met time-outs, een wachtwoordbeleid,
inloggen alleen vanuit Europa (zonder externe dienst), een Content-Security-Policy, afgeschermde
mappen via `.htaccess` en back-ups buiten de webroot. Zie
[INSTALL.md → Beveiliging](INSTALL.md#beveiliging).

## Installeren

Benodigd: PHP 7.4+ (met `pdo_mysql` en `zip`) en MySQL 5.7+ of MariaDB, op Apache of LiteSpeed.

In het kort:
1. Maak een database aan in het controlepaneel van je hosting.
2. Download de [nieuwste release](https://github.com/martijnfeld/scoutingvoerendaal-cms/releases/latest)
   en upload de bestanden via FTP/SFTP.
3. Kopieer `config.local.php.example` naar `config.local.php` en vul je databasegegevens in.
4. Open `https://jouw-domein.nl/install.php`, installeer de database en maak een beheerder aan.
5. Log in op `https://jouw-domein.nl/admin/`.

De volledige handleiding — inclusief back-ups, de wekelijkse cronjob, updates, nginx en
probleemoplossing — staat in **[INSTALL.md](INSTALL.md)**.

### Ook iets voor jouw scoutinggroep?

Deze website is gemaakt voor Scouting Voerendaal, maar andere groepen mogen hem ook gebruiken. Alle
teksten, speltakken, foto's en documenten pas je aan via het beheerpaneel, dus programmeren is niet
nodig. Installeer gewoon de releases uit **deze** repository: dan krijg je nieuwe versies en
beveiligingsupdates automatisch binnen via **Beheerpaneel → Updates**.

Wil je de code zelf aanpassen? Dan kun je een fork maken en in `config.local.php` `GITHUB_REPO` op je
eigen repository zetten. Let op: je krijgt dan alleen nog updates uit je eigen fork. Daarom vragen we
je om verbeteringen die ook voor anderen nuttig zijn liever hier bij te dragen (zie
[Bijdragen](#bijdragen)), zodat iedereen ervan profiteert.

## Lokaal ontwikkelen

Voor lokaal ontwikkelen is er een Docker-omgeving (PHP 8.2 + Apache, MySQL 8, phpMyAdmin). Die is
alleen bedoeld voor ontwikkeling en wordt nooit zelf op de server gezet.

```bash
docker compose up -d --build   # starten
docker compose down            # stoppen (database blijft bewaard)
docker compose down -v         # stoppen en database wissen
```

| Wat                | Adres                                  |
|--------------------|----------------------------------------|
| Website            | http://localhost:8080/                 |
| Eerste beheerder   | http://localhost:8080/install.php      |
| Beheerpaneel       | http://localhost:8080/admin/           |
| phpMyAdmin         | http://localhost:8081/ (`root`/`root`) |

De projectmap is in de container gemount, dus wijzigingen aan PHP/CSS/JS zie je direct. Lokaal heb je
geen `config.local.php` nodig.

### Mappenstructuur

```
index.php, pagina.php   publieke website (one-pager en losse pagina's)
admin/                  beheerpaneel, één bestand per scherm
api/opkomsten.php       opkomsten-feed voor de browser (via de server opgehaald uit Scoutdash)
includes/               gedeelde PHP-code (niet via de browser bereikbaar)
assets/                 CSS, JavaScript en geüploade bestanden (assets/uploads/)
sql/install.sql         volledig databaseschema voor een nieuwe installatie
sql/migrations/         genummerde migraties voor bestaande installaties
cron/backup_cron.php    startpunt voor de automatische back-up
tools/                  ontwikkelscripts (bv. het bijwerken van de geo-IP-data)
tests/                  automatische tests (tests/run.php), niet in de release-zip
```

### Tests

Eigen, kleine testrunner zonder Composer of PHPUnit. Drie suites: `unit` (pure PHP), `db` (tegen
een aparte testdatabase `scouting_test`, die bij elke run gewist wordt) en `http` (tegen de draaiende
site: afscherming, headers, inloggen en alle beheerschermen).

```bash
docker compose exec -e XDEBUG_MODE=off web php tests/run.php            # alle suites
docker compose exec -e XDEBUG_MODE=off web php tests/run.php unit       # alleen unit
docker compose exec -e XDEBUG_MODE=off web php tests/run.php --filter=slug
```

De beheerdertests in de `http`-suite draaien lokaal alleen als je inloggegevens van je lokale
beheerder meegeeft (`-e TEST_ADMIN_USER=... -e TEST_ADMIN_PASS=...`); anders worden ze overgeslagen.
GitHub Actions (`.github/workflows/tests.yml`) draait bij elke push en pull request de unit- en
databasetests op PHP 7.4 t/m 8.5 tegen MySQL 5.7/8.0 en MariaDB, plus alle suites in de
Docker-omgeving.

### Goed om te weten

- **Gewone shared hosting is het uitgangspunt**: geen shell, geen Composer/npm, geen workers. Alles
  moet werken als losse PHP-bestanden plus MySQL.
- **Databasewijziging?** Pas `sql/install.sql` aan *én* voeg een nieuwe migratie toe in
  `sql/migrations/`. Allebei moeten op hetzelfde eindresultaat uitkomen.
- **Geen inline `<script>` of `onclick=`**: de Content-Security-Policy blokkeert die. JavaScript hoort
  in `assets/js/`.
- **Releasen**: push een tag `vX.Y.Z`. De GitHub Action zet `APP_VERSION` goed en maakt de release
  aan, en neemt daarbij verse Europese IP-ranges (`includes/geo/`) mee. Daarnaast controleert de
  workflow *Geo-data bijwerken* die ranges maandelijks; zijn ze veranderd, dan publiceert hij zelf een
  nieuwe patch-release (bv. `v0.3.2` → `v0.3.3`) met alleen de geo-data. Kijk dus vóór het taggen wat
  de laatste tag is.
- **Tests**: draai `tests/run.php` (zie [Tests](#tests)) en test je wijziging daarnaast lokaal in
  Docker.

Uitgebreide technische achtergrond (architectuur, beveiligingsregels, back-ups, updates) staat in
[CLAUDE.md](CLAUDE.md).

## Bijdragen

Bijdragen zijn van harte welkom, ook van andere scoutinggroepen die deze site gebruiken! Een bug
gevonden of een idee? [Open een issue](https://github.com/martijnfeld/scoutingvoerendaal-cms/issues).
Wil je zelf iets bouwen? Maak een fork en stuur een pull request. Houd daarbij rekening met de punten
onder [Goed om te weten](#goed-om-te-weten), en schrijf teksten, commentaar en interface in het
Nederlands.
