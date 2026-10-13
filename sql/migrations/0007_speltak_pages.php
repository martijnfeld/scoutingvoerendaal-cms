<?php
/**
 * Eigen pagina per speltak (/speltakken/<slug>) en een overzichtspagina
 * (/speltakken): logo, inhoud (CKEditor), meta-omschrijving en
 * laatst-bijgewerkt per speltak, plus de introtekst van het overzicht.
 * Zelfde eindresultaat als sql/install.sql.
 *
 * PHP i.p.v. SQL zodat elke stap apart wordt uitgevoerd (een fout in een
 * latere statement van een .sql-bestand zou PDO kunnen missen) en een
 * half toegepaste migratie veilig opnieuw kan draaien.
 */
$columns = db()->query('SHOW COLUMNS FROM speltakken')->fetchAll(PDO::FETCH_COLUMN);
$add = [];
if (!in_array('logo', $columns, true)) {
    $add[] = 'ADD COLUMN logo VARCHAR(255) NULL AFTER afmeld_url';
}
if (!in_array('inhoud', $columns, true)) {
    $add[] = 'ADD COLUMN inhoud LONGTEXT NULL AFTER logo';
}
if (!in_array('meta_omschrijving', $columns, true)) {
    $add[] = 'ADD COLUMN meta_omschrijving VARCHAR(255) NULL AFTER inhoud';
}
if (!in_array('bijgewerkt', $columns, true)) {
    $add[] = 'ADD COLUMN bijgewerkt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER volgorde';
}
if ($add) {
    db()->exec('ALTER TABLE speltakken ' . implode(', ', $add));
}

// INSERT IGNORE laat een al bestaande waarde ongemoeid.
db()->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('speltakken_intro', 'Bij scouting worden de leden ingedeeld in speltakken: groepen op basis van leeftijd. Bekijk per speltak wat we doen, wanneer we opkomst hebben en wat er op het programma staat.')");

// /speltakken is nu de overzichtspagina (gaat in .htaccess vóór losse
// pagina's). Een bestaande losse pagina met die slug zou onbereikbaar
// worden; geef hem een unieke andere slug (id maakt hem uniek).
db()->exec("UPDATE pages SET slug = CONCAT('speltakken-pagina-', id) WHERE slug = 'speltakken'");
