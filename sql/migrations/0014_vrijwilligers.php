<?php
/**
 * Vrijwilligers (smoelenboek): personen, vrije groepen en rollen, plus per
 * speltak de schakelaar toon_leiding. Zelfde eindresultaat als sql/install.sql.
 *
 * PHP i.p.v. SQL zodat de migratie idempotent is (SHOW COLUMNS) en elke stap
 * apart wordt uitgevoerd.
 */
db()->exec("CREATE TABLE IF NOT EXISTS vrijwilliger_groepen (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam     VARCHAR(100) NOT NULL,
    slug     VARCHAR(100) NOT NULL,
    actief   TINYINT(1) NOT NULL DEFAULT 1,
    volgorde INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_vrijwilliger_groep_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS vrijwilligers (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam       VARCHAR(150) NOT NULL,
    foto       VARCHAR(255) NULL,
    email      VARCHAR(190) NULL,
    actief     TINYINT(1) NOT NULL DEFAULT 1,
    bijgewerkt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->exec("CREATE TABLE IF NOT EXISTS vrijwilliger_rollen (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vrijwilliger_id INT UNSIGNED NOT NULL,
    speltak_id      INT UNSIGNED NULL,
    groep_id        INT UNSIGNED NULL,
    functie         VARCHAR(120) NOT NULL DEFAULT '',
    volgorde        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_rol_vrijwilliger (vrijwilliger_id),
    KEY idx_rol_speltak (speltak_id),
    KEY idx_rol_groep (groep_id),
    CONSTRAINT fk_rol_vrijwilliger FOREIGN KEY (vrijwilliger_id) REFERENCES vrijwilligers (id) ON DELETE CASCADE,
    CONSTRAINT fk_rol_speltak FOREIGN KEY (speltak_id) REFERENCES speltakken (id) ON DELETE CASCADE,
    CONSTRAINT fk_rol_groep FOREIGN KEY (groep_id) REFERENCES vrijwilliger_groepen (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$columns = db()->query('SHOW COLUMNS FROM speltakken')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('toon_leiding', $columns, true)) {
    db()->exec('ALTER TABLE speltakken ADD COLUMN toon_leiding TINYINT(1) NOT NULL DEFAULT 1 AFTER bijgewerkt');
}
