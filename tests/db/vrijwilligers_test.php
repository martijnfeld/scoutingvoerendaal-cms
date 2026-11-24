<?php
/**
 * Vrijwilligers (smoelenboek): rollen opslaan, volgorde per groep, cascade,
 * shortcode-weergave en bestandsgebruik. Eigen bestand (eigen proces en database).
 */
require_once TEST_ROOT . '/includes/functions.php';
require_once TEST_ROOT . '/admin/includes/content_order.php';

function test_vrijwilliger(string $naam, array $extra = []): int
{
    db()->prepare('INSERT INTO vrijwilligers (naam, foto, email, actief) VALUES (:n, :f, :e, :a)')
        ->execute(['n' => $naam, 'f' => $extra['foto'] ?? null, 'e' => $extra['email'] ?? null, 'a' => $extra['actief'] ?? 1]);
    return (int) db()->lastInsertId();
}

function test_groep(string $naam): int
{
    db()->prepare('INSERT INTO vrijwilliger_groepen (naam, slug, volgorde) VALUES (:n, :s, (SELECT COALESCE(MAX(v.volgorde), 0) + 1 FROM vrijwilliger_groepen v))')
        ->execute(['n' => $naam, 's' => slugify($naam)]);
    return (int) db()->lastInsertId();
}

test('toon_leiding staat standaard aan en de tabellen bestaan', function () {
    assert_same(1, (int) get_speltak(1)['toon_leiding']);
    foreach (['vrijwilligers', 'vrijwilliger_groepen', 'vrijwilliger_rollen'] as $table) {
        assert_same(0, (int) db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
    }
});

test('save_vrijwilliger_rollen(): nieuwe rollen komen achteraan, bestaande houden hun volgorde', function () {
    $bestuur = test_groep('Bestuur');
    $welpen = (int) get_speltak(1)['id'];
    $a = test_vrijwilliger('Anna');
    $b = test_vrijwilliger('Bram');
    save_vrijwilliger_rollen($a, [['id' => 0, 'target' => 'g:' . $bestuur, 'functie' => 'Voorzitter']]);
    save_vrijwilliger_rollen($b, [
        ['id' => 0, 'target' => 'g:' . $bestuur, 'functie' => 'Penningmeester'],
        ['id' => 0, 'target' => 's:' . $welpen, 'functie' => 'Leiding'],
        ['id' => 0, 'target' => '', 'functie' => 'genegeerd'],
        ['id' => 0, 'target' => 'g:99999', 'functie' => 'bestaat niet'],
    ]);
    assert_same(['Anna', 'Bram'], array_column(get_groep_leden(['groep' => $bestuur]), 'naam'));
    assert_same(['Voorzitter', 'Penningmeester'], array_column(get_groep_leden(['groep' => $bestuur]), 'functie'));
    assert_same(['Bram'], array_column(get_groep_leden(['speltak' => $welpen]), 'naam'));
    assert_same(2, count(get_vrijwilliger_rollen($b)));

    // Functie wijzigen behoudt de volgorde; verplaatsen naar een andere groep zet achteraan.
    $roleA = get_vrijwilliger_rollen($a)[0];
    save_vrijwilliger_rollen($a, [['id' => (int) $roleA['id'], 'target' => 'g:' . $bestuur, 'functie' => 'Vice-voorzitter']]);
    assert_same(['Vice-voorzitter', 'Penningmeester'], array_column(get_groep_leden(['groep' => $bestuur]), 'functie'));
    save_vrijwilliger_rollen($a, [['id' => (int) $roleA['id'], 'target' => 's:' . $welpen, 'functie' => 'Leiding']]);
    assert_same(['Bram', 'Anna'], array_column(get_groep_leden(['speltak' => $welpen]), 'naam'));
    assert_same(['Bram'], array_column(get_groep_leden(['groep' => $bestuur]), 'naam'));

    // Rollen die niet meer in de lijst staan verdwijnen.
    save_vrijwilliger_rollen($b, []);
    assert_same(0, count(get_vrijwilliger_rollen($b)));
});

test('volgorde verplaatsen blijft binnen de eigen groep (NULL-veilig)', function () {
    $welpen = (int) get_speltak(1)['id'];
    $scouts = (int) get_speltak(2)['id'];
    $bestuur = (int) get_vrijwilliger_groep_by_slug('bestuur')['id'];
    $c = test_vrijwilliger('Cas');
    $d = test_vrijwilliger('Dees');
    save_vrijwilliger_rollen($c, [['id' => 0, 'target' => 's:' . $scouts, 'functie' => ''], ['id' => 0, 'target' => 'g:' . $bestuur, 'functie' => 'Lid']]);
    save_vrijwilliger_rollen($d, [['id' => 0, 'target' => 's:' . $scouts, 'functie' => '']]);

    $scoutsBefore = array_column(get_groep_leden(['speltak' => $scouts]), 'naam');
    $welpenBefore = get_groep_leden(['speltak' => $welpen]);
    $bestuurBefore = get_groep_leden(['groep' => $bestuur]);
    $rolD = (int) get_groep_leden(['speltak' => $scouts])[1]['rol_id'];
    move_content_record('vrijwilliger_rollen', $rolD, 'up');
    assert_same(array_reverse($scoutsBefore), array_column(get_groep_leden(['speltak' => $scouts]), 'naam'));
    assert_same($welpenBefore, get_groep_leden(['speltak' => $welpen]), 'andere speltak ongemoeid');
    assert_same($bestuurBefore, get_groep_leden(['groep' => $bestuur]), 'groepen ongemoeid');
    assert_true((int) db()->query("SELECT COUNT(*) FROM audit_log WHERE actie = 'move' AND entiteit = 'vrijwilliger_rol'")->fetchColumn() >= 1);
});

test('inactieve vrijwilligers staan niet in het publieke overzicht', function () {
    $bestuur = (int) get_vrijwilliger_groep_by_slug('bestuur')['id'];
    $e = test_vrijwilliger('Eef', ['actief' => 0]);
    save_vrijwilliger_rollen($e, [['id' => 0, 'target' => 'g:' . $bestuur, 'functie' => 'X']]);
    assert_false(in_array('Eef', array_column(get_groep_leden(['groep' => $bestuur]), 'naam'), true));
    assert_true(in_array('Eef', array_column(get_groep_leden(['groep' => $bestuur], false), 'naam'), true));
});

test('[smoelenboek] rendert groep, speltak en alle; onbekend geeft niets', function () {
    $welpen = get_speltak(1);
    $html = render_shortcodes('<p>Voor</p><p>[smoelenboek groep="bestuur"]</p><p>Na</p>');
    assert_contains('<p>Voor</p><div class="smoelenboek-groep"', $html, 'omringende <p> is weg');
    assert_contains('</div><p>Na</p>', $html);
    assert_contains('Anna', render_shortcodes('[smoelenboek speltak="' . $welpen['slug'] . '"]'));
    assert_contains('Bestuur', render_shortcodes('<p>[smoelenboek]</p>'));
    assert_contains('Cas', render_shortcodes('[smoelenboek speltak="alle"]'));
    assert_same('<p></p>', '<p>' . render_shortcodes('[smoelenboek groep="bestaat-niet"]') . '</p>');
    assert_same('', render_shortcodes('<p>[smoelenboek groep=“bestaat-niet”]</p>'));
    assert_not_contains('Eef', render_shortcodes('[smoelenboek groep="bestuur"]'), 'inactief niet getoond');
});

test('een groep met rollen kan niet worden verwijderd (RESTRICT); een speltak neemt zijn rollen mee (CASCADE)', function () {
    $bestuur = (int) get_vrijwilliger_groep_by_slug('bestuur')['id'];
    $blocked = false;
    try {
        db()->prepare('DELETE FROM vrijwilliger_groepen WHERE id = :id')->execute(['id' => $bestuur]);
    } catch (PDOException $e) {
        $blocked = true;
    }
    assert_true($blocked, 'FK RESTRICT');

    db()->exec("INSERT INTO speltakken (naam, slug, kleur, volgorde) VALUES ('Tijdelijk', 'tijdelijk', '#000000', 99)");
    $tmp = (int) db()->lastInsertId();
    $f = test_vrijwilliger('Fien');
    save_vrijwilliger_rollen($f, [['id' => 0, 'target' => 's:' . $tmp, 'functie' => 'Leiding']]);
    assert_same(1, count(get_vrijwilliger_rollen($f)));
    db()->prepare('DELETE FROM speltakken WHERE id = :id')->execute(['id' => $tmp]);
    assert_same(0, count(get_vrijwilliger_rollen($f)));
    assert_true(get_vrijwilliger($f) !== null, 'persoon blijft bestaan');
});

test('find_upload_usage() ziet foto\'s en vrijwilliger_remove_photo_if_unused() ruimt alleen ongebruikte op', function () {
    $name = bin2hex(random_bytes(6)) . '.jpg';
    $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!is_dir(UPLOAD_DIR)) {
        skip('uploadmap ontbreekt');
    }
    file_put_contents($path, 'x');
    try {
        $g = test_vrijwilliger('Gijs', ['foto' => 'assets/uploads/' . $name]);
        $h = test_vrijwilliger('Hanna', ['foto' => 'assets/uploads/' . $name]);
        assert_true(in_array('Vrijwilliger: Gijs', find_upload_usage($name), true));
        db()->prepare('DELETE FROM vrijwilligers WHERE id = :id')->execute(['id' => $g]);
        assert_false(vrijwilliger_remove_photo_if_unused('assets/uploads/' . $name), 'nog in gebruik door Hanna');
        assert_true(is_file($path));
        db()->prepare('DELETE FROM vrijwilligers WHERE id = :id')->execute(['id' => $h]);
        assert_true(vrijwilliger_remove_photo_if_unused('assets/uploads/' . $name));
        assert_false(is_file($path));
        assert_false(vrijwilliger_remove_photo_if_unused('../config.php'), 'alleen eigen uploads');
    } finally {
        @unlink($path);
    }
});

test('smoelenboekvolgorde: groepen en het speltakkenblok zijn door elkaar te zetten', function () {
    $bestuur = (int) get_vrijwilliger_groep_by_slug('bestuur')['id'];
    $overig = test_groep('Overig');
    $derde = test_groep('Derde');
    db()->prepare('UPDATE vrijwilliger_groepen SET kleur = :k, logo = :l, toelichting = :t WHERE id = :id')
        ->execute(['k' => '#112233', 'l' => 'assets/uploads/groep.png', 't' => '<p>Over <b>Overig</b></p>', 'id' => $overig]);
    $v = test_vrijwilliger('Olga');
    save_vrijwilliger_rollen($v, [['id' => 0, 'target' => 'g:' . $overig, 'functie' => 'Lid'], ['id' => 0, 'target' => 'g:' . $derde, 'functie' => 'Lid']]);

    $describe = function () {
        return array_map(function ($item) { return $item['type'] === 'speltakken' ? 'speltakken' : $item['groep']['naam']; }, vrijwilliger_overzicht_items());
    };
    assert_same(['Bestuur', 'Overig', 'Derde', 'speltakken'], $describe(), 'standaard: speltakken achteraan');

    vrijwilliger_move_overzicht_item('speltakken', 0, 'up');
    assert_same(['Bestuur', 'Overig', 'speltakken', 'Derde'], $describe());
    vrijwilliger_move_overzicht_item('speltakken', 0, 'up');
    vrijwilliger_move_overzicht_item('speltakken', 0, 'up');
    assert_same(['speltakken', 'Bestuur', 'Overig', 'Derde'], $describe());
    vrijwilliger_move_overzicht_item('speltakken', 0, 'up');
    assert_same(['speltakken', 'Bestuur', 'Overig', 'Derde'], $describe(), 'bovenaan blijft bovenaan');

    // Een groep over het blok heen en weer.
    vrijwilliger_move_overzicht_item('groep', $bestuur, 'up');
    assert_same(['Bestuur', 'speltakken', 'Overig', 'Derde'], $describe());
    vrijwilliger_move_overzicht_item('groep', $derde, 'up');
    assert_same(['Bestuur', 'speltakken', 'Derde', 'Overig'], $describe());
    assert_same([1, 2, 3], array_column(get_vrijwilliger_groepen(), 'volgorde') ? array_map('intval', array_column(get_vrijwilliger_groepen(), 'volgorde')) : [], 'groepen genummerd 1..n');
    vrijwilliger_move_overzicht_item('groep', $derde, 'ongeldig');
    assert_same(['Bestuur', 'speltakken', 'Derde', 'Overig'], $describe());

    // De shortcode volgt dezelfde volgorde; kleur, logo en toelichting worden getoond.
    $html = render_shortcodes('[smoelenboek]');
    $pos = function ($needle) use ($html) { return strpos($html, $needle); };
    assert_true($pos('>Bestuur<') < $pos('smoel-naam">Anna<') && $pos('smoel-naam">Anna<') < $pos('Derde') && $pos('Derde') < $pos('Overig'), 'volgorde in de weergave');
    assert_contains('--speltak-kleur:#112233', $html);
    assert_contains('src="assets/uploads/groep.png"', $html);
    assert_contains('<p>Over <b>Overig</b></p>', $html);
    assert_true(in_array('Vrijwilligersgroep: Overig', find_upload_usage('groep.png'), true), 'logo telt als gebruikt');

    // Inactieve groepen verdwijnen uit de weergave maar houden hun plek.
    db()->prepare('UPDATE vrijwilliger_groepen SET actief = 0 WHERE id = :id')->execute(['id' => $derde]);
    assert_not_contains('Derde', render_shortcodes('[smoelenboek]'));
    assert_same(['Bestuur', 'speltakken', 'Overig'], array_map(function ($i) { return $i['type'] === 'speltakken' ? 'speltakken' : $i['groep']['naam']; }, vrijwilliger_overzicht_items(true)));
});
