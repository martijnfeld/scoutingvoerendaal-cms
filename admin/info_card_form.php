<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/page_definitions.php';

// Oude bookmarks openen het formulier binnen Home, met behoud van het kaart-ID.
$requestedId = $_GET['id'] ?? $_POST['id'] ?? 0;
$id = is_scalar($requestedId) ? (int) $requestedId : 0;
$url = page_editor_url('home', 'info_cards') . ($id ? '&actie=bewerken&id=' . $id : '&actie=nieuw');
header('Location: ' . $url, true, 303);
exit;
