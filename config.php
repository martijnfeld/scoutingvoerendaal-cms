<?php
/**
 * Configuratiebestand — onderdeel van de repository, veilig om bij een
 * update te overschrijven. Bevat GEEN echte wachtwoorden: dit bestand
 * definieert alleen fallback-waarden (environment-variabelen voor Docker,
 * of placeholders) voor de constanten die hieronder staan.
 *
 * Vul je échte databasegegevens en geheime sleutels in via
 * `config.local.php` (kopieer `config.local.php.example`) — dat bestand
 * staat niet in git en wordt door de updatefunctionaliteit (Beheerpaneel →
 * Updates) nooit overschreven of aangeraakt.
 *
 * Voor de Docker-ontwikkelomgeving (zie docker-compose.yml) worden de
 * databasewaarden automatisch via environment-variabelen aangeleverd, dus
 * is een config.local.php lokaal niet nodig.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// -- Database instellingen --------------------------------------------
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'vul_hier_je_database_naam_in');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'vul_hier_je_database_gebruiker_in');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'vul_hier_je_database_wachtwoord_in');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// -- Overige instellingen ----------------------------------------------
// Zet op true (of environment-variabele APP_DEBUG=1) om tijdens
// ontwikkelen PHP-foutmeldingen te zien.
if (!defined('APP_DEBUG')) define('APP_DEBUG', getenv('APP_DEBUG') === '1' ? true : false);

// Map waar geüploade bestanden (foto's, PDF's) worden opgeslagen.
// Moet beschrijfbaar zijn door de webserver.
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/assets/uploads');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', 'assets/uploads');

// -- Back-ups ------------------------------------------------------------
// Map waar back-upbestanden (zip met database + sitebestanden) worden
// opgeslagen. Een back-up bevat config.local.php en alle wachtwoord-hashes,
// dus staat deze standaard BUITEN de websitemap: een map naast de
// projectmap, genoemd naar die map met "-backups" erachter. Staat de site
// bv. in /home/account/domains/jouw-domein.nl/public_html, dan wordt dat
// /home/account/domains/jouw-domein.nl/public_html-backups.
// Wordt automatisch aangemaakt. Staat de host schrijven buiten de
// websitemap niet toe, dan valt de site terug op `backups/` binnen de
// projectmap (afgeschermd via .htaccess) en waarschuwt Beheerpaneel →
// Back-ups daarover. Een eigen map instellen kan in config.local.php.
if (!defined('BACKUP_DIR')) define('BACKUP_DIR', dirname(__DIR__) . '/' . basename(__DIR__) . '-backups');

// Hoeveel maanden een back-up bewaard blijft voordat hij automatisch wordt
// verwijderd (zowel bij handmatige als geplande back-ups).
if (!defined('BACKUP_RETENTION_MONTHS')) define('BACKUP_RETENTION_MONTHS', 12);

// Geheime sleutel om de wekelijkse back-up via een URL te mogen starten
// vanuit een cronjob in je hosting-controlepaneel, bv.:
//   https://jouw-domein.nl/cron/backup_cron.php?key=...
// Verzin een lange, unieke waarde voordat je de cronjob instelt — zie
// INSTALL.md. Zolang deze op de standaardwaarde staat, weigert het
// cronscript te draaien.
if (!defined('BACKUP_CRON_KEY')) define('BACKUP_CRON_KEY', getenv('BACKUP_CRON_KEY') ?: 'wijzig_deze_geheime_sleutel');

// -- Updates ---------------------------------------------------------
// GitHub-repository (owner/repo) waar Beheerpaneel → Updates op controleert
// en updates vandaan haalt. Gebruik je een eigen fork, zet dan je eigen
// owner/repo in config.local.php.
if (!defined('GITHUB_REPO')) define('GITHUB_REPO', getenv('GITHUB_REPO') ?: 'jouw-gebruikersnaam/jouw-repo-naam');

// -- Beveiliging beheerpaneel ----------------------------------------
// Sta inloggen op /admin alleen toe vanaf IP-adressen in Europa (zie
// includes/geoip.php). Zit je tijdelijk buiten Europa, zet dit dan in
// config.local.php op false. Wil je de landenlijst inperken, definieer dan
// ADMIN_LOGIN_COUNTRIES in config.local.php (zie config.local.php.example).
if (!defined('ADMIN_GEO_BLOCK')) define('ADMIN_GEO_BLOCK', true);

date_default_timezone_set('Europe/Amsterdam');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}
