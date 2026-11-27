<?php
/**
 * Instelbare titel van de leiding per speltak (speltakken.leiding_titel). Het oude
 * tekstveld speltakken.leiding vervalt: de namen komen nu uit de vrijwilligers.
 * Zelfde eindresultaat als sql/install.sql.
 */
$columns = db()->query('SHOW COLUMNS FROM speltakken')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('leiding_titel', $columns, true)) {
    db()->exec("ALTER TABLE speltakken ADD COLUMN leiding_titel VARCHAR(100) NOT NULL DEFAULT 'Leiding' AFTER toon_leiding");
}
// Bij uitrol begint elke bestaande speltak met de standaardtitel.
db()->exec("UPDATE speltakken SET leiding_titel = 'Leiding'");
if (in_array('leiding', $columns, true)) {
    db()->exec('ALTER TABLE speltakken DROP COLUMN leiding');
}
