<?php
/** Publicatie en verwijdering gebruiken dezelfde controles in overzicht en editor. */
require_once __DIR__ . '/../../includes/header_menu.php';

/** Ook verborgen menu-items en eigen URL's gelden als bestaande navigatiekoppeling. */
function page_header_used(string $key, array $page = [], ?array $lockedRows = null): bool
{
    $rows = $lockedRows ?? header_menu_rows() ?? header_menu_legacy_rows();
    $slug = $key === 'speltakken' ? 'speltakken' : ($page['slug'] ?? '');
    foreach ($rows as $row) {
        if ($key === 'content' && $row['link_type'] === 'page' && (int) $row['page_id'] === (int) $page['id']) {
            return true;
        }
        if ($key === 'speltakken' && $row['link_type'] === 'speltakken') {
            return true;
        }
        if ($row['link_type'] !== 'url') { continue; }
        $url = $row['link_value'];
        if (preg_match('#^https?://#i', $url)) {
            $base = rtrim(get_setting('site_url'), '/') . '/';
            if (strpos($url, $base) !== 0) { continue; }
            $url = substr($url, strlen($base));
        }
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $path = trim(preg_replace('#^(?:\./|/)+#', '', $path), '/');
        if ($path === $slug) { return true; }
        if ($key === 'speltakken' && $path === 'speltakken.php') { return true; }
        if ($key === 'content' && $path === 'pagina.php') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            if (($query['slug'] ?? null) === $slug) { return true; }
        }
    }
    return false;
}

/** Menuwijzigingen en publicatie delen hun vergrendeling. */
function page_lock_header(): ?array
{
    try {
        return db()->query('SELECT * FROM header_menu_items ORDER BY volgorde, id FOR UPDATE')->fetchAll();
    } catch (PDOException $exception) {
        if (!header_menu_missing_table($exception)) { throw $exception; }
        return null;
    }
}

function page_overview_mutate(string $key, int $id, string $action): void
{
    db()->beginTransaction();
    try {
        // Dezelfde vergrendelvolgorde als het headerbeheer: eerst het menu, dan de pagina.
        $headerRows = page_lock_header();
        $page = [];
        if ($key === 'content') {
            $stmt = db()->prepare('SELECT * FROM pages WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $page = $stmt->fetch();
            if (!$page) { throw new RuntimeException('Pagina niet gevonden.'); }
        }
        $definition = page_editor_definition($key, $page);
        if ($key === 'speltakken') {
            $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'speltakken_page_active' FOR UPDATE");
            $value = $stmt->fetchColumn();
            $definition['actief'] = $value === false || $value === '1';
        }
        if ($action === 'delete' && $definition['can_delete']) {
            if (page_header_used($key, $page, $headerRows)) {
                throw new RuntimeException('Deze pagina wordt gebruikt in de header. Verwijder eerst de navigatiekoppelingen via Systeem → Instellingen → Header.');
            }
            db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
            audit_log('delete', 'pagina', $id, 'Pagina "' . $definition['titel'] . '" verwijderd');
        } elseif ($action === 'toggle' && $definition['can_toggle']) {
            $active = !$definition['actief'];
            if (!$active && page_header_used($key, $page, $headerRows)) {
                throw new RuntimeException('Deze pagina wordt gebruikt in de header. Verwijder eerst de navigatiekoppelingen via Systeem → Instellingen → Header.');
            }
            if ($key === 'speltakken') {
                set_setting('speltakken_page_active', $active ? '1' : '0');
            } else {
                db()->prepare('UPDATE pages SET actief = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
            }
            audit_log('toggle', 'pagina', $id ?: null, 'Pagina "' . $definition['titel'] . '" ' . ($active ? 'ingeschakeld' : 'uitgeschakeld'));
        } else {
            throw new RuntimeException('Deze actie is niet beschikbaar voor deze pagina.');
        }
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) { db()->rollBack(); }
        throw $exception;
    }
}
