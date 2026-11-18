<?php
// Compatibiliteit voor oude bookmarks; alle editors staan in pagina_form.php.
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/page_definitions.php';

$page = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : '';
$url = legacy_page_editor_url($page, $_GET['tab'] ?? null);
if ($url === null) {
    http_response_code(isset(page_editor_definitions()[$page]) ? 400 : 404);
    $pageTitle = 'Pagina of tabblad niet gevonden';
    include __DIR__ . '/includes/layout_top.php';
    echo '<p><a href="paginas.php">Terug naar paginaoverzicht</a></p>';
    include __DIR__ . '/includes/layout_bottom.php';
    exit;
}
if ($page === 'home' && ($_GET['tab'] ?? '') === 'info_cards') {
    $action = $_GET['actie'] ?? '';
    if (is_string($action) && in_array($action, ['nieuw', 'bewerken'], true)) {
        $url .= '&actie=' . $action;
        $requestedId = $_GET['id'] ?? $_POST['id'] ?? 0;
        if (is_scalar($requestedId) && (int) $requestedId > 0) {
            $url .= '&id=' . (int) $requestedId;
        }
    }
}
header('Location: ' . $url, true, 303);
exit;
