<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Accounts';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $total = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();

    if ($action === 'delete' && $id) {
        if ($id === (int) $_SESSION['admin_id']) {
            flash_set('error', 'Je kunt je eigen account niet verwijderen terwijl je bent ingelogd.');
        } elseif ($total <= 1) {
            flash_set('error', 'Je kunt het laatste beheerdersaccount niet verwijderen.');
        } else {
            db()->prepare('DELETE FROM admin_users WHERE id = :id')->execute(['id' => $id]);
            flash_set('success', 'Account verwijderd.');
        }
        header('Location: accounts.php');
        exit;
    }

    if ($action === 'reset_password' && $id) {
        $newPassword = $_POST['new_password'] ?? '';
        if ($policyError = password_policy_error($newPassword)) {
            flash_set('error', $policyError);
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
            $stmt->execute(['h' => $newHash, 'id' => $id]);
            // Alle sessies van dit account worden hierdoor uitgelogd (zie
            // admin_session_check_expired()); behalve je eigen huidige
            // sessie als je je eigen wachtwoord hier instelt.
            if ($id === (int) $_SESSION['admin_id']) {
                $loginAt = $_SESSION['admin_login_at'];
                admin_session_login([
                    'id' => $id,
                    'username' => current_admin_username(),
                    'password_hash' => $newHash,
                ]);
                $_SESSION['admin_login_at'] = $loginAt; // maximale sessieduur niet verlengen
            }
            flash_set('success', 'Wachtwoord van dit account is gewijzigd. Eventuele andere sessies van dit account zijn uitgelogd.');
        }
        header('Location: accounts.php');
        exit;
    }

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3) {
            $error = 'Kies een gebruikersnaam van minimaal 3 tekens.';
        } elseif ($policyError = password_policy_error($password)) {
            $error = $policyError;
        } elseif ($password !== $confirm) {
            $error = 'Wachtwoorden komen niet overeen.';
        } else {
            $exists = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u');
            $exists->execute(['u' => $username]);
            if ((int) $exists->fetchColumn() > 0) {
                $error = 'Er bestaat al een account met deze gebruikersnaam.';
            } else {
                $stmt = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
                $stmt->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT)]);
                flash_set('success', 'Account aangemaakt.');
                header('Location: accounts.php');
                exit;
            }
        }
    }
}

$accounts = db()->query('SELECT * FROM admin_users ORDER BY username ASC')->fetchAll();

$historyPerPage = 50;
$historyPage = max(1, (int) ($_GET['p'] ?? 1));
$history = admin_login_history($historyPerPage, ($historyPage - 1) * $historyPerPage);
$historyPages = max(1, (int) ceil($history['total'] / $historyPerPage));

include __DIR__ . '/includes/layout_top.php';
?>
<p>Beheer hier wie kan inloggen op dit beheerpaneel.</p>

<div class="admin-card">
  <h2>Bestaande accounts</h2>
  <table class="admin-table">
    <tr><th>Gebruikersnaam</th><th>Aangemaakt</th><th>Laatste login</th><th>Acties</th></tr>
    <?php foreach ($accounts as $account): $isSelf = (int) $account['id'] === (int) $_SESSION['admin_id']; ?>
    <tr>
      <td><strong><?= e($account['username']) ?></strong><?= $isSelf ? ' <span class="muted">(jij)</span>' : '' ?></td>
      <td><?= e($account['aangemaakt']) ?></td>
      <td><?= isset($history['last'][$account['id']]) ? e($history['last'][$account['id']]) : '<span class="muted">niet in de laatste ' . ADMIN_LOGIN_RETENTION_MONTHS . ' maanden</span>' ?></td>
      <td class="actions">
        <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="id" value="<?= $account['id'] ?>">
          <input type="password" name="new_password" placeholder="nieuw wachtwoord" minlength="<?= PASSWORD_MIN_LENGTH ?>" title="<?= e(PASSWORD_REQUIREMENTS_TEXT) ?>" required style="padding:6px 8px; border:1px solid #ccc; border-radius:6px; font-size:0.82rem;">
          <button type="submit" class="btn btn-secondary btn-small">Wachtwoord instellen</button>
        </form>
        <?php if (!$isSelf && count($accounts) > 1): ?>
        <form method="post" data-confirm="Account &quot;<?= e($account['username']) ?>&quot; verwijderen?" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $account['id'] ?>">
          <button type="submit" class="btn btn-danger btn-small">Verwijderen</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="admin-card" id="inloggeschiedenis">
  <h2>Inloggeschiedenis</h2>
  <?php if (!$history['rows']): ?>
  <p class="muted">Nog geen logins vastgelegd.</p>
  <?php else: ?>
  <p class="muted"><?= $history['total'] ?> geslaagde login<?= $history['total'] === 1 ? '' : 's' ?> in de laatste <?= ADMIN_LOGIN_RETENTION_MONTHS ?> maanden, nieuwste eerst. Oudere logins worden automatisch verwijderd.</p>
  <table class="admin-table">
    <tr><th>Tijdstip</th><th>Gebruikersnaam</th><th>IP-adres</th><th>Browser</th></tr>
    <?php foreach ($history['rows'] as $login): ?>
    <tr>
      <td><?= e($login['ingelogd_op']) ?></td>
      <td><?= e($login['username']) ?></td>
      <td><?= e($login['ip_address']) ?></td>
      <td class="muted" style="font-size:0.82rem;"><?= e($login['user_agent']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($historyPages > 1): ?>
  <p>
    <?php if ($historyPage > 1): ?><a href="accounts.php?p=<?= $historyPage - 1 ?>#inloggeschiedenis">&laquo; Nieuwer</a><?php endif; ?>
    <span class="muted">Pagina <?= $historyPage ?> van <?= $historyPages ?></span>
    <?php if ($historyPage < $historyPages): ?><a href="accounts.php?p=<?= $historyPage + 1 ?>#inloggeschiedenis">Ouder &raquo;</a><?php endif; ?>
  </p>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="admin-card" style="max-width:480px;">
  <h2>Nieuw account aanmaken</h2>
  <?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field">
      <label for="username">Gebruikersnaam</label>
      <input type="text" id="username" name="username" required minlength="3" value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Wachtwoord</label>
      <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
      <span class="hint"><?= e(PASSWORD_REQUIREMENTS_TEXT) ?></span>
    </div>
    <div class="field">
      <label for="confirm_password">Bevestig wachtwoord</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
    </div>
    <button type="submit" class="btn">Account aanmaken</button>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
