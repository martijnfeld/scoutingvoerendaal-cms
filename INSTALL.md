# Installatie op shared hosting (PHP + MySQL)

Deze website bestaat uit gewone PHP-bestanden en een MySQL-database. Dit
werkt op vrijwel elke shared hosting (PHP 7.4+ / MySQL 5.7+ of MariaDB).

Wil je dit eerst lokaal testen? Zie [Lokaal testen met Docker](#lokaal-testen-met-docker)
onderaan dit document — dan hoef je niets van onderstaande stappen zelf te
doen.

## 1. Database aanmaken

Maak in je hosting-controlepaneel (bv. DirectAdmin/cPanel/Plesk) een nieuwe
MySQL-database en een databasegebruiker aan, en noteer:
- databasenaam
- gebruikersnaam
- wachtwoord
- hostnaam (meestal `localhost`)

Je hoeft **geen** SQL handmatig te importeren — dat doet `install.php` in
stap 4 automatisch voor je.

## 2. Bestanden uploaden

Upload de volledige inhoud van deze map naar de hoofdmap (of subdomein-map)
van je hosting, bijvoorbeeld via FTP/SFTP.

## 3. Configuratie invullen

Kopieer [`config.local.php.example`](config.local.php.example) naar
`config.local.php` (zelfde map) en vul de databasegegevens uit stap 1 in:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASS', '...');
```

`config.local.php` staat los van [`config.php`](config.php) (dat wél in de
repository/git staat) zodat een latere update via **Beheerpaneel → Updates**
je databasegegevens en geheime sleutels nooit overschrijft.

## 4. Installatiewizard doorlopen

1. Open in je browser: `https://jouw-domein.nl/install.php`
2. Klopt de databaseverbinding (stap 3), dan zie je een knop **"Database
   installeren"** — die maakt in één klik alle tabellen aan en vult ze met
   de standaard startgegevens (voorheen een handmatige phpMyAdmin-import).
3. Kies daarna een gebruikersnaam en wachtwoord voor het CMS.
4. Daarna verwijdert `install.php` zichzelf van de server. Meldt de pagina
   dat dat niet lukte (bestandsrechten), **verwijder `install.php` dan zelf**
   — dit voorkomt dat iemand anders een tweede installatie kan starten.

Lukt de automatische database-installatie niet (zeldzaam, bv. een host die
multi-statement queries blokkeert)? Importeer dan zelf
[`sql/install.sql`](sql/install.sql) via phpMyAdmin, zoals voorheen, en
vernieuw de installatiepagina.

## 5. Inloggen op het beheerpaneel

Ga naar `https://jouw-domein.nl/admin/` en log in met het zojuist
aangemaakte account. Vanuit het beheerpaneel kun je:

- **Teksten & gegevens** — alle vaste teksten en gegevens (hero, contact,
  telefoonnummer, adres, verhuurvoorwaarden, footer, social media, etc.)
- **Speltakken** — naam, kleur, leeftijden, volgorde, toelichting, leiding
  en de opkomsten-JSON-feed-URL per speltak. Zet een speltak op "inactief"
  om hem (tijdelijk) van de website te halen.
- **Info-vakjes** — de kaarten bij "Doe mee!" zoals *Gratis kennismaken*,
  *Inschrijven & contributie*, etc. Je kunt er zoveel toevoegen, bewerken
  of verwijderen als je wilt, en de volgorde met de pijltjes aanpassen.
- **Documenten (PDF's)** — upload, vervang of verwijder PDF-bestanden
  (zoals het inschrijfformulier). Koppel een document aan een info-vakje
  om er automatisch een downloadknop bij te tonen.
- **Wachtwoord wijzigen** — je eigen inlogwachtwoord aanpassen.
- **Updates** — controleer op een nieuwe versie, lees de releasenotes en werk de website met één klik bij.
- **Teksten & gegevens → Statistieken & cookies** — Google Analytics aanzetten
  en de teksten van de cookiebanner aanpassen (zie hieronder).

## Google Analytics (bezoekersstatistieken)

De website kan bezoekersstatistieken bijhouden met Google Analytics 4 (GA4).
Dat staat standaard **uit**; je zet het aan door een measurement-ID in te
vullen. Er is geen extra code, plug-in of FTP-werk voor nodig.

### Aanzetten

1. Ga naar [analytics.google.com](https://analytics.google.com/) en log in
   met een Google-account (bij voorkeur een algemeen account van de groep,
   niet een persoonlijk account van één vrijwilliger).
2. Maak een **account** en een **property** aan (bijvoorbeeld "Scouting
   Voerendaal", tijdzone Nederland, valuta euro). Heb je al een property,
   sla deze stap dan over.
3. Kies bij **Beheer → Gegevensverzameling en -aanpassing → Gegevensstreams**
   voor **Web**, vul het adres van de website in (`https://jouw-domein.nl`)
   en geef de stream een naam.
4. Kopieer het **measurement-ID** van de stream. Dat begint met `G-`
   (bijvoorbeeld `G-AB12CD34EF`). Oude Universal Analytics-codes (`UA-…`)
   werken niet meer en worden geweigerd.
5. Ga in het beheerpaneel naar **Teksten & gegevens → Statistieken &
   cookies**, plak het ID in **Google Analytics measurement-ID** en klik op
   **Opslaan**.
6. Vul daar ook de **link naar je privacyverklaring** in: een volledig adres
   (`https://…`) of de slug van een pagina op deze site (bijvoorbeeld
   `privacy`, aangemaakt via **Pagina's**).

Wil je Google Analytics weer uitzetten, maak het measurement-ID dan leeg en
sla op. De cookiebanner verdwijnt dan ook.

### Cookiebanner en Consent Mode

Zodra er een measurement-ID is ingesteld, toont de website een cookiebanner
([cookieconsent](https://github.com/orestbida/cookieconsent) van
orestbida). Google Analytics draait in **Consent Mode v2**:

- Zolang een bezoeker geen toestemming heeft gegeven, plaatst Google
  Analytics **geen cookies**. Google ontvangt dan alleen anonieme,
  cookieloze meldingen, waarmee GA4 het bezoek kan schatten.
- Kiest de bezoeker **Accepteren**, dan mag Google Analytics wel cookies
  plaatsen (`_ga`, `_ga_…`) en worden bezoeken volledig gemeten.
- Kiest de bezoeker **Weigeren**, dan blijft alles cookieloos. Trekt een bezoeker
  de toestemming later in, dan worden de `_ga`-cookies gewist.
- Advertentiefuncties (Google Signals, advertentiepersonalisatie) staan
  altijd uit.
- De keuze wordt 6 maanden onthouden in de cookie `cc_cookie`. Bezoekers
  kunnen hem altijd aanpassen via **Cookie-instellingen** onderaan elke pagina.

De titel, tekst en knopteksten van de banner pas je aan op hetzelfde
tabblad; laat je een veld leeg, dan wordt de standaardtekst gebruikt. De
bibliotheek wordt vanaf jsDelivr geladen (net als Bootstrap). Is jsDelivr
onbereikbaar of geblokkeerd, dan verschijnt er geen banner en blijft Google
Analytics cookieloos.

### Privacy (AVG)

Je bent als groep zelf verantwoordelijk voor het privacyvriendelijk inzetten
van Google Analytics. De Autoriteit Persoonsgegevens adviseert in elk geval:

- Accepteer in Google Analytics de **gegevensverwerkingsvoorwaarden** (Beheer
  → Accountinstellingen → *Gegevensverwerkingsvoorwaarden*); dat geldt als
  verwerkersovereenkomst met Google.
- Zet onder **Beheer → Accountinstellingen** het **delen van gegevens** met
  Google (voor producten, technische ondersteuning, enz.) uit.
- Laat **Google Signals** en **gegevens voor advertenties** uitgeschakeld
  (Beheer → Gegevensverzameling).
- Kies een korte **bewaartermijn** (Beheer → Gegevensbewaring, bijvoorbeeld 2
  maanden).
- Beschrijf in je **privacyverklaring** dat je Google Analytics gebruikt,
  waarvoor, welke cookies, en dat dit alleen met toestemming gebeurt. Link
  ernaar vanuit de cookiebanner.

**Beheer → Systeemcontrole** waarschuwt als het ingestelde ID ongeldig is of
als de link naar de privacyverklaring ontbreekt.

### Wat wordt er gemeten?

Google Analytics meet zelf al (via *Verbeterde metingen*, standaard aan bij
een webstream): paginaweergaven, scrollen tot onderaan (`scroll`), klikken
op links naar andere websites zoals Facebook, Instagram, Google Maps en de
afmeldlinks (`click`), en downloads van PDF's (`file_download`).

Daarbovenop stuurt de website deze eigen gebeurtenissen:

| Gebeurtenis | Wanneer | Parameters |
|---|---|---|
| `section_view` | Een onderdeel van de homepage komt in beeld (één keer per bezoek aan de pagina) | `section` (`programma`, `lidworden`, `verhuur`, `contact`) |
| `contact_click` | Klik op een e-mailadres of telefoonnummer | `contact_type` (`algemeen`, `verhuur` of `overig`), `contact_method` (`email`/`telefoon`), `section` |
| `cta_click` | Klik op een van de knoppen bovenaan de homepage | `cta_position` (`hero_1`/`hero_2`), `cta_text` |
| `speltak_select` | Klik op een speltak in de knoppenrij bij Programma | `speltak` |
| `afmeld_click` | Klik op de afmeldlink van een speltak | `speltak` |
| `document_download` | Klik op een downloadknop bij een "Doe mee!"-vakje | `document_name`, `card_title` |

Elke gebeurtenis (ook de paginaweergave) krijgt bovendien `page_type`
(`home`, `pagina` of `niet_gevonden`) en, bij losse pagina's, `page_slug`
mee. Er worden **geen persoonsgegevens** verstuurd: van een e-mailadres of
telefoonnummer gaat alleen het soort contact mee, niet het adres zelf.

`document_download` staat los van het automatische `file_download`: dat
laatste kent alleen de (willekeurige) bestandsnaam, het eigen event de naam
van het document.

**Parameters zichtbaar maken in rapporten.** Google Analytics ontvangt de
parameters altijd, maar toont ze pas in rapporten en verkenningen als je ze
registreert: ga naar **Beheer → Gegevensweergave → Aangepaste definities →
Aangepaste dimensie maken**, kies bereik *Gebeurtenis* en vul als
gebeurtenisparameter achtereenvolgens `section`, `contact_type`,
`contact_method`, `cta_position`, `speltak`, `document_name`, `page_type` en
`page_slug` in (de weergavenaam mag Nederlands zijn, bijvoorbeeld "Sectie").
Registratie werkt alleen vooruit: oudere gegevens krijgen de dimensie niet
alsnog.

**Belangrijke acties als conversie.** Markeer onder **Beheer →
Gegevensweergave → Belangrijke gebeurtenissen** in elk geval `contact_click`
(iemand wil contact, bijvoorbeeld om lid te worden of het clubgebouw te
huren), en eventueel `document_download`. Ze verschijnen in die lijst zodra
ze één keer zijn binnengekomen.

### Controleren of het werkt

1. Open de website in een privévenster: de cookiebanner moet verschijnen.
2. Klik op **Accepteren** en open in Google Analytics **Rapporten →
   Realtime**; binnen een minuut hoort je bezoek daar te staan.
3. Voor meer detail: gebruik [Tag Assistant](https://tagassistant.google.com/)
   of de ontwikkelaarstools van je browser (tabblad *Netwerk*, zoek op
   `collect`). Vóór toestemming zie je daar `gcs=G100`, na Accepteren
   `gcs=G101` (advertentieopslag blijft altijd geweigerd).
4. Klik onderaan de pagina op **Cookie-instellingen**, zet *Analytische
   cookies* uit en sla op: de `_ga`-cookies verdwijnen.
5. De eigen gebeurtenissen controleer je in **Beheer → DebugView**: open de
   website met [Tag Assistant](https://tagassistant.google.com/) verbonden,
   scrol over de homepage en klik op een speltak, een downloadknop en een
   e-mailadres. De gebeurtenissen en hun parameters verschijnen dan direct.

Het kan tot 24–48 uur duren voordat bezoeken in de gewone rapporten
(buiten Realtime) verschijnen. Houd er rekening mee dat bezoekers die
weigeren of een adblocker gebruiken niet of slechts geschat meetellen.

## Google Search Console (vindbaarheid)

Met [Google Search Console](https://search.google.com/search-console) zie je
waarop mensen de website in Google vinden en of Google problemen ziet.

1. Kies in Search Console **Property toevoegen → URL-voorvoegsel** en vul
   het adres van de website in (precies zoals bij *Website-adres* in het
   beheerpaneel, bv. `https://www.scoutingvoerendaal.nl/`).
2. Kies als verificatiemethode **HTML-tag** en kopieer de tag
   (`<meta name="google-site-verification" content="…">`).
3. Plak die in het beheerpaneel bij **Teksten & gegevens → Algemeen →
   Google Search Console - verificatiecode** (de hele tag of alleen de
   code) en sla op. Klik daarna in Search Console op **Verifiëren**.
4. Dien bij **Sitemaps** de sitemap in: `sitemap.xml`.

Laat de code staan zolang je Search Console gebruikt: Google controleert
de verificatie af en toe opnieuw.

## Mappenstructuur

```
index.php                 publieke website (haalt alles uit de database)
config.php                 generieke configuratie (in git, veilig te overschrijven)
config.local.php            jouw databasegegevens + geheime sleutels (NIET in git, door jou aangemaakt)
config.local.php.example    voorbeeld/sjabloon voor config.local.php
install.php                eenmalige installatie (verwijderen na gebruik!)
sql/install.sql             databaseschema + startgegevens (verse installatie)
sql/migrations/             schema-/bestandsmigraties voor bestaande installaties (zie "Updates")
includes/                   gedeelde PHP-functies (niet direct benaderbaar)
includes/geo/               Europese IP-ranges voor de inlog-geoblokkade
tools/                      ontwikkelaarsscripts, draaien niet op de server (niet direct benaderbaar)
assets/css/style.css        opmaak van de publieke website
assets/css/admin.css        opmaak van het beheerpaneel
assets/js/main.js           programma-feed + menu/scroll-gedrag
assets/uploads/             door het CMS geüploade foto's en PDF's
admin/                      het beheerpaneel (login vereist)
backups/                    oude locatie/terugvalmap voor back-ups (niet direct benaderbaar, zie "Back-ups")
cron/backup_cron.php        startpunt voor de wekelijkse automatische back-up
```

Back-ups zelf staan standaard **niet** in deze map, maar ernaast (zie
[Back-ups](#back-ups)).

## Back-ups

Via **Beheerpaneel → Back-ups** maak je een back-up: één zipbestand met een
volledige databasedump (`database.sql`) én alle sitebestanden, inclusief
geüploade foto's en PDF's. Back-ups ouder dan 12 maanden (`BACKUP_RETENTION_MONTHS`
in `config.php`) worden automatisch verwijderd, zowel bij een handmatige als
bij een geplande back-up. Back-ups terugzetten vanuit het beheerpaneel kan
nog niet — dat volgt in een latere versie. Tot die tijd: importeer
`database.sql` via phpMyAdmin en upload de inhoud van `files/` naar de server.

Vereist de PHP-extensie `zip` (op vrijwel elke shared hosting standaard
aanwezig).

### Waar staan de back-ups?

Een back-up bevat je databasegegevens (`config.local.php`) en de
wachtwoord-hashes van alle beheerders. Daarom worden back-ups standaard
**buiten de websitemap** opgeslagen, zodat ze nooit via de browser te
downloaden zijn — ook niet op een webserver die `.htaccess` negeert (bv.
nginx). De map staat náást de websitemap en heet zoals de websitemap, met
`-backups` erachter. Bijvoorbeeld:

```
/home/jouwaccount/domains/jouw-domein.nl/public_html          ← de website
/home/jouwaccount/domains/jouw-domein.nl/public_html-backups  ← de back-ups
```

Die map wordt bij de eerste back-up automatisch aangemaakt. Onder
**Beheerpaneel → Back-ups** zie je altijd welke map er in gebruik is.

Niet elke host staat toe dat de website buiten de websitemap schrijft
(schrijfrechten, of een `open_basedir`-beperking). Lukt het daar niet, dan
valt de website automatisch terug op de map `backups/` **binnen** de
websitemap (afgeschermd via `.htaccess`, zoals in eerdere versies) en toont
**Beheerpaneel → Back-ups** een waarschuwing. Je kunt dat oplossen door de
map hierboven zelf aan te maken via FTP of het controlepaneel (en
beschrijfbaar te maken voor de webserver), of door zelf een andere map
buiten de websitemap in te stellen in `config.local.php`:

```php
define('BACKUP_DIR', '/home/jouwaccount/scouting-backups');
```

Gebruik een absoluut pad. Staat de website zelf in een submap van de
webroot (bv. `public_html/scouting/`), dan valt de standaardmap
(`public_html/scouting-backups`) nog binnen de webroot — stel dan ook zelf
een `BACKUP_DIR` buiten de webroot in; het beheerpaneel waarschuwt hier ook
voor.

Back-ups van vóór deze wijziging blijven gewoon in `backups/` staan en
worden in het beheerpaneel getoond (gemarkeerd als "oude locatie"), zodat
je ze nog kunt downloaden of verwijderen. Ze worden na 12 maanden vanzelf
opgeruimd.

### Wekelijkse automatische back-up instellen

1. Open `config.local.php` en verzin een lange, unieke waarde voor
   `BACKUP_CRON_KEY` (zolang die op de standaardwaarde staat, weigert het
   cronscript te draaien).
2. Maak in je hosting-controlepaneel (bv. DirectAdmin/cPanel/Plesk) onder
   "Cron Jobs" een nieuwe wekelijkse taak aan. De meeste shared hosts voeren
   een cronjob uit als "URL ophalen" (wget/curl); gebruik dan als opdracht:

   ```
   wget -q -O /dev/null "https://jouw-domein.nl/cron/backup_cron.php?key=JOUW_BACKUP_CRON_KEY"
   ```

   (of het curl-equivalent, afhankelijk van wat je controlepaneel aanbiedt).
3. Heeft je host wel PHP-CLI beschikbaar als cron-opdracht, dan kan de
   cronjob `cron/backup_cron.php` ook direct aanroepen zonder sleutel, bv.
   `php /home/jouwaccount/domains/jouw-domein.nl/public_html/cron/backup_cron.php`.
4. Heeft je hostingpakket helemaal geen cronjobs, gebruik dan de gratis
   dienst [cron-job.org](https://cron-job.org) om de URL uit stap 2 wekelijks
   op te halen.

**Beheerpaneel → Back-ups** toont voor alle drie de varianten de kant-en-klare
instellingen voor jouw site (pad, crontab-regel en URL met sleutel).

Elke keer dat `cron/backup_cron.php` draait, wordt er een nieuwe back-up
gemaakt én worden back-ups ouder dan 12 maanden meteen opgeruimd — een losse
opschoontaak is niet nodig.

## Updates

Via **Beheerpaneel → Updates** controleer je op een nieuwe versie van deze
website (op basis van GitHub-releases), lees je de releasenotes, en werk je
in één klik bij:

1. Er wordt eerst automatisch een volledige back-up gemaakt (zoals bij
   **Back-ups** hierboven) — mislukt de back-up, dan wordt er niet
   bijgewerkt.
2. De nieuwste release wordt van GitHub gedownload en uitgepakt over de
   bestaande bestanden. `config.local.php`, `assets/uploads/`, `backups/`,
   `includes/cache/` en `install.php` worden **nooit** overschreven of
   aangeraakt.
3. Nieuwe database-/bestandsmigraties uit `sql/migrations/` worden
   automatisch uitgevoerd (bijgehouden in de tabel `schema_migrations`, zodat
   elke migratie maar één keer draait).

Vereist dezelfde PHP-extensie `zip` als back-ups, plus uitgaande
HTTPS-verbindingen vanaf de server (niet elke shared host staat dat toe).
Kan een server dit niet automatisch, dan toont de updatepagina dat en kun je
in plaats daarvan de nieuwste release handmatig downloaden van GitHub en via
FTP/SFTP uploaden (met dezelfde bestanden/mappen ongewijzigd gelaten) —
klik daarna op **"Alleen migraties uitvoeren"** op de updatepagina om
eventuele nieuwe database-/bestandswijzigingen alsnog toe te passen.

Standaard staat `GITHUB_REPO` in `config.php` nog op een placeholder-waarde;
zet in `config.local.php` je eigen `owner/repo`-combinatie als je een fork
van deze repository gebruikt.

## Beveiliging

- `install.php` weigert een tweede keer te draaien zodra er al een
  beheerder bestaat en probeert zichzelf dan te verwijderen; lukt dat niet,
  verwijder het bestand dan zelf.
- De mappen `includes/`, `admin/includes/`, `sql/`, `tools/`, `backups/` en
  `docker/`, verborgen mappen/bestanden (`.git/`, `.htaccess`, ...) en
  ontwikkel-/configuratiebestanden (`*.md`, `*.sql`, `*.yml`, `*.zip`,
  `Dockerfile`, `error_log`, ...) zijn afgeschermd via `.htaccess` en niet
  rechtstreeks vanaf de browser te benaderen (zie ook
  [Hostingvereisten en nginx](#hostingvereisten-en-nginx)).
- In `assets/uploads/` kunnen geen scripts worden uitgevoerd, ook niet als
  daar per ongeluk een verkeerd bestand terechtkomt: alleen afbeeldingen
  (jpg/png/gif/webp) en PDF's worden daar geserveerd. Zet je zelf bestanden
  via FTP in die map, gebruik dan geen extra punten in de bestandsnaam
  (`logo.v2.png` wordt geweigerd, `logo-v2.png` werkt wel). Via het
  beheerpaneel geüploade bestanden krijgen altijd een veilige naam.
- Ingelogd blijven in het beheerpaneel duurt maximaal 2 uur zonder
  activiteit en maximaal 12 uur in totaal; daarna moet je opnieuw inloggen.
  Wordt het wachtwoord van een account gewijzigd, dan worden alle andere
  sessies van dat account uitgelogd.
- Wachtwoorden voor het beheerpaneel moeten minimaal 12 tekens lang zijn,
  met minimaal 2 cijfers en 2 speciale tekens.
- Inloggen op het beheerpaneel kan alleen vanaf een IP-adres in Europa. De
  landenlijst zit in de website zelf (`includes/geo/`, bijgewerkt met elke
  release), er worden geen IP-adressen naar externe diensten gestuurd. Ben
  je tijdelijk buiten Europa (of via een VPN met een niet-Europees
  IP-adres), zet dan `define('ADMIN_GEO_BLOCK', false);` in
  `config.local.php` en haal het na afloop weer weg. Met `ADMIN_LOGIN_COUNTRIES`
  kun je het verder inperken tot specifieke landen (zie
  `config.local.php.example`).
- `config.local.php` bevat je echte databasegegevens/geheime sleutels en
  staat niet in git; bewaar het buiten de repository als je losse back-ups
  van bestanden maakt.
- Back-ups (met daarin `config.local.php` en een volledige databasedump)
  worden standaard buiten de websitemap opgeslagen, en hebben een
  willekeurig achtervoegsel in de bestandsnaam zodat ze niet te raden zijn
  (zie [Back-ups](#back-ups)).
- Updates worden alleen via HTTPS gedownload, met controle van het
  certificaat van GitHub.
- Opkomsten-feeds (Scoutdash) moeten met `https://` beginnen en worden
  alleen via HTTPS met certificaatcontrole opgehaald.

### Hostingvereisten en nginx

De afscherming hierboven werkt via `.htaccess`-bestanden. Die werken op
Apache (2.2 en 2.4) en LiteSpeed, en zijn zo geschreven dat ze geen
foutmelding geven als een module ontbreekt. Wel nodig: de host moet in
`.htaccess` minimaal `AuthConfig FileInfo Indexes Limit Options` toestaan
(`AllowOverride`; bij vrijwel elke shared hosting het geval). Geeft de hele
site direct na het uploaden een **"500 Internal Server Error"**, zet dan
in de `.htaccess` in de hoofdmap een `#` vóór de regel `Options -Indexes`
en probeer het opnieuw.

**Let op bij nginx.** nginx leest geen `.htaccess`-bestanden. Dat geldt
voor hosting die volledig op nginx draait, maar ook voor Plesk met de optie
*"Statische bestanden direct door nginx serveren"* aan: dan levert nginx
bv. `.zip`-, `.sql`- en `.md`-bestanden zelf uit, buiten `.htaccess` om.
Zet in dat geval die optie uit, of voeg onderstaande regels toe in het
controlepaneel (bij Plesk: *Apache & nginx-instellingen → Extra
nginx-instructies*). De blokkerende regels moeten vóór de regel voor
`.php`-bestanden staan. Ook op **OpenLiteSpeed** werken alleen de
rewrite-regels uit `.htaccess`; gebruik daar de gelijkwaardige regels in
het controlepaneel.

```nginx
autoindex off;
location = /sitemap.xml { rewrite ^ /sitemap.php last; }
location ~ /\.(?!well-known/) { return 404; }
location ~ ^/(includes|admin/includes|sql|backups|tools|docker|tests)(/|$) { return 404; }
location ~* (\.(bak|old|orig|save|swp|swo|tmp|dist|example|sample|sql|sqlite|db|log|md|markdown|ini|conf|cnf|yml|yaml|toml|env|sh|bash|bat|cmd|ps1|inc|phps|lock|bin|zip|tar|tgz|gz|bz2|7z|rar)|~)$ { return 404; }
location ~* /(Dockerfile|docker-compose\.ya?ml|composer\.(json|lock)|package(-lock)?\.json|Makefile|error_log)$ { return 404; }
location ^~ /assets/uploads/ {
    location ~* ^/assets/uploads/[^/.]+\.(jpe?g|png|gif|webp|pdf)$ {
        add_header X-Content-Type-Options "nosniff" always;
        expires 1d;
    }
    return 404;   # alles anders (ook .php) nooit serveren/uitvoeren
}
```

De `.htaccess` comprimeert ook tekstbestanden (HTML, CSS, JS) met gzip.
Op nginx staat dat meestal al aan; controleer het in je browser
(ontwikkelaarstools → Netwerk → de pagina → `Content-Encoding: gzip`). Staat het
uit, voeg dan ook deze regels toe — maar niet als je host `gzip` al zelf
instelt, want dan weigert nginx de dubbele regel:

```nginx
gzip on;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml image/svg+xml;
```

Juist op zulke hosts is het extra belangrijk dat back-ups buiten de
websitemap staan (de standaard, zie [Back-ups](#back-ups)).

## Lokaal testen met Docker

Voor lokaal ontwikkelen/testen staat er een `docker-compose.yml` klaar met
PHP + Apache, MySQL en phpMyAdmin. Dit is **alleen voor lokaal gebruik**,
niet voor shared hosting (daar upload je gewoon de bestanden, zie hierboven).

Vereist: [Docker Desktop](https://www.docker.com/products/docker-desktop/)
(of Docker Engine + Compose plugin op Linux).

```bash
docker compose up -d --build
```

De eerste keer duurt dit even (image bouwen + database initialiseren met
`sql/install.sql`). Daarna:

1. Ga naar **http://localhost:8080/install.php** en maak een beheerdersaccount aan.
   (Verwijderen van `install.php` hoeft lokaal niet, maar kan geen kwaad.)
2. Website: **http://localhost:8080/**
3. Beheerpaneel: **http://localhost:8080/admin/**
4. phpMyAdmin (databasebeheer): **http://localhost:8081/**
   (server: `db`, gebruiker: `root`, wachtwoord: `root`)

Je hebt lokaal geen `config.local.php` nodig — de databasegegevens komen
automatisch uit environment-variabelen die in `docker-compose.yml` staan
(`config.php` valt daarop terug zolang `config.local.php` niet bestaat). De
hele projectmap is in de container gemount, dus wijzigingen aan PHP/CSS-
bestanden zijn direct zichtbaar, en uploads/wijzigingen via het beheerpaneel
komen gewoon in je lokale map terecht.

Stoppen:

```bash
docker compose down        # database blijft bewaard voor de volgende keer
docker compose down -v     # ook de database wissen en helemaal opnieuw beginnen
```
