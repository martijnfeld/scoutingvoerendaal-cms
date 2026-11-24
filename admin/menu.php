<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/header_menu_controller.php';

$pageTitle = 'Menu';
$adminMainClass = 'header-settings-main';
include __DIR__ . '/includes/layout_top.php';
?>
<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
<?php include __DIR__ . '/includes/header_menu_view.php'; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
