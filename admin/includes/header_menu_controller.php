<?php
/** Alleen ingeladen door settings.php na require_login(). */
require_once __DIR__ . '/../../includes/header_menu.php';
require_once __DIR__ . '/header_menu_presentation.php';
$error = '';
$headerItems = header_menu_rows();
$headerReady = $headerItems !== null;
$headerItems = $headerItems ?? [];
$requestedId = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save'
    ? ($_POST['id'] ?? '') : ($_GET['edit'] ?? '0');
$menuId = is_string($requestedId) ? filter_var($requestedId, FILTER_VALIDATE_INT) : false;
$menuId = $menuId !== false && $menuId > 0 ? $menuId : 0;
$isHeaderEditor = $menuId > 0 || ($_GET['new'] ?? '') === '1'
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save');
$menuValues = ['label' => '', 'link_type' => 'section', 'page_id' => '', 'link_value' => 'top',
    'dropdown_type' => 'none', 'parent_id' => '', 'volgorde' => 1, 'actief' => 1];
$menuRecord = null;
foreach ($headerItems as $item) {
    if ((int) $item['id'] === $menuId) {
        $menuRecord = $item;
        $menuValues = $item;
    }
}
$menuCreationParentId = null;
$menuPlacementError = '';
if (!$menuId && array_key_exists('parent', $_GET)) {
    $requestedParent = is_string($_GET['parent']) ? filter_var($_GET['parent'], FILTER_VALIDATE_INT) : false;
    foreach ($headerItems as $parent) {
        if ((int) $parent['id'] === $requestedParent && $parent['parent_id'] === null && $parent['dropdown_type'] === 'manual') {
            $menuCreationParentId = $requestedParent;
        }
    }
    if ($menuCreationParentId === null) {
        $menuPlacementError = 'Dit handmatige submenu bestaat niet meer. Kies opnieuw Submenu-item toevoegen in het overzicht.';
        $error = $menuPlacementError;
        $isHeaderEditor = false;
    }
    $menuValues['parent_id'] = $menuCreationParentId;
} elseif ($menuId && !$menuRecord) {
    $error = 'Dit menu-item bestaat niet meer. Kies een item uit het overzicht.';
    $isHeaderEditor = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } elseif (!$headerReady) {
        $error = 'Voer eerst de databasemigratie uit via Systeem → Updates.';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            $id = is_string($_POST['id'] ?? null) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : false;
            if (!is_string($action) || $id === false || $id < 0) {
                throw new RuntimeException('Ongeldige menuactie.');
            }
            $input = $_POST;
            if ($action === 'save') {
                if ($menuPlacementError !== '') {
                    throw new RuntimeException($menuPlacementError);
                }
                $input['link_value'] = ($input['link_type'] ?? '') === 'section'
                    ? ($input['section_value'] ?? '') : ($input['url_value'] ?? '');
            }
            header_menu_mutate($action, $id, $input, ['parent_id' => $menuCreationParentId]);
            $messages = ['save' => 'Menu-item opgeslagen.', 'delete' => 'Menu-item verwijderd. De gekoppelde pagina is behouden.',
                'up' => 'Menu-item omhoog verplaatst.', 'down' => 'Menu-item omlaag verplaatst.', 'toggle' => 'Zichtbaarheid bijgewerkt.'];
            flash_set('success', $messages[$action]);
            header('Location: settings.php?tab=header', true, 303);
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception instanceof PDOException ? 'De wijziging kon niet worden opgeslagen. Ververs de pagina en probeer het opnieuw.' : $exception->getMessage();
            if (($_POST['action'] ?? '') === 'save') {
                $isHeaderEditor = $menuPlacementError === '';
                $postedValues = $input ?? $_POST;
                foreach (['label', 'link_type', 'page_id', 'link_value', 'dropdown_type'] as $key) {
                    if (isset($postedValues[$key]) && is_string($postedValues[$key])) {
                        $menuValues[$key] = $postedValues[$key];
                    }
                }
                $menuValues['actief'] = isset($_POST['actief']) ? 1 : 0;
            }
        }
    }
}
$headerPages = get_pages();
$headerPagesById = array_column($headerPages, null, 'id');
$headerSpeltakken = get_speltakken(true);
$headerGroups = header_menu_admin_groups($headerItems, $headerPagesById);

$menuParentLabel = '';
foreach ($headerItems as $parent) {
    if ($menuValues['parent_id'] !== null && $menuValues['parent_id'] !== '' && (int) $parent['id'] === (int) $menuValues['parent_id']) {
        $menuParentLabel = $parent['label'];
    }
}
$isMenuChild = $menuParentLabel !== '';
