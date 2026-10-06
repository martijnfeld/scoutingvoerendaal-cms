<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/version.php';

/** Veilig HTML-escapen voor output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
 * map met die naam in de websitemap staat (die gaat in .htaccess voor).
 */
const PAGE_RESERVED_SLUGS = ['admin', 'api', 'assets', 'backups', 'cron', 'docker', 'docs', 'includes', 'sql', 'tools'];

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
