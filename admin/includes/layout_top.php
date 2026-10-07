<?php
/**
 * Bovenkant van elke admin-pagina.
 * Verwacht (optioneel) $pageTitle vooraf gedefinieerd.
 */
$pageTitle = $pageTitle ?? 'Beheer';
$flash = flash_get();
$adminPage = basename($_SERVER['PHP_SELF']);
$overviewNavigation = ['label' => 'Overzicht', 'href' => 'index.php', 'icon' => 'layout-dashboard', 'pages' => ['index.php']];
$navigationGroups = [
    'Inhoud' => [
        ['label' => 'Website-instellingen', 'href' => 'settings.php', 'icon' => 'settings', 'pages' => ['settings.php']],
        ['label' => "Pagina's", 'href' => 'paginas.php', 'icon' => 'file-text', 'pages' => ['paginas.php', 'pagina_form.php']],
        ['label' => 'Speltakken', 'href' => 'speltakken.php', 'icon' => 'sitemap', 'pages' => ['speltakken.php', 'speltak_form.php']],
        ['label' => 'Info-vakjes', 'href' => 'info_cards.php', 'icon' => 'layout-cards', 'pages' => ['info_cards.php', 'info_card_form.php']],
        ['label' => 'Documenten', 'href' => 'documents.php', 'icon' => 'files', 'pages' => ['documents.php', 'document_form.php']],
        ['label' => 'Bestanden', 'href' => 'uploads.php', 'icon' => 'cloud-upload', 'pages' => ['uploads.php']],
    ],
    'Systeem' => [
        ['label' => 'Systeemcontrole', 'href' => 'controle.php', 'icon' => 'checklist', 'pages' => ['controle.php']],
        ['label' => 'Back-ups', 'href' => 'backups.php', 'icon' => 'database', 'pages' => ['backups.php', 'backup_download.php']],
        ['label' => 'Updates', 'href' => 'updates.php', 'icon' => 'refresh', 'pages' => ['updates.php']],
        ['label' => 'Accounts', 'href' => 'accounts.php', 'icon' => 'users', 'pages' => ['accounts.php', 'account_form.php']],
    ],
];
$userNavigation = [
    ['label' => 'Wachtwoord wijzigen', 'href' => 'change_password.php', 'icon' => 'key', 'pages' => ['change_password.php']],
];
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-header">
      <a class="admin-brand" href="index.php">
        <img class="admin-scouting-logo" src="../assets/img/scouting-nederland-logo.svg" alt="Scouting Nederland">
        <span class="admin-brand-copy"><?= e(get_setting('org_naam', 'Scouting')) ?><small>Beheer</small></span>
      </a>
      <div class="dropdown admin-user-menu">
        <button class="admin-user-trigger dropdown-toggle" type="button" id="adminUserMenu" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
          <span class="admin-user-avatar"><?= fa_icon('user') ?></span>
          <span class="admin-user-name" title="<?= e(current_admin_username()) ?>"><?= e(current_admin_username()) ?></span>
        </button>
        <div class="dropdown-menu admin-user-dropdown" aria-labelledby="adminUserMenu">
          <?php foreach ($userNavigation as $item): $isActive = in_array($adminPage, $item['pages'], true); ?>
          <a href="<?= e($item['href']) ?>" class="admin-user-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
            <?= fa_icon($item['icon']) ?><span><?= e($item['label']) ?></span>
          </a>
          <?php endforeach; ?>
          <form method="post" action="logout.php" class="logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="logout-link"><?= fa_icon('logout') ?><span>Uitloggen</span></button>
          </form>
        </div>
      </div>
    </div>
    <button class="admin-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false">
      <?= fa_icon('menu-2') ?><span>Menu</span><?= fa_icon('chevron-down', 'admin-nav-chevron') ?>
    </button>
    <nav id="adminNavigation" class="admin-navigation collapse" aria-label="Hoofdnavigatie">
      <?php $isActive = in_array($adminPage, $overviewNavigation['pages'], true); ?>
      <a href="<?= e($overviewNavigation['href']) ?>" class="admin-nav-link admin-nav-overview<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <?= fa_icon($overviewNavigation['icon']) ?><span><?= e($overviewNavigation['label']) ?></span>
      </a>
      <?php foreach ($navigationGroups as $groupLabel => $items): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label"><?= e($groupLabel) ?></div>
        <?php foreach ($items as $item): $isActive = in_array($adminPage, $item['pages'], true); ?>
        <a href="<?= e($item['href']) ?>" class="admin-nav-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <?= fa_icon($item['icon']) ?><span><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="admin-main">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($flash): ?>
    <div class="admin-flash admin-flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
