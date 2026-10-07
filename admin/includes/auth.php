<?php
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();

// Content-Security-Policy voor het beheerpaneel: alleen scripts van onze
// eigen site en de vaste CDN's, geen inline-scripts of event-handlers
// (bevestigingsvragen lopen via data-confirm in assets/js/admin.js).
// Inline styles en blob:-afbeeldingen zijn nodig voor CKEditor.
if (!headers_sent()) {
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "script-src 'self' https://cdn.ckeditor.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "font-src 'self' https://cdnjs.cloudflare.com data:",
        "img-src 'self' https: data: blob:",
        "media-src 'self' https:",
        "frame-src https:",
        // CDN alleen voor de source map van CKEditor (opgevraagd door DevTools).
        "connect-src 'self' https://cdn.ckeditor.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]));
}

/**
 * Korte vingerafdruk van een wachtwoord-hash. Wordt bij het inloggen in de
 * sessie gezet; verandert het wachtwoord (zelf gewijzigd of door een andere
 * beheerder gereset), dan klopt hij niet meer en worden alle andere sessies
 * van dat account bij hun volgende verzoek uitgelogd.
 */
function admin_password_fingerprint(string $passwordHash): string
{
    return substr(hash('sha256', $passwordHash), 0, 16);
}

/** Markeert de sessie als ingelogd voor $user (rij uit admin_users). */
function admin_session_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_pw_fp'] = admin_password_fingerprint($user['password_hash']);
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_activity'] = time();
}

/**
 * Controleert bij elk verzoek of een ingelogde sessie nog geldig is:
 * niet te lang inactief, niet te oud, en het account bestaat nog met
 * hetzelfde wachtwoord. Zo niet, dan wordt de sessie leeggemaakt (met een
 * nieuw sessie-id) en geeft deze functie true terug.
 *
 * Draait direct bij het laden van dit bestand, zodat ook endpoints die zelf
 * $_SESSION['admin_id'] controleren (bv. upload_image.php) dit meekrijgen.
 */
function admin_session_check_expired(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    $now = time();
    $loginAt = (int) ($_SESSION['admin_login_at'] ?? 0);
    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);

    $valid = $loginAt > 0
        && $now - $lastActivity <= ADMIN_SESSION_IDLE_TIMEOUT
        && $now - $loginAt <= ADMIN_SESSION_MAX_LIFETIME;

    if ($valid) {
        $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => (int) $_SESSION['admin_id']]);
        $hash = $stmt->fetchColumn();
        $valid = $hash !== false
            && hash_equals(admin_password_fingerprint($hash), (string) ($_SESSION['admin_pw_fp'] ?? ''));
    }

    if (!$valid) {
        $_SESSION = [];
        session_regenerate_id(true);
        return true;
    }
    $_SESSION['admin_last_activity'] = $now;
    return false;
}

$adminSessionExpired = admin_session_check_expired();

function require_login(): void
{
    global $adminSessionExpired;
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php' . ($adminSessionExpired ? '?verlopen=1' : ''));
        exit;
    }
}

function current_admin_username(): string
{
    return $_SESSION['admin_username'] ?? '';
}
