<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/settings_schema.php';
require_once __DIR__ . '/includes/settings_editor.php';
require_once __DIR__ . '/includes/page_definitions.php';

$pageTitle = 'Instellingen';
$requestedTab = $_GET['tab'] ?? 'algemeen';
$legacyUrl = is_string($requestedTab) ? legacy_settings_url($requestedTab) : null;
if ($legacyUrl !== null) {
    header('Location: ' . $legacyUrl, true, 303);
    exit;
}
$schema = system_settings_schema();
$activeTab = is_string($requestedTab) && isset($schema[$requestedTab]) ? $requestedTab : 'algemeen';
$group = $schema[$activeTab];
$error = $_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($requestedTab) || !isset($schema[$requestedTab]))
    ? 'Ongeldig tabblad. Ververs de pagina en probeer het opnieuw.'
    : save_settings_group($group, $activeTab, 'settings.php?tab=' . urlencode($activeTab));
$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);
include __DIR__ . '/includes/layout_top.php';
?>
<nav class="tabs" aria-label="Instellingengroepen">
  <?php foreach ($schema as $key => $config): ?>
  <a href="settings.php?tab=<?= e($key) ?>" class="<?= $key === $activeTab ? 'active' : '' ?>"<?= $key === $activeTab ? ' aria-current="page"' : '' ?>><?= e($config['label']) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
<?php include __DIR__ . '/includes/settings_form.php'; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
