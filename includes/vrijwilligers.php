<?php
/**
 * Vrijwilligers (smoelenboek): gegevenstoegang, weergave en de
 * [smoelenboek]-shortcode voor CKEditor-inhoud. Wordt geladen door
 * includes/functions.php.
 */

/* ------------------------------------------------------------------ *
 * Gegevens
 * ------------------------------------------------------------------ */

function get_vrijwilliger_groepen(bool $only_active = false): array
{
    $sql = 'SELECT * FROM vrijwilliger_groepen' . ($only_active ? ' WHERE actief = 1' : '')
        . ' ORDER BY volgorde ASC, naam ASC';
    return db()->query($sql)->fetchAll();
}

function get_vrijwilliger_groep(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM vrijwilliger_groepen WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function get_vrijwilliger_groep_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM vrijwilliger_groepen WHERE slug = :slug AND actief = 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function unique_vrijwilliger_groep_slug(string $base, ?int $excludeId = null): string
{
    $slug = slugify($base, 'groep');
    $original = $slug;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM vrijwilliger_groepen WHERE slug = :slug AND id != :id');
        $stmt->execute(['slug' => $slug, 'id' => $excludeId ?? 0]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i++;
    }
}

const VRIJWILLIGERS_SPELTAKKEN_POSITIE = 'vrijwilligers_speltakken_positie';

/**
 * De volgorde van het smoelenboek: de vrije groepen en één blok "speltakken"
 * (alle speltakken samen, in hun eigen volgorde) door elkaar. De groepen
 * hebben volgorde 1..n; de setting bepaalt na hoeveel groepen het blok komt.
 * Zonder setting staat het blok achteraan. Items: ['type' => 'groep', 'groep' => rij]
 * of ['type' => 'speltakken']. Leest de setting rechtstreeks (niet uit de
 * request-cache), zodat een verplaatsing direct zichtbaar is.
 */
function vrijwilliger_overzicht_items(bool $only_active = false): array
{
    $groepen = get_vrijwilliger_groepen(false);
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
    $stmt->execute(['k' => VRIJWILLIGERS_SPELTAKKEN_POSITIE]);
    $value = $stmt->fetchColumn();
    $position = ($value === false || $value === null || $value === '')
        ? count($groepen) : max(0, min(count($groepen), (int) $value));

    $items = [];
    foreach ($groepen as $i => $g) {
        if ($i === $position) {
            $items[] = ['type' => 'speltakken'];
        }
        if (!$only_active || $g['actief']) {
            $items[] = ['type' => 'groep', 'groep' => $g];
        }
    }
    if ($position >= count($groepen)) {
        $items[] = ['type' => 'speltakken'];
    }
    return $items;
}

/**
 * Verplaatst een groep of het speltakkenblok één plek omhoog/omlaag in
 * vrijwilliger_overzicht_items(). $type: 'groep' (met $id) of 'speltakken'.
 * Slaat het resultaat op als groepsvolgorde 1..n plus de blokpositie.
 */
function vrijwilliger_move_overzicht_item(string $type, int $id, string $direction): void
{
    if (!in_array($direction, ['up', 'down'], true) || !in_array($type, ['groep', 'speltakken'], true)) {
        return;
    }
    db()->beginTransaction();
    try {
        db()->query('SELECT id FROM vrijwilliger_groepen FOR UPDATE')->fetchAll();
        $items = vrijwilliger_overzicht_items(false);
        $index = null;
        foreach ($items as $i => $item) {
            if ($item['type'] === $type && ($type === 'speltakken' || (int) $item['groep']['id'] === $id)) {
                $index = $i;
            }
        }
        $neighbor = $index === null ? -1 : $index + ($direction === 'up' ? -1 : 1);
        if ($index !== null && isset($items[$neighbor])) {
            $moved = $items[$index];
            [$items[$index], $items[$neighbor]] = [$items[$neighbor], $items[$index]];
            $update = db()->prepare('UPDATE vrijwilliger_groepen SET volgorde = :v WHERE id = :id');
            $order = 0;
            $position = 0;
            foreach ($items as $item) {
                if ($item['type'] === 'speltakken') {
                    $position = $order;
                } else {
                    $update->execute(['v' => ++$order, 'id' => $item['groep']['id']]);
                }
            }
            set_setting(VRIJWILLIGERS_SPELTAKKEN_POSITIE, (string) $position);
            $name = $type === 'speltakken' ? 'Speltakken' : $moved['groep']['naam'];
            audit_log('move', 'vrijwilliger_groep', $type === 'groep' ? $id : null,
                'Smoelenboek-onderdeel "' . $name . '" ' . ($direction === 'up' ? 'omhoog' : 'omlaag') . ' verplaatst');
        }
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function get_vrijwilligers(): array
{
    return db()->query('SELECT * FROM vrijwilligers ORDER BY naam ASC, id ASC')->fetchAll();
}

function get_vrijwilliger(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM vrijwilligers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

/** Rollen van één persoon, met de naam van de groep of speltak erbij. */
function get_vrijwilliger_rollen(int $vrijwilligerId): array
{
    $stmt = db()->prepare(
        'SELECT r.*, s.naam AS speltak_naam, g.naam AS groep_naam
         FROM vrijwilliger_rollen r
         LEFT JOIN speltakken s ON s.id = r.speltak_id
         LEFT JOIN vrijwilliger_groepen g ON g.id = r.groep_id
         WHERE r.vrijwilliger_id = :id ORDER BY r.id ASC'
    );
    $stmt->execute(['id' => $vrijwilligerId]);
    return $stmt->fetchAll();
}

/**
 * Leden van een groep, in de ingestelde volgorde.
 * $target: ['speltak' => id] of ['groep' => id]. $only_active = alleen
 * actieve personen (publiek); het beheer toont ook inactieve.
 */
function get_groep_leden(array $target, bool $only_active = true): array
{
    $column = isset($target['speltak']) ? 'r.speltak_id' : 'r.groep_id';
    $id = (int) ($target['speltak'] ?? $target['groep'] ?? 0);
    $sql = 'SELECT r.id AS rol_id, r.functie, r.volgorde, v.id, v.naam, v.foto, v.email, v.actief
            FROM vrijwilliger_rollen r JOIN vrijwilligers v ON v.id = r.vrijwilliger_id
            WHERE ' . $column . ' = :id' . ($only_active ? ' AND v.actief = 1' : '')
         . ' ORDER BY r.volgorde ASC, r.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetchAll();
}

/** Titel van het leidingblok van een speltak ("Leiding" als er niets is ingesteld). */
function speltak_leiding_titel(array $speltak): string
{
    $titel = trim((string) ($speltak['leiding_titel'] ?? ''));
    return $titel !== '' ? $titel : 'Leiding';
}

/** Namen van de actieve vrijwilligers van een speltak, bv. "Jan, Klaas en Piet" ('' als er geen zijn). */
function speltak_leiding_namen(int $speltakId): string
{
    $namen = [];
    foreach (get_groep_leden(['speltak' => $speltakId]) as $lid) {
        $naam = trim((string) $lid['naam']);
        if ($naam !== '' && !in_array($naam, $namen, true)) {
            $namen[] = $naam;
        }
    }
    if (count($namen) > 1) {
        $laatste = array_pop($namen);
        return implode(', ', $namen) . ' en ' . $laatste;
    }
    return $namen[0] ?? '';
}

/**
 * Slaat de rollen van één persoon op (in één transactie). $rows: lijst van
 * ['id' => bestaande rol-id of 0, 'target' => 's:<speltak-id>'|'g:<groep-id>', 'functie' => '…'].
 * Een bestaande rol in dezelfde groep behoudt zijn volgorde; een nieuwe rol of
 * een rol die naar een andere groep gaat komt achteraan. Rollen die niet meer
 * in $rows staan worden verwijderd. Rijen zonder geldige bestemming worden
 * overgeslagen.
 */
function save_vrijwilliger_rollen(int $vrijwilligerId, array $rows): void
{
    $speltakIds = array_map('intval', db()->query('SELECT id FROM speltakken')->fetchAll(PDO::FETCH_COLUMN));
    $groepIds = array_map('intval', db()->query('SELECT id FROM vrijwilliger_groepen')->fetchAll(PDO::FETCH_COLUMN));

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT id, speltak_id, groep_id FROM vrijwilliger_rollen WHERE vrijwilliger_id = :id FOR UPDATE');
        $stmt->execute(['id' => $vrijwilligerId]);
        $existing = [];
        foreach ($stmt->fetchAll() as $row) {
            $existing[(int) $row['id']] = $row;
        }
        $keep = [];
        foreach ($rows as $row) {
            if (!preg_match('/^([sg]):(\d+)$/', (string) ($row['target'] ?? ''), $m)) {
                continue;
            }
            $targetId = (int) $m[2];
            if (!in_array($targetId, $m[1] === 's' ? $speltakIds : $groepIds, true)) {
                continue;
            }
            $speltakId = $m[1] === 's' ? $targetId : null;
            $groepId = $m[1] === 'g' ? $targetId : null;
            $functie = mb_substr(trim((string) ($row['functie'] ?? '')), 0, 120);
            $roleId = (int) ($row['id'] ?? 0);
            $old = $existing[$roleId] ?? null;

            if ($old && (int) $old['speltak_id'] === (int) $speltakId && (int) $old['groep_id'] === (int) $groepId) {
                db()->prepare('UPDATE vrijwilliger_rollen SET functie = :f WHERE id = :id')
                    ->execute(['f' => $functie, 'id' => $roleId]);
                $keep[$roleId] = true;
                continue;
            }
            $max = db()->prepare('SELECT COALESCE(MAX(volgorde), 0) FROM vrijwilliger_rollen WHERE speltak_id <=> :s AND groep_id <=> :g');
            $max->execute(['s' => $speltakId, 'g' => $groepId]);
            $volgorde = (int) $max->fetchColumn() + 1;
            if ($old) {
                db()->prepare('UPDATE vrijwilliger_rollen SET speltak_id = :s, groep_id = :g, functie = :f, volgorde = :v WHERE id = :id')
                    ->execute(['s' => $speltakId, 'g' => $groepId, 'f' => $functie, 'v' => $volgorde, 'id' => $roleId]);
                $keep[$roleId] = true;
            } else {
                db()->prepare('INSERT INTO vrijwilliger_rollen (vrijwilliger_id, speltak_id, groep_id, functie, volgorde) VALUES (:vid, :s, :g, :f, :v)')
                    ->execute(['vid' => $vrijwilligerId, 's' => $speltakId, 'g' => $groepId, 'f' => $functie, 'v' => $volgorde]);
                $keep[(int) db()->lastInsertId()] = true;
            }
        }
        $delete = db()->prepare('DELETE FROM vrijwilliger_rollen WHERE id = :id');
        foreach (array_keys($existing) as $roleId) {
            if (!isset($keep[$roleId])) {
                $delete->execute(['id' => $roleId]);
            }
        }
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

/* ------------------------------------------------------------------ *
 * Weergave
 * ------------------------------------------------------------------ */

/** E-mailadres omgekeerd en base64-gecodeerd; assets/js/main.js zet het terug. */
function smoelenboek_email_attr(string $email): string
{
    return base64_encode(strrev($email));
}

/**
 * Rendert secties met een kaartenraster. $sections: lijst van
 * ['titel' => string, 'kleur' => string|null, 'leden' => get_groep_leden()-rijen].
 * Secties zonder leden worden overgeslagen; een lege titel geeft geen kop.
 */
function smoelenboek_html(array $sections, string $headingTag = 'h2'): string
{
    $headingTag = in_array($headingTag, ['h2', 'h3'], true) ? $headingTag : 'h2';
    $html = '';
    foreach ($sections as $section) {
        if (empty($section['leden'])) {
            continue;
        }
        $style = !empty($section['kleur']) ? ' style="--speltak-kleur:' . e($section['kleur']) . '"' : '';
        $html .= '<div class="smoelenboek-groep"' . $style . '>';
        if (($section['titel'] ?? '') !== '') {
            $html .= '<' . $headingTag . ' class="smoelenboek-titel">';
            if (!empty($section['logo'])) {
                $html .= '<img class="smoelenboek-logo" src="' . e($section['logo']) . '" alt="" width="40" height="40" loading="lazy">';
            }
            $html .= e($section['titel']) . '</' . $headingTag . '>';
        }
        if (trim((string) ($section['toelichting'] ?? '')) !== '') {
            // HTML toegestaan, beheerd via CMS (CKEditor).
            $html .= '<div class="pagina-body smoelenboek-toelichting">' . add_lazy_loading($section['toelichting']) . '</div>';
        }
        $html .= '<ul class="smoelenboek">';
        foreach ($section['leden'] as $lid) {
            $html .= '<li class="smoel">';
            if (!empty($lid['foto'])) {
                $html .= '<img class="smoel-foto" src="' . e($lid['foto']) . '" alt="Foto van ' . e($lid['naam'])
                    . '" width="160" height="160" loading="lazy">';
            } else {
                $html .= '<span class="smoel-foto smoel-foto-leeg" aria-hidden="true"><i class="fa-solid fa-user"></i></span>';
            }
            $html .= '<span class="smoel-naam">' . e($lid['naam']) . '</span>';
            if (($lid['functie'] ?? '') !== '') {
                $html .= '<span class="smoel-functie">' . e($lid['functie']) . '</span>';
            }
            if (!empty($lid['email'])) {
                $html .= '<a class="smoel-mail" href="#" rel="nofollow" data-m="' . e(smoelenboek_email_attr($lid['email']))
                    . '"><i class="fa-solid fa-envelope" aria-hidden="true"></i>'
                    . '<span class="visually-hidden">Stuur e-mail naar ' . e($lid['naam']) . '</span></a><noscript><span class="smoel-mail">(JavaScript nodig voor het e-mailadres)</span></noscript>';
            }
            $html .= '</li>';
        }
        $html .= '</ul></div>';
    }
    return $html;
}

/* ------------------------------------------------------------------ *
 * Shortcode [smoelenboek]
 * ------------------------------------------------------------------ */

/** Parset ' groep="bestuur" speltak=\'welpen\'' (ook typografische aanhalingstekens van CKEditor). */
function shortcode_parse_attributes(string $raw): array
{
    $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = str_replace("\xC2\xA0", ' ', $raw);
    $attrs = [];
    $pattern = '/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|“([^”“]*)[”“]|„([^”“]*)[”“]|‘([^’‘]*)[’‘])/iu';
    if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $value = '';
            for ($i = 2; $i <= 6; $i++) {
                if (isset($m[$i]) && $m[$i] !== '') {
                    $value = $m[$i];
                    break;
                }
            }
            $attrs[strtolower($m[1])] = trim($value);
        }
    }
    return $attrs;
}

/**
 * Alle beschikbare shortcodes, voor de autocomplete in de CKEditor
 * (typ "[" in een rich-text veld). Lijst van ['code' => '[smoelenboek …]', 'label' => '…'].
 */
function shortcode_suggestions(): array
{
    $items = [['code' => '[smoelenboek]', 'label' => 'Smoelenboek — alle groepen en speltakken']];
    foreach (get_vrijwilliger_groepen(true) as $g) {
        $items[] = ['code' => '[smoelenboek groep="' . $g['slug'] . '"]', 'label' => 'Smoelenboek — groep ' . $g['naam']];
    }
    $items[] = ['code' => '[smoelenboek speltak="alle"]', 'label' => 'Smoelenboek — alle speltakken'];
    foreach (get_speltakken(true) as $sp) {
        $items[] = ['code' => '[smoelenboek speltak="' . $sp['slug'] . '"]', 'label' => 'Smoelenboek — speltak ' . $sp['naam']];
    }
    return $items;
}

/** Secties voor één [smoelenboek]-aanroep. Onbekende groep/speltak = lege lijst. */
function smoelenboek_sections(array $attrs): array
{
    $sections = [];
    $addSpeltak = function (array $sp) use (&$sections) {
        $sections[] = ['titel' => $sp['naam'], 'kleur' => $sp['kleur'], 'logo' => $sp['logo'] ?? '',
            'leden' => get_groep_leden(['speltak' => (int) $sp['id']])];
    };
    $addGroep = function (array $g) use (&$sections) {
        $sections[] = ['titel' => $g['naam'], 'kleur' => $g['kleur'], 'logo' => $g['logo'] ?? '',
            'toelichting' => $g['toelichting'] ?? '', 'leden' => get_groep_leden(['groep' => (int) $g['id']])];
    };

    $groep = strtolower($attrs['groep'] ?? '');
    $speltak = strtolower($attrs['speltak'] ?? '');
    if ($groep === '' && $speltak === '') {
        foreach (vrijwilliger_overzicht_items(true) as $item) {
            if ($item['type'] === 'groep') {
                $addGroep($item['groep']);
            } else {
                foreach (get_speltakken(true) as $sp) {
                    $addSpeltak($sp);
                }
            }
        }
        return $sections;
    }
    if ($groep !== '') {
        $g = get_vrijwilliger_groep_by_slug($groep);
        if ($g) {
            $addGroep($g);
        }
    }
    if ($speltak === 'alle') {
        foreach (get_speltakken(true) as $sp) {
            $addSpeltak($sp);
        }
    } elseif ($speltak !== '') {
        $sp = get_speltak_by_slug($speltak);
        if ($sp && $sp['actief']) {
            $addSpeltak($sp);
        }
    }
    return $sections;
}

/**
 * Vervangt [smoelenboek …] in CMS-inhoud door het smoelenboek. Het omringende
 * <p> van CKEditor wordt meegenomen, zodat er geen <div> in een <p> komt.
 */
function render_shortcodes(?string $html): string
{
    $html = (string) $html;
    if (stripos($html, '[smoelenboek') === false) {
        return $html;
    }
    return (string) preg_replace_callback(
        '/(?:<p[^>]*>\s*)?\[smoelenboek((?:\s[^\]]*)?)\](?:\s*<\/p>)?/i',
        function (array $m): string {
            return smoelenboek_html(smoelenboek_sections(shortcode_parse_attributes($m[1])), 'h2');
        },
        $html
    );
}

/**
 * Verwijdert het fotobestand van een (verwijderde) vrijwilliger, maar alleen
 * als het een eigen upload is die nergens meer in gebruik is. Persoonsgegevens
 * blijven zo niet onnodig op de server staan. Geeft terug of het is verwijderd.
 */
function vrijwilliger_remove_photo_if_unused(?string $foto): bool
{
    $foto = (string) $foto;
    if ($foto === '' || strpos($foto, rtrim(UPLOAD_URL, '/') . '/') !== 0) {
        return false;
    }
    $filename = basename($foto);
    if ($filename === '' || $filename[0] === '.' || find_upload_usage($filename)) {
        return false;
    }
    $path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;
    return is_file($path) && @unlink($path);
}
