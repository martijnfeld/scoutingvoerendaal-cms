<?php
/** Leesbare bestemmingen voor het beheer; relatieve links vertrekken vanuit de websitemap. */
function header_menu_admin_destination(array $item, array $pages): array
{
    $url = header_menu_url($item, '../');
    $description = '';
    if ($item['link_type'] === 'page') {
        $page = $pages[(int) $item['page_id']] ?? null;
        $description = 'Pagina: ' . ($page['titel'] ?? 'Niet gevonden');
        $path = !empty($item['page_slug']) ? '/' . page_url($item['page_slug']) : 'Pagina niet gevonden';
    } elseif ($item['link_type'] === 'programma' || ($item['link_type'] === 'section' && $item['link_value'] === 'programma')) {
        $description = 'Programmapagina';
        $path = '/' . programma_url();
    } elseif ($item['link_type'] === 'section') {
        $description = 'Home → ' . (HEADER_SECTIONS[$item['link_value']] ?? 'Onbekende sectie');
        $path = '/#' . $item['link_value'];
    } elseif ($item['link_type'] === 'speltakken') {
        $description = 'Speltakkenoverzicht';
        $path = '/' . speltakken_url();
    } else {
        $description = 'Eigen URL';
        $path = $item['link_value'];
    }
    if ($url !== null && !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) && strpos($url, '../') !== 0) {
        $url = '../' . (strpos($url, './') === 0 ? substr($url, 2) : $url);
    }
    return ['url' => $url, 'description' => $description, 'path' => $path];
}

/** Bouwt de hoofditems met hun kinderen en relatieve sorteerknoppen in één keer op. */
function header_menu_admin_groups(array $rows, array $pages): array
{
    $byParent = [];
    foreach ($rows as $row) {
        $byParent[(int) $row['parent_id']][] = $row;
    }
    $present = function (array $row, array $siblings, int $index, bool $parentHidden = false) use ($pages): array {
        $row['destination'] = header_menu_admin_destination($row, $pages);
        $row['can_up'] = $index > 0;
        $row['can_down'] = $index < count($siblings) - 1;
        $row['position'] = $index + 1;
        $row['status'] = !$row['actief'] ? 'Verborgen'
            : ($parentHidden ? 'Hoofditem verborgen'
            : ($row['link_type'] === 'page' && !$row['page_active'] ? 'Pagina inactief' : 'Zichtbaar'));
        $row['visible'] = $row['status'] === 'Zichtbaar';
        return $row;
    };
    $groups = [];
    $roots = $byParent[0] ?? [];
    foreach ($roots as $index => $row) {
        $item = $present($row, $roots, $index);
        $item['children'] = [];
        $children = $byParent[(int) $row['id']] ?? [];
        foreach ($children as $childIndex => $child) {
            $item['children'][] = $present($child, $children, $childIndex, !$item['visible']);
        }
        $groups[] = $item;
    }
    return $groups;
}
