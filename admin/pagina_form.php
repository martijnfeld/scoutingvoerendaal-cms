<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/page_definitions.php';
require_once __DIR__ . '/includes/settings_schema.php';
require_once __DIR__ . '/includes/settings_editor.php';

$definitions = page_editor_definitions();
$pageKey = $_GET['pagina'] ?? 'content';
$error = '';
$page = null;
$id = 0;
$status = 200;
if (!is_string($pageKey) || !isset($definitions[$pageKey])) {
    $status = 404;
    $error = 'Pagina niet gevonden.';
} elseif ($pageKey === 'content') {
    $requestedId = $_GET['id'] ?? $_POST['id'] ?? '0';
    if (!is_scalar($requestedId) || !ctype_digit((string) $requestedId)) {
        $status = 400;
        $error = 'Ongeldig pagina-ID.';
    } else {
        $id = (int) $requestedId;
        $page = $id ? get_page($id) : null;
        if ($id && !$page) {
            $status = 404;
            $error = 'Pagina niet gevonden.';
        }
    }
}
if ($status === 200) {
    $definition = page_editor_definition($pageKey, $page ?? []);
    $activeTab = $_GET['tab'] ?? array_key_first($definition['tabs']);
    if (!is_string($activeTab) || !isset($definition['tabs'][$activeTab])) {
        $status = 400;
        $error = 'Ongeldig tabblad.';
    }
}
if ($status !== 200) {
    http_response_code($status);
    $pageTitle = $error;
    $editorNavigation = ['public_url' => null, 'unavailable_text' => 'Open een pagina vanuit het paginaoverzicht.'];
    include __DIR__ . '/includes/layout_top.php';
    include __DIR__ . '/includes/page_editor_top.php';
    include __DIR__ . '/includes/page_editor_bottom.php';
    include __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$pageTitle = $pageKey === 'content' && !$id ? 'Nieuwe pagina' : $definition['titel'] . ' bewerken';
$editorNavigation = page_editor_navigation($definition, $activeTab);
$editorUrl = page_editor_url($pageKey, $activeTab, $definition['id'] ?? 0);
$canSave = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($canSave) {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } elseif (($_POST['tab'] ?? $activeTab) !== $activeTab) {
        $error = 'Ongeldig tabblad. Ververs de pagina en probeer het opnieuw.';
    } elseif (array_filter($_POST, function ($value) { return !is_string($value); })) {
        $error = 'Ongeldige veldwaarde. Ververs de pagina en probeer het opnieuw.';
    }
    $canSave = $error === '';
}

// Alleen de server-side configuratie bepaalt welke opslag en velden bereikbaar zijn.
switch ($definition['tabs'][$activeTab]['handler']) {
    case 'settings':
        $group = settings_schema()[$activeTab];
        if ($canSave) {
            $error = save_settings_group($group, $activeTab, $editorUrl);
        }
        $existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $editorView = 'settings_form.php';
        break;
    case 'page_record':
        include __DIR__ . '/includes/page_record_controller.php';
        $editorView = 'page_record_view.php';
        break;
    case 'info_cards':
        $cardAction = $_GET['actie'] ?? '';
        if (!is_string($cardAction) || !in_array($cardAction, ['', 'nieuw', 'bewerken'], true)) {
            header('Location: ' . page_editor_url('home', 'info_cards'), true, 303);
            exit;
        }
        if ($cardAction === '') {
            include __DIR__ . '/includes/info_cards_controller.php';
            $editorView = 'info_cards_view.php';
        } else {
            include __DIR__ . '/includes/info_card_form_controller.php';
            $editorView = 'info_card_form_view.php';
        }
        break;
}

include __DIR__ . '/includes/layout_top.php';
include __DIR__ . '/includes/page_editor_top.php';
?>
<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($pageKey === 'speltakken'): ?>
<p>Beheer namen, afbeeldingen en inhoud van individuele groepen via <a href="speltakken.php">Speltakken</a>.</p>
<?php endif; ?>
<?php include __DIR__ . '/includes/' . $editorView; ?>
<?php include __DIR__ . '/includes/page_editor_bottom.php'; ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
