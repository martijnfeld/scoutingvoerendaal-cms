<?php
/**
 * Bovenkant van elke admin-pagina.
 * Verwacht (optioneel) $pageTitle vooraf gedefinieerd.
 */
$pageTitle = $pageTitle ?? 'Beheer';
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> — Beheer <?= e(get_setting('org_naam', 'Scouting')) ?></title>
<?php if (get_setting('logo_image')): ?>
<link rel="icon" type="image/png" href="../<?= e(get_setting('logo_image')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand"><?= e(get_setting('org_naam', 'Scouting')) ?><br><small>Beheer</small></div>
    <nav>
      <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Dashboard</a>
      <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">Teksten &amp; gegevens</a>
      <a href="speltakken.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['speltakken.php','speltak_form.php']) ? 'active' : '' ?>">Speltakken</a>
      <a href="info_cards.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['info_cards.php','info_card_form.php']) ? 'active' : '' ?>">Info-vakjes</a>
      <a href="paginas.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['paginas.php','pagina_form.php']) ? 'active' : '' ?>">Pagina's</a>
      <a href="documents.php" class="<?= basename($_SERVER['PHP_SELF']) === 'documents.php' ? 'active' : '' ?>">Documenten (PDF's)</a>
      <a href="uploads.php" class="<?= basename($_SERVER['PHP_SELF']) === 'uploads.php' ? 'active' : '' ?>">Geüploade bestanden</a>
      <a href="backups.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['backups.php','backup_download.php']) ? 'active' : '' ?>">Back-ups</a>
      <a href="updates.php" class="<?= basename($_SERVER['PHP_SELF']) === 'updates.php' ? 'active' : '' ?>">Updates</a>
      <a href="controle.php" class="<?= basename($_SERVER['PHP_SELF']) === 'controle.php' ? 'active' : '' ?>">Systeemcontrole</a>
      <a href="accounts.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['accounts.php','account_form.php']) ? 'active' : '' ?>">Accounts</a>
      <a href="change_password.php" class="<?= basename($_SERVER['PHP_SELF']) === 'change_password.php' ? 'active' : '' ?>">Mijn wachtwoord</a>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="../index.php" target="_blank"><i class="fa-solid fa-arrow-left"></i> Bekijk website</a>
      <form method="post" action="logout.php" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="logout-link">Uitloggen (<?= e(current_admin_username()) ?>)</button>
      </form>
    </div>
  </aside>
  <main class="admin-main">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($flash): ?>
    <div class="admin-flash admin-flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
