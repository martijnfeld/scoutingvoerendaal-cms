<?php
/**
 * Eenmalige installatie.
 *
 * Gebruik:
 * 1. Upload alle bestanden naar je hosting.
 * 2. Kopieer config.local.php.example naar config.local.php en vul je
 *    databasegegevens in (zie INSTALL.md).
 * 3. Open dit bestand in je browser: het installeert zelf het
 *    databaseschema en laat je daarna je eerste beheerdersaccount aanmaken.
 * 4. Verwijder dit bestand daarna van de server (belangrijk!).
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/updater.php';

start_secure_session();

function install_page(string $title, string $bodyHtml): void
{
    ?>
    <!DOCTYPE html>
    <html lang="nl">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> — Installatie</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    </head>
    <body style="background:#1c2b22;">
    <div class="login-wrap" style="max-width:520px;">
      <h1><?= e($title) ?></h1>
      <?php echo $bodyHtml; ?>
    </div>
    </body></html>
    <?php
}

// -- Stap 1: databaseverbinding testen -----------------------------------
// Los van db() (die bij een connectiefout direct stopt met een generieke
// foutpagina) zodat we hier een duidelijke, bruikbare foutmelding kunnen
// tonen in plaats daarvan.
$connectionError = null;
try {
    $testPdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    $connectionError = $e;
}

if ($connectionError !== null) {
    install_page('Databaseverbinding ontbreekt', '
      <div class="admin-flash admin-flash-error">
        Kan geen verbinding maken met de database.
        ' . (APP_DEBUG ? '<br><br>' . e($connectionError->getMessage()) : '') . '
      </div>
      <p>Zo verhelp je dit:</p>
      <ol>
        <li>Maak in je hosting-controlepaneel een MySQL-database en -gebruiker aan (zie <code>INSTALL.md</code>).</li>
        <li>Kopieer <code>config.local.php.example</code> naar <code>config.local.php</code> en vul daar je
            databasegegevens in.</li>
        <li>Vernieuw deze pagina.</li>
      </ol>
    ');
    exit;
}

// -- Stap 2: databaseschema installeren (als het nog niet bestaat) -------
$schemaMissing = false;
try {
    $adminCount = (int) $testPdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (Throwable $e) {
    $schemaMissing = true;
    $adminCount = 0;
}

$schemaError = '';
if ($schemaMissing && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'install_schema') {
    if (!csrf_verify()) {
        $schemaError = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        try {
            $sql = file_get_contents(__DIR__ . '/sql/install.sql');
            if ($sql === false) {
                throw new RuntimeException('Kon sql/install.sql niet lezen.');
            }
            $testPdo->exec($sql);
            mark_all_migrations_applied();
            header('Location: install.php');
            exit;
        } catch (Throwable $e) {
            $schemaError = 'Installeren van de database is mislukt: ' . $e->getMessage();
        }
    }
}

if ($schemaMissing) {
    install_page('Database installeren', '
      ' . ($schemaError !== '' ? '<div class="admin-flash admin-flash-error">' . e($schemaError) . '</div>' : '') . '
      <p>De databaseverbinding werkt. De tabellen bestaan nog niet — klik hieronder om ze automatisch aan te maken en
      te vullen met de standaard startgegevens.</p>
      <form method="post">
        ' . csrf_field() . '
        <input type="hidden" name="step" value="install_schema">
        <button type="submit" class="btn" style="width:100%;">Database installeren</button>
      </form>
    ');
    exit;
}

// -- Stap 3: al geïnstalleerd? --------------------------------------------
if ($adminCount > 0) {
    install_page('Al geïnstalleerd', '
      <p>Er bestaat al een beheerdersaccount. Ga naar <a href="admin/login.php">/admin/login.php</a> om in te loggen.</p>
      <p><strong>Verwijder dit bestand (install.php) nu van de server</strong> om misbruik te voorkomen.</p>
    ');
    exit;
}

// -- Stap 4: eerste beheerdersaccount aanmaken ----------------------------
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'create_admin') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($username === '' || strlen($username) < 3) {
            $error = 'Kies een gebruikersnaam van minimaal 3 tekens.';
        } elseif ($policyError = password_policy_error($password)) {
            $error = $policyError;
        } elseif ($password !== $confirm) {
            $error = 'Wachtwoorden komen niet overeen.';
        } else {
            $stmt = $testPdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
            $stmt->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT)]);
            $success = true;
        }
    }
}

$body = '';
if ($success) {
    $body .= '
      <div class="admin-flash admin-flash-success">
        Beheerdersaccount aangemaakt! Je kunt nu inloggen op <a href="admin/login.php">/admin/login.php</a>.
      </div>
      <p><strong>Verwijder dit bestand (install.php) nu van de server</strong> — het is niet meer nodig en vormt een risico als het blijft staan.</p>
    ';
} else {
    $body .= ($error !== '' ? '<div class="admin-flash admin-flash-error">' . e($error) . '</div>' : '');
    $body .= '
      <p class="muted" style="font-size:0.85rem;">Database geïnstalleerd. Maak nu het eerste (en enige) beheerdersaccount aan waarmee je kunt inloggen op /admin.</p>
      <form method="post">
        ' . csrf_field() . '
        <input type="hidden" name="step" value="create_admin">
        <div class="field">
          <label for="username">Gebruikersnaam</label>
          <input type="text" id="username" name="username" required minlength="3" autofocus>
        </div>
        <div class="field">
          <label for="password">Wachtwoord</label>
          <input type="password" id="password" name="password" required minlength="' . PASSWORD_MIN_LENGTH . '">
          <span class="hint">' . e(PASSWORD_REQUIREMENTS_TEXT) . '</span>
        </div>
        <div class="field">
          <label for="confirm_password">Bevestig wachtwoord</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="' . PASSWORD_MIN_LENGTH . '">
        </div>
        <button type="submit" class="btn" style="width:100%;">Beheerder aanmaken</button>
      </form>
    ';
}

install_page('Beheerder aanmaken', $body);
