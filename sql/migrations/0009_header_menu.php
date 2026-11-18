<?php
/** DDL commit impliciet; de volledige dataoverdracht gebeurt daarna atomair.
 * Een gevulde tabel is bestaande configuratie en wordt nooit opnieuw aangevuld.
 * Een onderbroken dataoverdracht rolt geheel terug; een herstart na commit is een no-op.
 */
db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS header_menu_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    label VARCHAR(150) NOT NULL,
    link_type VARCHAR(30) NOT NULL,
    page_id INT UNSIGNED NULL,
    link_value VARCHAR(2048) NOT NULL DEFAULT '',
    dropdown_type VARCHAR(30) NOT NULL DEFAULT 'none',
    volgorde INT NOT NULL DEFAULT 0,
    actief TINYINT(1) NOT NULL DEFAULT 1,
    seed_key VARCHAR(100) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_header_seed (seed_key),
    KEY idx_header_order (parent_id, volgorde, id),
    KEY idx_header_page (page_id),
    CONSTRAINT fk_header_parent FOREIGN KEY (parent_id) REFERENCES header_menu_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_header_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
);
db()->beginTransaction();
try {
    db()->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('header_menu_initialized', '0')");
    $initialized = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'header_menu_initialized' FOR UPDATE")->fetchColumn();
    $existing = db()->query('SELECT id FROM header_menu_items ORDER BY id FOR UPDATE')->fetchAll();
    if ($initialized !== '1' && !$existing) {
        db()->exec(<<<'SQL'
INSERT INTO header_menu_items (label, link_type, link_value, dropdown_type, volgorde, seed_key) VALUES
('Home', 'section', 'top', 'none', 1, 'home'),
('Programma', 'section', 'programma', 'programme', 2, 'programma'),
('Speltakken', 'speltakken', '', 'speltakken', 3, 'speltakken'),
('Lid worden', 'section', 'lidworden', 'none', 4, 'lidworden'),
('Verhuur', 'section', 'verhuur', 'none', 5, 'verhuur'),
('Contact', 'section', 'contact', 'none', 6, 'contact');
SQL
        );
        $insert = db()->prepare("INSERT INTO header_menu_items
            (label, link_type, page_id, volgorde, actief, seed_key)
            VALUES (:label, 'page', :page_id, :volgorde, 1, :seed_key)");
        $position = 6;
        foreach (db()->query('SELECT id, titel FROM pages WHERE in_menu = 1 ORDER BY volgorde ASC, titel ASC, id ASC')->fetchAll() as $page) {
            $insert->execute(['label' => $page['titel'], 'page_id' => $page['id'],
                'volgorde' => ++$position, 'seed_key' => 'page-' . $page['id']]);
        }
    }
    db()->exec("UPDATE settings SET setting_value = '1' WHERE setting_key = 'header_menu_initialized'");
    db()->commit();
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    throw $exception;
}
