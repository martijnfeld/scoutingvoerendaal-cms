<?php
/** Centrale navigatie: alleen ophalen en resolveren, nooit seeden tijdens een paginaverzoek. */
const HEADER_LINK_TYPES = ['section' => 'Homepage-sectie', 'page' => 'Pagina', 'programma' => 'Programmapagina', 'speltakken' => 'Speltakkenoverzicht', 'url' => 'Eigen URL'];
const HEADER_DROPDOWN_TYPES = ['none' => 'Geen dropdown', 'manual' => 'Handmatig submenu', 'programme' => 'Automatisch programma per speltak', 'speltakken' => 'Automatisch speltakpagina’s'];
const HEADER_SECTIONS = ['top' => 'Home', 'programma' => 'Programma', 'lidworden' => 'Lid worden', 'verhuur' => 'Verhuur', 'contact' => 'Contact'];

/** Vaste SVG-iconen voor de header en het menuoverzicht, zonder externe iconfont. */
function header_menu_icon(string $name): string
{
    $icons = [
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15v5Z"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="m3 3 18 18M10.6 5.1 12 5c7 0 10 7 10 7a20 20 0 0 1-3 4M6 6a19 19 0 0 0-4 6s3 7 10 7a12 12 0 0 0 5-1M10 10a3 3 0 0 0 4 4"/>',
        'arrow-up' => '<path d="M12 19V5m-6 6 6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14m-6-6 6 6 6-6"/>',
        'link' => '<path d="m10 13 4-4m-6 6-2 2a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m0 2 2-2a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0" transform="translate(2 0)"/>',
        'submenu' => '<path d="M5 3v10h14m-5-5 5 5-5 5"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    if (!isset($icons[$name])) {
        return '';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $icons[$name] . '</svg>';
}

/** Alleen een ontbrekende tabel wordt als verwachte toestand vóór de migratie behandeld. */
function header_menu_missing_table(PDOException $exception): bool
{
    return (int) ($exception->errorInfo[1] ?? 0) === 1146;
}

function header_menu_rows(): ?array
{
    try {
        $rows = db()->query('SELECT h.*, p.slug AS page_slug, p.actief AS page_active
            FROM header_menu_items h LEFT JOIN pages p ON p.id = h.page_id
            ORDER BY h.volgorde ASC, h.id ASC')->fetchAll();
        // Ook tussen DDL en de transactionele dataoverdracht blijft de oude header beschikbaar.
        if (!$rows && db()->query("SELECT setting_value FROM settings WHERE setting_key = 'header_menu_initialized'")->fetchColumn() !== '1') {
            return null;
        }
        return $rows;
    } catch (PDOException $exception) {
        if (!header_menu_missing_table($exception)) {
            throw $exception;
        }
        return null;
    }
}

/** Eigen URL: http(s), mailto, tel of een pad binnen de installatie. Geen protocolrelatieve URL. */
function header_menu_safe_url(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        return false;
    }
    // Ook gecodeerde besturingstekens en backslashes weigeren.
    if (preg_match('/[\x00-\x20\x7f\\\\]/', rawurldecode($url))) {
        return false;
    }
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && !parse_url($url, PHP_URL_USER) && !parse_url($url, PHP_URL_PASS);
    }
    if (preg_match('/^mailto:([^?]+)$/i', $url, $match)) {
        return filter_var($match[1], FILTER_VALIDATE_EMAIL) !== false;
    }
    if (preg_match('/^tel:\+?[0-9().-]+$/i', $url)) {
        return true;
    }
    return (bool) preg_match('~^(?:\./|/|\#)?[a-zA-Z0-9_-][a-zA-Z0-9_./?=&%+\~#@!-]*$~D', $url)
        && strpos($url, '..') === false && strpos($url, ':') === false;
}

function header_menu_url(array $item, string $homeHref): ?string
{
    switch ($item['link_type']) {
        case 'programma':
            return programma_url();
        case 'section':
            // Oude menukoppelingen blijven ook tijdens een update bruikbaar.
            if ($item['link_value'] === 'programma') { return programma_url(); }
            return isset(HEADER_SECTIONS[$item['link_value']]) ? $homeHref . '#' . $item['link_value'] : null;
        case 'page':
            return !empty($item['page_active']) && !empty($item['page_slug']) ? page_url($item['page_slug']) : null;
        case 'speltakken':
            return speltakken_page_active() ? speltakken_url() : null;
        case 'url':
            if (!header_menu_safe_url($item['link_value'])) { return null; }
            if (strpos($item['link_value'], '#') === 0) { return $homeHref . $item['link_value']; }
            return strpos($item['link_value'], '/') === 0 ? './' . ltrim($item['link_value'], '/') : $item['link_value'];
    }
    return null;
}

/** Oude navigatie als tijdelijke, uitsluitend lezende fallback vóór migratie 0009. */
function header_menu_legacy_rows(): array
{
    $rows = [];
    foreach (HEADER_SECTIONS as $section => $label) {
        $rows[] = ['id' => -count($rows) - 1, 'parent_id' => null, 'label' => $label,
            'link_type' => 'section', 'link_value' => $section, 'dropdown_type' => 'none', 'actief' => 1];
        if ($section === 'programma') {
            $rows[] = ['id' => -99, 'parent_id' => null, 'label' => 'Speltakken',
                'link_type' => 'speltakken', 'link_value' => '', 'dropdown_type' => 'none', 'actief' => 1];
        }
    }
    foreach (get_pages_in_menu() as $page) {
        $rows[] = ['id' => $page['id'], 'parent_id' => null, 'label' => $page['titel'],
            'link_type' => 'page', 'page_slug' => $page['slug'], 'page_active' => $page['actief'],
            'link_value' => '', 'dropdown_type' => 'none', 'actief' => 1];
    }
    return $rows;
}

/** Twee queries ongeacht het aantal items: gekoppelde pagina's via JOIN, speltakken één keer. */
function header_menu_tree(string $homeHref, string $currentPageSlug = '', string $activeNav = ''): array
{
    $rows = header_menu_rows();
    $rows = $rows === null ? header_menu_legacy_rows() : $rows;
    $speltakken = null;
    $tree = [];
    foreach ($rows as $row) {
        if ($row['parent_id'] !== null || !$row['actief']) {
            continue;
        }
        $url = header_menu_url($row, $homeHref);
        if ($url === null) {
            continue;
        }
        $row['url'] = $url;
        $row['active'] = ($row['link_type'] === 'page' && $currentPageSlug !== '' && $currentPageSlug === ($row['page_slug'] ?? ''))
            || ($row['link_type'] === 'speltakken' && $activeNav === 'speltakken')
            || ($activeNav === 'programma' && ($row['link_type'] === 'programma'
                || ($row['link_type'] === 'section' && $row['link_value'] === 'programma')));
        $row['children'] = [];
        if ($row['dropdown_type'] === 'manual') {
            foreach ($rows as $child) {
                if ((string) $child['parent_id'] !== (string) $row['id'] || !$child['actief']) {
                    continue;
                }
                $childUrl = header_menu_url($child, $homeHref);
                if ($childUrl !== null) {
                    $row['children'][] = ['label' => $child['label'], 'url' => $childUrl];
                    if ($child['link_type'] === 'page' && $currentPageSlug !== '' && ($child['page_slug'] ?? '') === $currentPageSlug) {
                        $row['active'] = true;
                    }
                }
            }
        } elseif (in_array($row['dropdown_type'], ['programme', 'speltakken'], true)) {
            if ($speltakken === null) {
                $speltakken = get_speltakken(true);
            }
            foreach ($speltakken as $speltak) {
                $row['children'][] = ['label' => $speltak['naam'], 'url' => $row['dropdown_type'] === 'programme'
                    ? programma_url($speltak['slug']) : speltak_url($speltak['slug'])];
            }
        }
        $tree[] = $row;
    }
    return $tree;
}

/** Valideert ook relaties tegen de binnen de transactie vergrendelde configuratie. */
function header_menu_validate(array $input, array $rows, int $id): array
{
    foreach (['label', 'link_type', 'page_id', 'link_value', 'dropdown_type', 'parent_id', 'volgorde', 'actief'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) {
            throw new RuntimeException('Ongeldige veldwaarde.');
        }
    }
    $item = ['label' => trim($input['label'] ?? ''), 'link_type' => $input['link_type'] ?? '',
        'link_value' => trim($input['link_value'] ?? ''), 'dropdown_type' => $input['dropdown_type'] ?? '',
        'actief' => isset($input['actief']) ? 1 : 0];
    if (preg_match('/\A.{1,150}\z/us', $item['label']) !== 1) {
        throw new RuntimeException('Vul een menutekst van maximaal 150 tekens in.');
    }
    if (!isset(HEADER_LINK_TYPES[$item['link_type']], HEADER_DROPDOWN_TYPES[$item['dropdown_type']])) {
        throw new RuntimeException('Ongeldig link- of dropdowntype.');
    }
    foreach (['parent_id', 'page_id', 'volgorde'] as $key) {
        $raw = $input[$key] ?? '0';
        if ($raw === '') {
            $raw = '0';
        }
        $number = filter_var($raw, FILTER_VALIDATE_INT);
        if ($number === false || $number < 0 || $number > 2147483647) {
            throw new RuntimeException('Ongeldige positie, pagina of bovenliggend item.');
        }
        $item[$key] = $key !== 'volgorde' && $number === 0 ? null : $number;
    }
    $children = array_filter($rows, function ($row) use ($id) { return $id && (int) $row['parent_id'] === $id; });
    if ($children && ($item['parent_id'] !== null || $item['dropdown_type'] !== 'manual')) {
        throw new RuntimeException('Verwijder eerst de submenu-items voordat je deze dropdown wijzigt.');
    }
    if ($item['parent_id'] !== null) {
        $parent = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $item['parent_id']) {
                $parent = $row;
            }
        }
        if (!$parent || $item['parent_id'] === $id || $parent['parent_id'] !== null || $parent['dropdown_type'] !== 'manual' || $item['dropdown_type'] !== 'none') {
            throw new RuntimeException('Een submenu hoort bij een handmatig hoofdmenu en kan zelf geen dropdown bevatten.');
        }
    }
    if ($item['link_type'] === 'page') {
        if (!$item['page_id'] || !get_page($item['page_id'])) {
            throw new RuntimeException('Kies een bestaande pagina.');
        }
        $item['link_value'] = '';
    } else {
        $item['page_id'] = null;
        if ($item['link_type'] === 'section' && !isset(HEADER_SECTIONS[$item['link_value']])) {
            throw new RuntimeException('Kies een bestaande homepage-sectie.');
        }
        if ($item['link_type'] === 'url' && !header_menu_safe_url($item['link_value'])) {
            throw new RuntimeException('Gebruik een veilige http(s)-, mailto- of tel-URL, of een relatief pad binnen deze website.');
        }
        if (in_array($item['link_type'], ['programma', 'speltakken'], true)) {
            $item['link_value'] = '';
        }
    }
    return $item;
}

/** Alle samenhangende wijzigingen lopen door dezelfde transactie en server-side actiecontrole. */
function header_menu_mutate(string $action, int $id, array $input, ?array $editorContext = null): void
{
    if (!in_array($action, ['save', 'delete', 'up', 'down', 'toggle'], true)) {
        throw new RuntimeException('Ongeldige menuactie.');
    }
    db()->beginTransaction();
    try {
        $rows = db()->query('SELECT * FROM header_menu_items ORDER BY volgorde, id FOR UPDATE')->fetchAll();
        $current = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                $current = $row;
            }
        }
        if (($id || $action !== 'save') && !$current) {
            throw new RuntimeException('Menu-item niet gevonden.');
        }
        if ($action === 'save') {
            if ($editorContext !== null) {
                // De editor bepaalt geen plaatsing: bestaande positie behouden, nieuwe items achteraan.
                $parentId = $current ? $current['parent_id'] : ($editorContext['parent_id'] ?? null);
                $position = $current ? (int) $current['volgorde'] : 1;
                if (!$current) {
                    foreach ($rows as $row) {
                        if ((int) $row['parent_id'] === (int) $parentId) {
                            $position = max($position, min(2147483647, (int) $row['volgorde'] + 1));
                        }
                    }
                }
                $input['parent_id'] = $parentId === null ? '' : (string) $parentId;
                $input['volgorde'] = (string) $position;
            }
            $item = header_menu_validate($input, $rows, $id);
            if ($current) {
                $item['id'] = $id;
                $sql = 'UPDATE header_menu_items SET label=:label, link_type=:link_type, page_id=:page_id,
                    link_value=:link_value, dropdown_type=:dropdown_type, parent_id=:parent_id,
                    volgorde=:volgorde, actief=:actief WHERE id=:id';
            } else {
                $sql = 'INSERT INTO header_menu_items (label, link_type, page_id, link_value, dropdown_type, parent_id, volgorde, actief)
                    VALUES (:label, :link_type, :page_id, :link_value, :dropdown_type, :parent_id, :volgorde, :actief)';
            }
            db()->prepare($sql)->execute($item);
            $id = $current ? $id : (int) db()->lastInsertId();
            $label = $item['label'];
        } else {
            $label = $current['label'];
            if ($action === 'delete') {
                foreach ($rows as $row) {
                    if ((int) $row['parent_id'] === $id) {
                        throw new RuntimeException('Verwijder eerst de submenu-items.');
                    }
                }
                db()->prepare('DELETE FROM header_menu_items WHERE id = ?')->execute([$id]);
            } elseif ($action === 'toggle') {
                db()->prepare('UPDATE header_menu_items SET actief = ? WHERE id = ?')->execute([$current['actief'] ? 0 : 1, $id]);
            } else {
                $siblings = array_values(array_filter($rows, function ($row) use ($current) { return $row['parent_id'] === $current['parent_id']; }));
                $index = array_search($id, array_map('intval', array_column($siblings, 'id')), true);
                $target = $index + ($action === 'up' ? -1 : 1);
                if (isset($siblings[$target])) {
                    $swap = $siblings[$index];
                    $siblings[$index] = $siblings[$target];
                    $siblings[$target] = $swap;
                }
                $stmt = db()->prepare('UPDATE header_menu_items SET volgorde = ? WHERE id = ?');
                foreach ($siblings as $position => $sibling) {
                    $stmt->execute([$position + 1, $sibling['id']]);
                }
            }
        }
        audit_log($action === 'save' ? ($current ? 'update' : 'create') : $action, 'header_menu', $id, 'Headermenu: ' . $label);
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }
}

function header_menu_page_used(int $id): bool
{
    try {
        $stmt = db()->prepare('SELECT id FROM header_menu_items WHERE page_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $exception) {
        if (!header_menu_missing_table($exception)) {
            throw $exception;
        }
        return false;
    }
}
