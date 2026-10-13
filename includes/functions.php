<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/version.php';

/** Veilig HTML-escapen voor output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * URL naar een eigen CSS/JS-bestand met de versie als query-string, zodat
 * browsers na een update meteen de nieuwe versie ophalen (cachebusting).
 */
function asset_url(string $path): string
{
    return $path . '?v=' . rawurlencode(APP_VERSION);
}

/** Rendert een vast Font Awesome-icoon voor decoratief gebruik in de UI. */
function fa_icon(string $name, string $class = ''): string
{
    $icons = [
        'layout-dashboard' => 'table-columns',
        'settings' => 'gear',
        'file-text' => 'file-lines',
        'sitemap' => 'diagram-project',
        'layout-cards' => 'table-cells-large',
        'files' => 'copy',
        'cloud-upload' => 'cloud-arrow-up',
        'checklist' => 'list-check',
        'database' => 'database',
        'refresh' => 'arrows-rotate',
        'users' => 'users',
        'key' => 'key',
        'user' => 'user',
        'logout' => 'right-from-bracket',
        'menu-2' => 'bars',
        'chevron-down' => 'chevron-down',
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    return '<i class="fa-solid fa-' . $icons[$name]
        . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true"></i>';
}

/* ------------------------------------------------------------------ *
 * Instellingen (settings key/value tabel)
 * ------------------------------------------------------------------ */

function get_all_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function get_setting(string $key, string $default = ''): string
{
    $all = get_all_settings();
    return $all[$key] ?? $default;
}

/** Vervangt {jaar} door het huidige jaar in een instellingswaarde. */
function get_setting_with_year(string $key, string $default = ''): string
{
    return str_replace('{jaar}', date('Y'), get_setting($key, $default));
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute(['k' => $key, 'v' => $value]);
}

/* ------------------------------------------------------------------ *
 * Google Analytics + cookiebanner
 * ------------------------------------------------------------------ */

/** Versie van orestbida/cookieconsent (jsDelivr), gebruikt voor de cookiebanner. */
const COOKIECONSENT_VERSION = '3.1.0';

/** GA4-measurement-ID: G- gevolgd door letters/cijfers. */
const GA_MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]{4,15}$/';

/**
 * Standaardteksten van de cookiebanner; gelijk aan de waarden in
 * sql/install.sql en migratie 0004, en de terugvaloptie als een veld leeg is.
 */
const COOKIE_BANNER_DEFAULTS = [
    'cookie_banner_title' => 'Wij gebruiken cookies',
    'cookie_banner_text' => 'Met jouw toestemming gebruiken we Google Analytics om anoniem bij te houden hoe onze website wordt gebruikt. Zonder toestemming plaatsen we geen analytische cookies.',
    'cookie_banner_accept' => 'Accepteren',
    'cookie_banner_reject' => 'Weigeren',
    'cookie_banner_settings' => 'Instellingen',
    'cookie_privacy_link' => '',
];

/**
 * Normaliseert een ingevoerd measurement-ID (spaties weg, hoofdletters).
 * Leeg mag (= Google Analytics uit); anders moet het een GA4-ID zijn.
 *
 * @throws RuntimeException met een Nederlandse foutmelding
 */
function ga_measurement_id_normalize(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '' || preg_match(GA_MEASUREMENT_ID_PATTERN, $value)) {
        return $value;
    }
    if (strpos($value, 'UA-') === 0) {
        throw new RuntimeException('Dit is een oude Universal Analytics-code (UA-…); die werkt niet meer. Gebruik het measurement-ID van een GA4-gegevensstream, dat begint met G-.');
    }
    throw new RuntimeException('Ongeldig Google Analytics measurement-ID. Het begint met G- gevolgd door letters en cijfers, bijvoorbeeld G-AB12CD34EF.');
}

/**
 * Link naar de privacyverklaring: leeg, een volledige http(s)-URL of een
 * pad op de eigen site (bijvoorbeeld "privacy" voor een CMS-pagina).
 *
 * @throws RuntimeException met een Nederlandse foutmelding
 */
function cookie_privacy_link_normalize(string $value): string
{
    $value = trim($value);
    if ($value === '' || preg_match('#^/?[A-Za-z0-9._~%/-]+(\?[A-Za-z0-9._~%&=+-]*)?(\#[A-Za-z0-9._~-]*)?$#', $value)) {
        return $value;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (filter_var($value, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true)
        && (string) parse_url($value, PHP_URL_HOST) !== '') {
        return $value;
    }
    throw new RuntimeException('Ongeldige link naar de privacyverklaring. Gebruik een volledig adres (https://…) of de slug van een pagina, bijvoorbeeld privacy.');
}

/** Het ingestelde measurement-ID, of '' als het leeg of ongeldig is (dan geen GA). */
function ga_measurement_id(): string
{
    $id = strtoupper(trim(get_setting('ga_measurement_id')));
    return preg_match(GA_MEASUREMENT_ID_PATTERN, $id) ? $id : '';
}

/** Teksten voor de cookiebanner, met de standaardtekst voor lege velden. */
function cookie_banner_texts(): array
{
    $texts = [];
    foreach (COOKIE_BANNER_DEFAULTS as $key => $default) {
        $value = trim(get_setting($key));
        $texts[$key] = $value !== '' ? $value : $default;
    }
    return $texts;
}

/**
 * HTML-attributen voor een Google Analytics-gebeurtenis bij een klik, verwerkt
 * door assets/js/ga-events.js. Zonder GA doen de attributen niets. Zet hier
 * nooit persoonsgegevens (zoals e-mailadressen) in de parameters.
 */
function ga_event_attrs(string $event, array $params = []): string
{
    $attrs = ' data-ga-event="' . e($event) . '"';
    if ($params) {
        $attrs .= ' data-ga-params="' . e((string) json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
    }
    return $attrs;
}

/* ------------------------------------------------------------------ *
 * Speltakken
 * ------------------------------------------------------------------ */

function get_speltakken(bool $only_active = false): array
{
    $sql = 'SELECT * FROM speltakken';
    if ($only_active) {
        $sql .= ' WHERE actief = 1';
    }
    $sql .= ' ORDER BY volgorde ASC, naam ASC';
    return db()->query($sql)->fetchAll();
}

function get_speltak(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM speltakken WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function slugify(string $text, string $fallback = 'item'): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : $fallback;
}

function unique_speltak_slug(string $base, ?int $excludeId = null): string
{
    $slug = slugify($base, 'speltak');
    $original = $slug;
    $i = 2;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM speltakken WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}

function get_speltak_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM speltakken WHERE slug = :slug');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Pad van de overzichtspagina van de speltakken (/speltakken, via de
 * rewrite-regel in .htaccess). Staat daarom ook in PAGE_RESERVED_SLUGS.
 */
const SPELTAKKEN_PATH = 'speltakken';

/** URL van de overzichtspagina: relatief t.o.v. de websitemap, of met $absolute volledig. */
function speltakken_url(bool $absolute = false): string
{
    return $absolute ? absolute_url(SPELTAKKEN_PATH) : SPELTAKKEN_PATH;
}

/** URL van de eigen pagina van een speltak: "speltakken/<slug>". */
function speltak_url(string $slug, bool $absolute = false): string
{
    $path = SPELTAKKEN_PATH . '/' . rawurlencode($slug);
    return $absolute ? absolute_url($path) : $path;
}

/**
 * Meta-omschrijving van een speltakpagina: de ingevulde, of anders een
 * samenvatting uit naam, leeftijd en opkomsttijd.
 */
function speltak_meta_description(array $sp): string
{
    if (trim((string) ($sp['meta_omschrijving'] ?? '')) !== '') {
        return trim($sp['meta_omschrijving']);
    }
    $text = $sp['naam'] . ' bij ' . get_setting('org_naam');
    if (!empty($sp['leeftijd'])) {
        $text .= ', voor jeugd van ' . $sp['leeftijd'];
    }
    $text .= '.';
    if (!empty($sp['dag_tijd'])) {
        $text .= ' Opkomst: ' . $sp['dag_tijd'] . '.';
    }
    return $text . ' Lees meer over de speltak en bekijk het actuele programma.';
}

/* ------------------------------------------------------------------ *
 * Documenten (PDF's)
 * ------------------------------------------------------------------ */

function get_documents(): array
{
    return db()->query('SELECT * FROM documents ORDER BY naam ASC')->fetchAll();
}

function get_document(?int $id): ?array
{
    if (!$id) return null;
    $stmt = db()->prepare('SELECT * FROM documents WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ------------------------------------------------------------------ *
 * Info-vakjes
 * ------------------------------------------------------------------ */

function get_info_cards(string $sectie): array
{
    $stmt = db()->prepare('SELECT * FROM info_cards WHERE sectie = :s ORDER BY volgorde ASC, id ASC');
    $stmt->execute(['s' => $sectie]);
    return $stmt->fetchAll();
}

function get_info_card(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM info_cards WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ------------------------------------------------------------------ *
 * Losse pagina's
 * ------------------------------------------------------------------ */

function get_pages(bool $only_active = false): array
{
    $sql = 'SELECT * FROM pages';
    if ($only_active) {
        $sql .= ' WHERE actief = 1';
    }
    $sql .= ' ORDER BY volgorde ASC, titel ASC';
    return db()->query($sql)->fetchAll();
}

/** Actieve pagina's die in het hoofdmenu getoond moeten worden. */
function get_pages_in_menu(): array
{
    return db()->query(
        'SELECT * FROM pages WHERE actief = 1 AND in_menu = 1 ORDER BY volgorde ASC, titel ASC'
    )->fetchAll();
}

function get_page(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_page_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE slug = :slug');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Slugs die niet als pagina-URL gebruikt mogen worden, omdat er een echte
 * map met die naam in de websitemap staat (die gaat in .htaccess voor), of
 * een eigen rewrite-regel (de speltakpagina's, SPELTAKKEN_PATH).
 */
const PAGE_RESERVED_SLUGS = ['admin', 'api', 'assets', 'backups', 'cron', 'docker', 'docs', 'includes', 'speltakken', 'sql', 'tests', 'tools'];

/**
 * URL van een losse pagina: "<slug>" (relatief t.o.v. de websitemap, via
 * de rewrite-regel in .htaccess), of met $absolute de volledige URL op
 * basis van site_url.
 */
function page_url(string $slug, bool $absolute = false): string
{
    $path = rawurlencode($slug);
    return $absolute ? rtrim(get_setting('site_url'), '/') . '/' . $path : $path;
}

function unique_page_slug(string $base, ?int $excludeId = null): string
{
    $slug = slugify($base, 'pagina');
    $original = $slug;
    $i = 2;
    while (true) {
        if (in_array($slug, PAGE_RESERVED_SLUGS, true)) {
            $slug = $original . '-' . $i;
            $i++;
            continue;
        }
        $sql = 'SELECT COUNT(*) FROM pages WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}

/* ------------------------------------------------------------------ *
 * SEO: structured data, Search Console, afbeeldingen
 * ------------------------------------------------------------------ */

/**
 * Volledige URL voor een pad op de eigen site (bv. "assets/uploads/x.jpg"),
 * op basis van site_url. Een volledige http(s)-URL blijft ongewijzigd.
 */
function absolute_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim(get_setting('site_url'), '/') . '/' . ltrim($path, '/');
}

/** Geldige verificatiecode van Google Search Console (de content van de meta-tag). */
const GOOGLE_SITE_VERIFICATION_PATTERN = '/^[A-Za-z0-9_-]{10,100}$/';

/**
 * Normaliseert de verificatiecode van Google Search Console. Je mag ook de
 * hele <meta>-tag plakken; dan wordt alleen de waarde van content="…"
 * bewaard. Leeg mag (= geen verificatie-tag).
 *
 * @throws RuntimeException met een Nederlandse foutmelding
 */
function google_site_verification_normalize(string $value): string
{
    $value = trim($value);
    if (preg_match('/\bcontent\s*=\s*["\']([^"\']*)["\']/i', $value, $m)) {
        $value = trim($m[1]);
    }
    if ($value === '' || preg_match(GOOGLE_SITE_VERIFICATION_PATTERN, $value)) {
        return $value;
    }
    throw new RuntimeException('Ongeldige verificatiecode voor Google Search Console. Plak de HTML-tag die Google geeft, of alleen de waarde van content="…".');
}

/** De ingestelde Search Console-code, of '' als die leeg of ongeldig is. */
function google_site_verification(): string
{
    $code = trim(get_setting('google_site_verification'));
    return preg_match(GOOGLE_SITE_VERIFICATION_PATTERN, $code) ? $code : '';
}

/**
 * Structured data (schema.org) voor de organisatie, op de homepage. Type
 * NGO: een scoutinggroep is een vereniging/stichting zonder winstoogmerk,
 * geen sportclub. Lege velden en ongeldige social-links worden weggelaten.
 */
function organization_json_ld(): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'NGO',
        '@id' => organization_json_ld_id(),
        'name' => get_setting('org_naam'),
        'url' => get_setting('site_url'),
        'logo' => get_setting('logo_image') !== '' ? absolute_url(get_setting('logo_image')) : '',
        'image' => get_setting('og_image') !== '' ? absolute_url(get_setting('og_image')) : '',
        'email' => get_setting('org_email'),
        'telephone' => get_setting('org_telefoon'),
    ];
    $address = organization_address_json_ld();
    if ($address) {
        $data['address'] = $address;
    }
    $sameAs = [];
    foreach (['social_facebook', 'social_instagram', 'social_youtube'] as $key) {
        $url = trim(get_setting($key));
        if (preg_match('#^https?://[^/\s]+\.[^/\s]+/\S+#i', $url)) {
            $sameAs[] = $url;
        }
    }
    $data['sameAs'] = $sameAs;
    return array_filter($data, fn($v) => $v !== '' && $v !== []);
}

/**
 * Vaste @id van de organisatie in structured data, zodat andere pagina's
 * (bv. een speltak via parentOrganization) naar dezelfde entiteit verwijzen.
 */
function organization_json_ld_id(): string
{
    return absolute_url('') . '#organisatie';
}

/** PostalAddress van het clubgebouw, of [] als er geen adres is ingevuld. */
function organization_address_json_ld(): array
{
    $address = array_filter([
        'streetAddress' => get_setting('org_straat'),
        'postalCode' => get_setting('org_postcode'),
        'addressLocality' => get_setting('org_plaats'),
        'addressCountry' => get_setting('org_land'),
    ], fn($v) => $v !== '');
    return $address ? ['@type' => 'PostalAddress'] + $address : [];
}

/**
 * Leeftijdsgrenzen uit de vrije tekst van een speltak: "7–11 jaar" →
 * [7, 11], "18+ (leden)" → [18, null]. Onherkenbaar → [null, null].
 */
function speltak_age_range(?string $leeftijd): array
{
    $leeftijd = (string) $leeftijd;
    if (preg_match('/(\d{1,2})\s*(?:-|–|—|t\/m|tot)\s*(\d{1,2})/u', $leeftijd, $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    if (preg_match('/(\d{1,2})\s*\+/', $leeftijd, $m)) {
        return [(int) $m[1], null];
    }
    return [null, null];
}

/**
 * Structured data voor de pagina van een speltak: een Organization die
 * via parentOrganization bij de vereniging (organization_json_ld()) hoort,
 * met doelgroep (leeftijden), logo en het clubgebouw als locatie. Lege
 * velden worden weggelaten.
 */
function speltak_json_ld(array $sp): array
{
    $url = speltak_url($sp['slug'], true);
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $url . '#speltak',
        'name' => $sp['naam'] . ' — ' . get_setting('org_naam'),
        'alternateName' => $sp['naam'],
        'url' => $url,
        'description' => speltak_meta_description($sp),
        'logo' => !empty($sp['logo']) ? absolute_url($sp['logo']) : '',
        'parentOrganization' => [
            '@type' => 'NGO',
            '@id' => organization_json_ld_id(),
            'name' => get_setting('org_naam'),
            'url' => absolute_url(''),
        ],
    ];
    [$min, $max] = speltak_age_range($sp['leeftijd'] ?? null);
    if ($min !== null) {
        $data['audience'] = array_filter([
            '@type' => 'PeopleAudience',
            'suggestedMinAge' => $min,
            'suggestedMaxAge' => $max,
        ], fn($v) => $v !== null);
    }
    $address = organization_address_json_ld();
    if ($address) {
        $data['location'] = ['@type' => 'Place', 'name' => get_setting('org_naam'), 'address' => $address];
    }
    return array_filter($data, fn($v) => $v !== '' && $v !== []);
}

/**
 * Structured data voor een kruimelpad. $items: lijst van [naam, absolute URL],
 * van de homepage naar de huidige pagina.
 */
function breadcrumb_json_ld(array $items): array
{
    $list = [];
    foreach (array_values($items) as $i => [$name, $url]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/**
 * Zichtbaar kruimelpad. $items: lijst van [naam, href], van de homepage
 * naar de huidige pagina; het laatste item is de huidige pagina en wordt
 * geen link. Hoort bij breadcrumb_json_ld() met dezelfde items.
 */
function breadcrumbs_html(array $items): string
{
    $last = count($items) - 1;
    $html = '<nav class="breadcrumbs" aria-label="Kruimelpad"><ol class="breadcrumb">';
    foreach (array_values($items) as $i => [$name, $href]) {
        $html .= $i === $last
            ? '<li class="breadcrumb-item active" aria-current="page">' . e($name) . '</li>'
            : '<li class="breadcrumb-item"><a href="' . e($href) . '">' . e($name) . '</a></li>';
    }
    return $html . '</ol></nav>';
}

/**
 * <script type="application/ld+json">-blok. json_encode() zorgt voor geldige
 * JSON (aanhalingstekens, regeleinden, ongeldige UTF-8); JSON_HEX_TAG zet
 * < en > om, zodat een waarde met "</script>" het blok niet kan afsluiten.
 */
function json_ld_script(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Voegt loading="lazy" toe aan elke <img> in CMS-HTML die nog geen
 * loading-attribuut heeft, zodat afbeeldingen onder de vouw pas laden als
 * de bezoeker ernaartoe scrollt.
 */
function add_lazy_loading(?string $html): string
{
    $html = (string) $html;
    return preg_replace('/<(img)\b(?![^>]*\bloading\s*=)/i', '<$1 loading="lazy"', $html) ?? $html;
}

/* ------------------------------------------------------------------ *
 * Bestand-uploads (foto's / PDF's)
 * ------------------------------------------------------------------ */

/**
 * Verwerkt een geüploade file (uit $_FILES[$field]) en slaat deze veilig op.
 * Geeft de relatieve URL (bv. "assets/uploads/xxxx.pdf") terug, of null
 * als er geen (geldig) bestand is geüpload.
 *
 * @throws RuntimeException bij een ongeldig bestandstype of uploadfout.
 */
function handle_upload(string $field, array $allowedExtensions): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Uploaden van bestand is mislukt (foutcode ' . $file['error'] . ').');
    }
    // Streng zijn met de originele naam: geen verborgen bestanden (zoals
    // ".htaccess") en een extensie moet herkenbaar zijn. De opgeslagen naam
    // is sowieso willekeurig, maar zo komt zo'n bestand er nooit doorheen.
    $originalName = basename((string) $file['name']);
    if ($originalName === '' || $originalName[0] === '.') {
        throw new RuntimeException('Bestandsnamen die met een punt beginnen zijn niet toegestaan.');
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        throw new RuntimeException('Het bestandstype kon niet worden bepaald. Toegestaan: ' . implode(', ', $allowedExtensions));
    }
    if (!in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException('Bestandstype .' . e($ext) . ' is niet toegestaan. Toegestaan: ' . implode(', ', $allowedExtensions));
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Kon geüpload bestand niet opslaan.');
    }
    return rtrim(UPLOAD_URL, '/') . '/' . $safeName;
}

/**
 * Lijst van reeds geüploade bestanden in UPLOAD_DIR, optioneel gefilterd
 * op extensie. Gebruikt om een "kies bestaand bestand"-lijst te tonen bij
 * velden waar je anders een pad zou moeten typen.
 */
function get_uploaded_files(array $extensions = []): array
{
    $files = [];
    if (!is_dir(UPLOAD_DIR)) {
        return $files;
    }
    foreach (scandir(UPLOAD_DIR) as $entry) {
        // Verborgen bestanden (o.a. .htaccess, die scripts blokkeert) nooit tonen.
        if ($entry === '' || $entry[0] === '.') continue;
        $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if ($extensions && !in_array($ext, $extensions, true)) continue;
        $files[] = [
            'name' => $entry,
            'path' => rtrim(UPLOAD_URL, '/') . '/' . $entry,
        ];
    }
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $files;
}

/**
 * Zoekt uit waar een geüpload bestand wordt gebruikt (voor admin/uploads.php,
 * zodat een bestand dat nog in gebruik is niet verwijderd kan worden).
 *
 * Documenten (PDF's) slaan alleen de kale bestandsnaam op, dus daar volstaat
 * een exacte match. Afbeeldingen kunnen ook binnen een groter HTML-veld
 * voorkomen (alle CKEditor-velden: info-vakjes, speltak-toelichtingen,
 * pagina's en instellingen; via de upload-adapter als absolute URL) of met
 * een licht afwijkend pad zijn opgeslagen, dus daar wordt ook op een
 * LIKE-patroon met de bestandsnaam gezocht. Geüploade bestandsnamen zijn
 * altijd hexadecimaal (of eenvoudige seed-namen) en bevatten dus nooit
 * LIKE-jokertekens.
 */
function find_upload_usage(string $filename): array
{
    $used = [];
    $urlPath = rtrim(UPLOAD_URL, '/') . '/' . $filename;
    $likePattern = '%' . $filename . '%';

    $stmt = db()->prepare('SELECT naam FROM documents WHERE bestand = :f');
    $stmt->execute(['f' => $filename]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Document: ' . $row['naam'];
    }

    $stmt = db()->prepare(
        'SELECT titel FROM info_cards WHERE afbeelding = :f OR afbeelding LIKE :p1 OR tekst LIKE :p2'
    );
    $stmt->execute(['f' => $urlPath, 'p1' => $likePattern, 'p2' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Info-vakje: ' . $row['titel'];
    }

    $stmt = db()->prepare('SELECT naam FROM speltakken WHERE toelichting LIKE :p1 OR inhoud LIKE :p2 OR logo LIKE :p3');
    $stmt->execute(['p1' => $likePattern, 'p2' => $likePattern, 'p3' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Speltak: ' . $row['naam'];
    }

    $stmt = db()->prepare('SELECT titel FROM pages WHERE inhoud LIKE :p1 OR og_afbeelding LIKE :p2');
    $stmt->execute(['p1' => $likePattern, 'p2' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Pagina: ' . $row['titel'];
    }

    $stmt = db()->prepare(
        'SELECT setting_key FROM settings WHERE setting_value = :f OR setting_value LIKE :p'
    );
    $stmt->execute(['f' => $urlPath, 'p' => $likePattern]);
    foreach ($stmt->fetchAll() as $row) {
        $used[] = 'Instelling: ' . $row['setting_key'];
    }

    return array_values(array_unique($used));
}

/* ------------------------------------------------------------------ *
 * Sessies
 * ------------------------------------------------------------------ */

/** Eigen sessienaam i.p.v. PHP's standaard PHPSESSID. */
const SESSION_NAME = 'sv_session';
/** Uitloggen na zoveel seconden zonder activiteit in het beheerpaneel. */
const ADMIN_SESSION_IDLE_TIMEOUT = 2 * 3600;
/** Maximale duur van een login, ongeacht activiteit. */
const ADMIN_SESSION_MAX_LIFETIME = 12 * 3600;

/**
 * Of het huidige verzoek via HTTPS binnenkwam. Houdt ook rekening met
 * hosts/Cloudflare die TLS bij een proxy afhandelen en het verzoek als
 * gewone HTTP doorsturen (X-Forwarded-Proto).
 */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Cookie-instellingen voor de sessiecookie (ook gebruikt bij uitloggen). */
function session_cookie_params(): array
{
    return [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => request_is_https(), // niet op http://localhost (Docker)
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * Start de sessie met veilige instellingen. Gebruik dit overal in plaats
 * van een kale session_start(), zodat elk startpunt dezelfde cookie-
 * instellingen krijgt. Veilig om meerdere keren aan te roepen.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (!headers_sent()) {
        // Sommige hosts blokkeren ini_set (disable_functions) of deze
        // instellingen; dat mag nooit tot een fatale fout leiden.
        if (function_exists('ini_set')) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.use_trans_sid', '0');
            // Standaard ruimt PHP sessies al na 24 minuten op; laat ze
            // minstens zo lang bestaan als de inactiviteitstimeout.
            @ini_set('session.gc_maxlifetime', (string) ADMIN_SESSION_IDLE_TIMEOUT);
        }
        session_name(SESSION_NAME);
        session_set_cookie_params(session_cookie_params());
    }
    session_start();
}

/** Beëindigt de sessie volledig, inclusief het verlopen van de cookie. */
function destroy_session(): void
{
    start_secure_session();
    $_SESSION = [];
    if (!headers_sent()) {
        $params = session_cookie_params();
        unset($params['lifetime']);
        $params['expires'] = time() - 42000;
        setcookie(session_name(), '', $params);
    }
    session_destroy();
}

/* ------------------------------------------------------------------ *
 * CSRF-bescherming
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    start_secure_session();
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/* ------------------------------------------------------------------ *
 * Login-pogingen (brute-force bescherming voor admin/login.php)
 * ------------------------------------------------------------------ */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 30;
const LOGIN_ATTEMPT_RETENTION_MONTHS = 2;
/** Bewaartermijn van de inloggeschiedenis (admin_logins), zie purge_old_admin_logins(). */
const ADMIN_LOGIN_RETENTION_MONTHS = 2;

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Ruimt oude pogingen op. Wordt bij elk bezoek aan login.php aangeroepen
 * omdat er op shared hosting geen cron beschikbaar is om dit periodiek
 * te doen.
 *
 * Rekent met MySQL's eigen NOW() in plaats van PHP's tijd: op shared
 * hosting staat de PHP-tijdzone niet altijd gelijk aan de MySQL-tijdzone,
 * en attempted_at wordt met NOW() weggeschreven.
 */
function purge_old_login_attempts(): void
{
    $months = LOGIN_ATTEMPT_RETENTION_MONTHS;
    db()->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL {$months} MONTH)");
}

function record_failed_login(string $username, string $ip): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (:u, :ip, NOW())');
    $stmt->execute(['u' => $username, 'ip' => $ip]);
}

/** Reset de teller voor een gebruikersnaam na een geslaagde login. */
function clear_login_attempts(string $username): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = :u');
    $stmt->execute(['u' => $username]);
}

/**
 * Controleert of een gebruikersnaam en/of IP-adres momenteel geblokkeerd
 * is wegens LOGIN_MAX_ATTEMPTS of meer mislukte inlogpogingen binnen de
 * laatste LOGIN_LOCKOUT_MINUTES minuten. Geef een lege $username mee om
 * alleen op IP-adres te controleren (bv. voordat iemand iets heeft
 * ingevuld).
 *
 * Rekent met MySQL's eigen NOW() (zie purge_old_login_attempts) zodat een
 * afwijkende PHP-tijdzone op de hostingserver het niet kan verstoren.
 *
 * @return array{locked: bool, retry_after: int} retry_after in seconden
 */
function login_lockout_status(string $username, string $ip): array
{
    $minutes = LOGIN_LOCKOUT_MINUTES;
    $sql = "SELECT COUNT(*) AS aantal,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(attempted_at), INTERVAL {$minutes} MINUTE))) AS retry_after
            FROM login_attempts
            WHERE %s AND attempted_at > DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";

    $retryAfter = 0;

    if ($username !== '') {
        $stmt = db()->prepare(sprintf($sql, 'username = :u'));
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();
        if ((int) $row['aantal'] >= LOGIN_MAX_ATTEMPTS) {
            $retryAfter = max($retryAfter, (int) $row['retry_after']);
        }
    }

    $stmt = db()->prepare(sprintf($sql, 'ip_address = :ip'));
    $stmt->execute(['ip' => $ip]);
    $row = $stmt->fetch();
    if ((int) $row['aantal'] >= LOGIN_MAX_ATTEMPTS) {
        $retryAfter = max($retryAfter, (int) $row['retry_after']);
    }

    return ['locked' => $retryAfter > 0, 'retry_after' => $retryAfter];
}

/* ------------------------------------------------------------------ *
 * Wachtwoordeisen (voor beheerdersaccounts)
 * ------------------------------------------------------------------ */

const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_MIN_DIGITS = 2;
const PASSWORD_MIN_SPECIAL = 2;
const PASSWORD_REQUIREMENTS_TEXT = 'Minimaal 12 tekens, waarvan minimaal 2 cijfers en 2 speciale tekens (zoals ! ? # @ of een spatie).';

/**
 * Controleert een nieuw wachtwoord tegen de wachtwoordeisen. Geeft null
 * terug als het wachtwoord voldoet, anders een Nederlandse foutmelding.
 * Een "speciaal teken" is alles wat geen letter of cijfer is. Telt in
 * tekens i.p.v. bytes via PCRE's /u-modus (mbstring is niet op elke
 * shared host beschikbaar).
 */
function password_policy_error(string $password): ?string
{
    $length = preg_match_all('/./us', $password);
    $digits = preg_match_all('/\p{N}/u', $password);
    $special = preg_match_all('/[^\p{L}\p{N}]/u', $password);

    if ($length === false) {
        return 'Wachtwoord bevat ongeldige tekens.';
    }
    if ($length < PASSWORD_MIN_LENGTH || $digits < PASSWORD_MIN_DIGITS || $special < PASSWORD_MIN_SPECIAL) {
        return 'Wachtwoord voldoet niet aan de eisen: ' . lcfirst(PASSWORD_REQUIREMENTS_TEXT);
    }
    return null;
}

/* ------------------------------------------------------------------ *
 * Flash-meldingen (voor na een redirect in het admin-paneel)
 * ------------------------------------------------------------------ */

function flash_set(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    start_secure_session();
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
