<?php
/** HTTP-integratie tegen uitsluitend de wegwerp-testdatabase, zonder bestaande beheeraccounts. */
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/includes/header_menu.php';

function header_assert_status(int $status, array $response, string $context = 'Header-integratie'): void
{
    assert_status($status, $response, $context);
}
function header_assert_no_php_errors(array $response, string $context = 'Header-integratie'): void
{
    assert_no_php_errors($response, $context);
}

function header_http_server(): void
{
    static $started = false;
    if ($started) { return; }
    $started = true;
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $dir = test_backup_dir() . '/header-http';
    mkdir($dir, 0700, true);
    $constants = ['DB_HOST' => DB_HOST, 'DB_PORT' => DB_PORT, 'DB_NAME' => DB_NAME, 'DB_USER' => DB_USER,
        'DB_PASS' => DB_PASS, 'APP_DEBUG' => true, 'BACKUP_DIR' => $dir . '/backups'];
    $router = "<?php\n";
    foreach ($constants as $key => $value) { $router .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n"; }
    $router .= '$root = ' . var_export(realpath(TEST_ROOT), true) . ";\n";
    $router .= <<<'ROUTER'
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = strpos($path, '/vereniging/') === 0 ? '/vereniging' : '';
$path = substr($path, strlen($prefix));
if ($path === '/') {
    $script = '/index.php';
} elseif ($path === '/speltakken') {
    $script = '/speltakken.php';
} elseif (preg_match('~^/speltakken/([^/]+)$~', $path, $match)) {
    $script = '/speltak.php'; $_GET['slug'] = $match[1];
} elseif (preg_match('~^/admin/[a-z_]+\.php$~', $path)) {
    $script = $path;
} else {
    $script = '/pagina.php'; $_GET['slug'] = trim($path, '/');
}
$_SERVER['SCRIPT_NAME'] = $prefix . $script;
$_SERVER['PHP_SELF'] = $prefix . $script;
require $root . $script;
ROUTER;
    file_put_contents($dir . '/router.php', $router);
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $dir, '-S', $address, $dir . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $dir . '/server.log', 'a'], 2 => ['file', $dir . '/server.log', 'a']], $pipes, TEST_ROOT);
    if (!is_resource($process)) { fail('Kon de lokale PHP-testserver niet starten.'); }
    fclose($pipes[0]);
    register_shutdown_function(function () use ($process) { proc_terminate($process); proc_close($process); });
    putenv('TEST_BASE_URL=http://' . $address);
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($connection) { fclose($connection); return; }
        usleep(25000);
    }
    fail('De lokale PHP-testserver werd niet bereikbaar.');
}
function header_http_login(): array
{
    header_http_server();
    $page = http_get('admin/login.php');
    $result = http_post('admin/login.php', ['csrf_token' => http_csrf_token($page), 'username' => 'header-test', 'password' => 'Test-header-12!']);
    header_assert_status(302, $result);
    return http_get('admin/settings.php?tab=header');
}
function header_http_save(array $extra = [], ?int $parentId = null): array
{
    $page = http_get('admin/settings.php?tab=header');
    return http_post('admin/settings.php?tab=header' . ($parentId !== null ? '&new=1&parent=' . $parentId : ''), array_merge(['csrf_token' => http_csrf_token($page),
        'action' => 'save', 'id' => '0', 'label' => 'HTTP menu', 'link_type' => 'section', 'section_value' => 'contact',
        'dropdown_type' => 'none', 'parent_id' => '', 'volgorde' => '20', 'page_id' => '', 'actief' => '1'], $extra));
}

test('headerbeheer vereist authenticatie en weigert CSRF en gemanipuleerde aanvragen', function () {
    header_http_server();
    $r = http_get('admin/settings.php?tab=header');
    header_assert_status(302, $r);
    assert_same('login.php', http_header($r, 'Location'));
    db()->prepare('INSERT INTO admin_users (username,password_hash) VALUES (?,?)')->execute(['header-test', password_hash('Test-header-12!', PASSWORD_DEFAULT)]);
    $page = header_http_login();
    header_assert_status(200, $page);
    header_assert_no_php_errors($page, 'Header-tabblad');
    assert_contains('Menu-item toevoegen', $page['body']);
    $before = header_menu_rows();
    foreach ([['csrf_token' => 'fout'], ['action' => 'drop'], ['id' => ['1']], ['label' => ['fout']],
        ['link_type' => 'url', 'url_value' => 'javascript:alert(1)'], ['link_type' => 'page', 'page_id' => '999999']] as $extra) {
        $r = header_http_save($extra);
        header_assert_status(200, $r);
        header_assert_no_php_errors($r);
        assert_same($before, header_menu_rows(), 'ongeldige aanvraag wijzigt niets');
    }
});

test('HTTP-menuopslag gebruikt PRG, onafhankelijke labels en dynamische paginaslugs', function () {
    db()->exec("INSERT INTO pages (titel,slug) VALUES ('Paginatitel','http-pagina')");
    $id = (int) db()->lastInsertId();
    header_assert_status(303, header_http_save(['link_type' => 'page', 'page_id' => (string) $id, 'label' => 'Eigen menutekst']));
    $r = http_get('http-pagina');
    header_assert_status(200, $r);
    header_assert_no_php_errors($r);
    assert_contains('class="header-main-link active" href="http-pagina" aria-current="page">Eigen menutekst</a>', $r['body']);
    db()->prepare("UPDATE pages SET slug='http-nieuw' WHERE id=?")->execute([$id]);
    $r = http_get('http-nieuw');
    assert_contains('href="http-nieuw" aria-current="page">Eigen menutekst</a>', $r['body']);
    $editor = http_get('admin/pagina_form.php?id=' . $id);
    assert_not_contains('name="in_menu"', $editor['body']);
    $list = http_get('admin/paginas.php');
    assert_not_contains('<th>Menu</th>', $list['body']);
    $r = http_post('admin/paginas.php', ['csrf_token' => http_csrf_token($list), 'action' => 'delete', 'id' => (string) $id]);
    assert_contains('Instellingen → Header', $r['body']);
    assert_true(get_page($id) !== null);
    db()->prepare('UPDATE pages SET actief=0 WHERE id=?')->execute([$id]);
    assert_not_contains('>Eigen menutekst</a>', http_get('')['body']);
    $menuId = (int) db()->query("SELECT id FROM header_menu_items WHERE label='Eigen menutekst'")->fetchColumn();
    $r = http_post('admin/settings.php?tab=header', ['csrf_token' => http_csrf_token(http_get('admin/settings.php?tab=header')), 'action' => 'delete', 'id' => (string) $menuId]);
    header_assert_status(303, $r);
    assert_true(get_page($id) !== null, 'menu verwijderen bewaart pagina');
    $list = http_get('admin/paginas.php');
    header_assert_status(302, http_post('admin/paginas.php', ['csrf_token' => http_csrf_token($list), 'action' => 'delete', 'id' => (string) $id]));
});

test('een pagina via de editor toevoegen maakt geen headeritem en negeert de oude menuvlag', function () {
    $before = header_menu_rows();
    $editor = http_get('admin/pagina_form.php');
    $r = http_post('admin/pagina_form.php', ['csrf_token' => http_csrf_token($editor),
        'titel' => 'Via editor', 'slug' => 'via-editor', 'inhoud' => '', 'actief' => '1', 'in_menu' => '1']);
    header_assert_status(302, $r);
    assert_same($before, header_menu_rows());
    assert_same(0, (int) get_page_by_slug('via-editor')['in_menu']);
    // Een historisch ingestelde vlag wordt bij bewerken evenmin aangeraakt.
    db()->exec("UPDATE pages SET in_menu=1 WHERE slug='via-editor'");
    $id = (int) get_page_by_slug('via-editor')['id'];
    $editor = http_get('admin/pagina_form.php?id=' . $id);
    $r = http_post('admin/pagina_form.php?id=' . $id, ['csrf_token' => http_csrf_token($editor),
        'titel' => 'Via editor gewijzigd', 'slug' => 'via-editor', 'inhoud' => '', 'actief' => '1']);
    header_assert_status(302, $r);
    assert_same(1, (int) get_page_by_slug('via-editor')['in_menu']);
    assert_same($before, header_menu_rows());
});

test('gerenderde navigatie werkt op Home, losse pagina’s, speltakroutes en in subdirectory', function () {
    db()->exec("INSERT INTO pages (titel,slug) VALUES ('HTTP gewone pagina','http-gewoon')");
    $speltak = get_speltakken(true)[0];
    foreach (['', 'vereniging/'] as $prefix) {
        foreach (['', 'http-gewoon', 'speltakken', 'speltakken/' . $speltak['slug']] as $path) {
            $r = http_get($prefix . $path);
            header_assert_status(200, $r, $prefix . $path);
            header_assert_no_php_errors($r);
            assert_contains('href="' . ($path === '' ? '' : './') . '#sk-' . $speltak['slug'] . '"', $r['body']);
            assert_contains('href="speltakken/' . $speltak['slug'] . '"', $r['body']);
            assert_contains('aria-expanded="false" aria-controls="header-submenu-', $r['body']);
            if (strpos($path, 'speltakken') === 0) {
                assert_contains('<base href="/' . $prefix . '">', $r['body']);
                assert_contains('class="header-main-link active" href="speltakken"', $r['body']);
                assert_contains('class="header-menu-link has-submenu is-active"', $r['body']);
            }
        }
    }
});

test('headeroverzicht groepeert submenu’s en de editor bewaart context bij toevoegen en fouten', function () {
    $overview = http_get('admin/settings.php?tab=header');
    assert_not_contains('data-header-editor', $overview['body'], 'overzicht toont geen leeg bewerkformulier');
    assert_contains('aria-label="Hoofdmenu"', $overview['body']);
    assert_contains('Automatisch submenu', $overview['body']);
    header_assert_status(303, header_http_save(['label' => 'Meer informatie', 'dropdown_type' => 'manual']));
    $parentId = (int) db()->query("SELECT id FROM header_menu_items WHERE label='Meer informatie'")->fetchColumn();
    $new = http_get('admin/settings.php?tab=header&new=1&parent=' . $parentId);
    header_assert_no_php_errors($new);
    assert_contains('data-header-editor', $new['body']);
    assert_contains('Dit item staat in het submenu van Meer informatie.', $new['body']);
    assert_not_contains('name="parent_id"', $new['body']);
    assert_not_contains('name="volgorde"', $new['body']);
    assert_not_contains('Plaats in het menu', $new['body']);
    header_assert_status(303, header_http_save(['label' => 'Leden', 'parent_id' => (string) $parentId,
        'link_type' => 'url', 'url_value' => '/leden'], $parentId));
    $childId = (int) db()->query("SELECT id FROM header_menu_items WHERE label='Leden'")->fetchColumn();
    $overview = http_get('admin/settings.php?tab=header');
    assert_contains('href="../leden"', $overview['body'], 'voorbeeldlink vertrekt vanuit de websitemap');
    $dom = new DOMDocument();
    @$dom->loadHTML($overview['body']);
    $xpath = new DOMXPath($dom);
    assert_same(1, $xpath->query('//li[contains(@class,"header-admin-group")][.//div[@id="menu-item-' . $parentId . '"]]//ol//div[@id="menu-item-' . $childId . '"]')->length);
    assert_same(0, $xpath->query('//div[contains(@class,"header-admin")]/descendant::i')->length, 'menu-acties gebruiken SVG');
    assert_true($xpath->query('//div[@id="menu-item-' . $parentId . '"]//svg')->length > 0);
    $editor = http_get('admin/settings.php?tab=header&edit=' . $childId);
    assert_contains('value="Leden"', $editor['body']);
    assert_contains('Terug naar menuoverzicht', $editor['body']);
    $before = header_menu_rows();
    $invalid = header_http_save(['id' => (string) $childId, 'label' => 'Naam behouden', 'parent_id' => (string) $parentId,
        'link_type' => 'url', 'url_value' => 'javascript:alert(1)']);
    header_assert_no_php_errors($invalid);
    assert_contains('value="Naam behouden"', $invalid['body']);
    assert_contains('Dit item staat in het submenu van Meer informatie.', $invalid['body']);
    assert_same($before, header_menu_rows());
    $invalidParent = http_get('admin/settings.php?tab=header&new=1&parent=999999');
    assert_contains('Dit handmatige submenu bestaat niet meer', $invalidParent['body']);
    assert_not_contains('data-header-editor', $invalidParent['body']);
    $unknownItem = http_get('admin/settings.php?tab=header&edit=999999');
    assert_contains('Dit menu-item bestaat niet meer', $unknownItem['body']);
});

test('de editor beheert geen volgorde of parent en de zichtbaarheidsschakelaar behoudt de configuratie', function () {
    $parentId = (int) db()->query("SELECT id FROM header_menu_items WHERE label='Meer informatie'")->fetchColumn();
    // Een los POST-veld mag van een nieuw hoofditem geen submenu-item maken.
    header_assert_status(303, header_http_save(['label' => 'Altijd hoofditem', 'parent_id' => (string) $parentId, 'volgorde' => '0']));
    $root = db()->query("SELECT * FROM header_menu_items WHERE label='Altijd hoofditem'")->fetch();
    assert_same(null, $root['parent_id']);
    $rootOrder = (int) $root['volgorde'];
    assert_true($rootOrder > (int) db()->query('SELECT MAX(volgorde) FROM header_menu_items WHERE parent_id IS NULL AND id != ' . (int) $root['id'])->fetchColumn());
    $child = db()->query("SELECT * FROM header_menu_items WHERE label='Leden'")->fetch();
    header_assert_status(303, header_http_save(['id' => (string) $child['id'], 'label' => 'Leden gewijzigd',
        'link_type' => 'url', 'url_value' => '/leden', 'parent_id' => '', 'volgorde' => '9999']));
    $updated = db()->query('SELECT * FROM header_menu_items WHERE id=' . (int) $child['id'])->fetch();
    assert_same($child['parent_id'], $updated['parent_id']);
    assert_same($child['volgorde'], $updated['volgorde']);
    $before = header_menu_rows();
    $denied = header_http_save(['label' => 'Mag niet ontstaan'], 999999);
    assert_contains('Dit handmatige submenu bestaat niet meer', $denied['body']);
    assert_same($before, header_menu_rows());
    $overview = http_get('admin/settings.php?tab=header');
    header_assert_status(303, http_post('admin/settings.php?tab=header', ['csrf_token' => http_csrf_token($overview),
        'action' => 'toggle', 'id' => (string) $root['id']]));
    $hidden = http_get('admin/settings.php?tab=header');
    $dom = new DOMDocument();
    @$dom->loadHTML($hidden['body']);
    $xpath = new DOMXPath($dom);
    $row = $xpath->query('//div[@id="menu-item-' . $root['id'] . '"]')->item(0);
    assert_contains('header-admin-row-inactive', $row->getAttribute('class'));
    assert_same(1, $xpath->query('.//button[@role="switch" and @aria-checked="false"]', $row)->length);
    assert_contains('Verborgen', $row->textContent);
    assert_same($rootOrder, (int) db()->query('SELECT volgorde FROM header_menu_items WHERE id=' . (int) $root['id'])->fetchColumn());
    assert_not_contains('>Altijd hoofditem</a>', http_get('')['body']);
    header_assert_status(303, http_post('admin/settings.php?tab=header', ['csrf_token' => http_csrf_token($hidden),
        'action' => 'toggle', 'id' => (string) $root['id']]));
    assert_contains('>Altijd hoofditem</a>', http_get('')['body']);
});

test('HTTP-fallback vóór migratie veroorzaakt geen fout en wijzigt geen gegevens', function () {
    db()->exec('DROP TABLE header_menu_items');
    db()->exec("INSERT INTO pages (titel,slug,in_menu) VALUES ('Legacy menu','http-legacy',1)");
    $r = http_get('');
    header_assert_status(200, $r);
    header_assert_no_php_errors($r);
    assert_contains('href="http-legacy">Legacy menu</a>', $r['body']);
    assert_same(null, header_menu_rows());
    assert_contains('Systeem → Updates', http_get('admin/settings.php?tab=header')['body']);
});
