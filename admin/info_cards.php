<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
// Oude bookmarks openen voortaan het kaartbeheer binnen Home.
require_once __DIR__ . '/includes/page_definitions.php';
header('Location: ' . page_editor_url('home', 'info_cards'), true, 303);
exit;
