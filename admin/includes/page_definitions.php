<?php
/** Paginaschema: tabbladen, opslag en mogelijkheden komen uit één whitelist. */
function page_editor_definitions(): array
{
    return [
        'home' => [
            'titel' => 'Home', 'slug' => '', 'actief' => 1,
            'public_url' => '../', 'can_delete' => false, 'can_reorder' => false,
            'tabs' => [
                'hero' => ['label' => 'Hero', 'handler' => 'settings'],
                'kennismaken' => ['label' => 'Kennismaken-strip', 'handler' => 'settings'],
                'programma' => ['label' => 'Programma', 'handler' => 'settings'],
                'lidworden' => ['label' => 'Doe mee!', 'handler' => 'settings'],
                'info_cards' => ['label' => 'Info-vakjes', 'handler' => 'info_cards'],
                'verhuur' => ['label' => 'Verhuur', 'handler' => 'settings'],
                'contact' => ['label' => 'Contact', 'handler' => 'settings'],
            ],
        ],
        'speltakken' => [
            'titel' => 'Speltakken', 'slug' => 'speltakken', 'actief' => 1,
            'public_url' => '../speltakken', 'can_delete' => false, 'can_reorder' => false,
            'tabs' => ['speltakken' => ['label' => 'Speltakken', 'handler' => 'settings']],
        ],
        'content' => [
            'titel' => 'Nieuwe pagina', 'slug' => '', 'actief' => 1,
            'public_url' => null, 'can_delete' => true, 'can_reorder' => true,
            'tabs' => ['inhoud' => ['label' => 'Inhoud', 'handler' => 'page_record']],
        ],
    ];
}

function page_editor_url(string $key, string $tab = '', int $id = 0): string
{
    $query = $key === 'content' ? ($id ? ['id' => $id] : []) : ['pagina' => $key];
    if ($tab !== '') {
        $query['tab'] = $tab;
    }
    return 'pagina_form.php' . ($query ? '?' . http_build_query($query) : '');
}

/** Hetzelfde paginamodel voor overzicht, navigatie en editorselectie. */
function page_editor_definition(string $key, array $record = []): array
{
    $definitions = page_editor_definitions();
    if (!isset($definitions[$key])) {
        throw new InvalidArgumentException('Pagina niet gevonden.');
    }
    $definition = $definitions[$key];
    $definition['key'] = $key;
    $definition['id'] = null;
    if ($key === 'content' && $record) {
        // Alleen opgeslagen paginagegevens, nooit mogelijkheden of handlers overnemen.
        $definition = array_replace($definition, array_intersect_key($record, array_flip([
            'titel', 'slug', 'actief', 'volgorde',
        ])));
        $definition['id'] = (int) $record['id'];
        $definition['public_url'] = '../' . page_url($record['slug']);
    }
    $definition['edit_url'] = page_editor_url($key, '', $definition['id'] ?? 0);
    return $definition;
}

function page_overview_entries(array $records): array
{
    $entries = [];
    foreach (page_editor_definitions() as $key => $definition) {
        if ($key !== 'content') {
            $entries[] = page_editor_definition($key);
        }
    }
    foreach ($records as $record) {
        $entries[] = page_editor_definition('content', $record);
    }
    return $entries;
}

function page_editor_navigation(array $definition, string $activeTab): array
{
    $navigation = [
        'public_url' => $definition['actief'] ? $definition['public_url'] : null,
        'unavailable_text' => $definition['id'] ? 'Deze pagina is niet gepubliceerd.' : 'Sla de pagina eerst op om deze te bekijken.',
        'active_tab' => $activeTab, 'tabs' => [],
    ];
    foreach ($definition['tabs'] as $tab => $config) {
        $navigation['tabs'][$tab] = [
            'label' => $config['label'],
            'url' => page_editor_url($definition['key'], $tab, $definition['id'] ?? 0),
        ];
    }
    return $navigation;
}

/** Oude instellingentabbladen krijgen hun bestemming uit hetzelfde paginaschema. */
function legacy_settings_url(string $tab): ?string
{
    foreach (page_editor_definitions() as $key => $definition) {
        if (isset($definition['tabs'][$tab]) && $definition['tabs'][$tab]['handler'] !== 'page_record') {
            return page_editor_url($key, $tab);
        }
    }
    return null;
}

/** Alleen gebruikt door de oude URL; secties horen voortaan bij Home. */
function legacy_page_editor_url(string $page, $tab = null): ?string
{
    if (in_array($page, ['programma', 'lidworden', 'verhuur', 'contact'], true)) {
        return $tab === null || $tab === $page ? page_editor_url('home', $page) : null;
    }
    $definitions = page_editor_definitions();
    if ($page === 'content' || !isset($definitions[$page])) {
        return null;
    }
    if ($tab !== null && (!is_string($tab) || !isset($definitions[$page]['tabs'][$tab]))) {
        return null;
    }
    return page_editor_url($page, $tab ?? '');
}
