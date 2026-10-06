<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/backup.php';

$name = basename((string) ($_GET['name'] ?? ''));
// Alleen .zip-bestanden uit de bekende back-upmappen (zie backup_known_dirs()).
$path = find_backup_path($name);

if ($path === null) {
    http_response_code(404);
    exit('Back-up niet gevonden.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
