<?php
/**
 * Vrijwilligersgroepen krijgen een kleur, logo en toelichting (zoals speltakken).
 * Zelfde eindresultaat als sql/install.sql. PHP i.p.v. SQL zodat de migratie
 * idempotent is (SHOW COLUMNS).
 */
$columns = db()->query('SHOW COLUMNS FROM vrijwilliger_groepen')->fetchAll(PDO::FETCH_COLUMN);
$add = [];
if (!in_array('kleur', $columns, true)) {
    $add[] = "ADD COLUMN kleur VARCHAR(20) NOT NULL DEFAULT '#00A551' AFTER slug";
}
if (!in_array('logo', $columns, true)) {
    $add[] = 'ADD COLUMN logo VARCHAR(255) NULL AFTER kleur';
}
if (!in_array('toelichting', $columns, true)) {
    $add[] = 'ADD COLUMN toelichting MEDIUMTEXT NULL AFTER logo';
}
if ($add) {
    db()->exec('ALTER TABLE vrijwilliger_groepen ' . implode(', ', $add));
}
