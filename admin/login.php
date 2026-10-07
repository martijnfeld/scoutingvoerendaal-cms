<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/geoip.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

purge_old_login_attempts();
purge_old_admin_logins();

$ip = client_ip();
$error = '';
$locked = false;
$adminCount = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();

if (!admin_login_allowed_from_ip($ip)) {
    // Geen formulier tonen en ook een POST niet verwerken: zo kan er vanaf
    // buiten Europa niet eens een wachtwoord geprobeerd worden.
    http_response_code(403);
    $locked = true;
    $error = 'Inloggen op het beheerpaneel is alleen mogelijk vanuit Europa.';
} elseif ($adminCount === 0) {
    $error = 'Er is nog geen beheerdersaccount aangemaakt. Open eerst install.php in je browser.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $lockout = login_lockout_status($username, $ip);
    if ($lockout['locked']) {
        $locked = true;
        $error = 'Te veel mislukte inlogpogingen. Probeer het over ' . (int) ceil($lockout['retry_after'] / 60) . ' minuten opnieuw.';
    } elseif (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            clear_login_attempts($username);
            admin_session_login($user);
            admin_record_login($user, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');
            header('Location: index.php');
            exit;
        }
        record_failed_login($username, $ip);
        $error = 'Onjuiste gebruikersnaam of wachtwoord.';
    }
} else {
    $lockout = login_lockout_status('', $ip);
    if ($lockout['locked']) {
        $locked = true;
        $error = 'Te veel mislukte inlogpogingen vanaf dit IP-adres. Probeer het over ' . (int) ceil($lockout['retry_after'] / 60) . ' minuten opnieuw.';
    } elseif (isset($_GET['verlopen'])) {
        // Doorgestuurd door require_login() na een sessietimeout.
        $error = 'Je sessie is verlopen, log opnieuw in.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Inloggen — Beheer</title>
<link rel="stylesheet" href="<?= e(asset_url('../assets/css/admin.css')) ?>">
</head>
<body style="background:#1c2b22;">
<div class="login-wrap">
  <h1>Beheer inloggen</h1>
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($adminCount > 0 && !$locked): ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Gebruikersnaam</label>
      <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Wachtwoord</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn" style="width:100%;">Inloggen</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
