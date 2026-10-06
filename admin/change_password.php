<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Wachtwoord wijzigen';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $error = 'Huidig wachtwoord is onjuist.';
        } elseif ($policyError = password_policy_error($new)) {
            $error = $policyError;
        } elseif ($new !== $confirm) {
            $error = 'De bevestiging komt niet overeen met het nieuwe wachtwoord.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $update = db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
            $update->execute(['h' => $newHash, 'id' => $user['id']]);
            // Nieuw sessie-id + nieuwe vingerafdruk: deze sessie blijft
            // ingelogd, andere sessies van dit account worden uitgelogd.
            $user['password_hash'] = $newHash;
            admin_session_login($user);
            flash_set('success', 'Je wachtwoord is gewijzigd.');
            header('Location: change_password.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-card" style="max-width:420px;">
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label for="current_password">Huidig wachtwoord</label>
      <input type="password" id="current_password" name="current_password" required>
    </div>
    <div class="field">
      <label for="new_password">Nieuw wachtwoord</label>
      <input type="password" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
      <span class="hint"><?= e(PASSWORD_REQUIREMENTS_TEXT) ?></span>
    </div>
    <div class="field">
      <label for="confirm_password">Bevestig nieuw wachtwoord</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
    </div>
    <button type="submit" class="btn">Wachtwoord wijzigen</button>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
