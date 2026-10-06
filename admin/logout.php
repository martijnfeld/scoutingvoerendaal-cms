<?php
/**
 * Uitloggen. Alleen via POST met geldig CSRF-token (formulier in de
 * zijbalk, zie includes/layout_top.php), zodat een andere site je niet
 * met een simpele link of <img> kan uitloggen. Een GET stuurt alleen door.
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: ' . (empty($_SESSION['admin_id']) ? 'login.php' : 'index.php'));
    exit;
}

destroy_session();
header('Location: login.php');
exit;
