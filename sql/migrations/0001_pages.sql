-- Losse pagina's (informatieve pagina's naast de hoofdpagina), inhoud
-- bewerkbaar via een uitgebreide CKEditor (met afbeeldingen en YouTube-video's).
CREATE TABLE IF NOT EXISTS pages (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titel              VARCHAR(150) NOT NULL,
    slug               VARCHAR(150) NOT NULL,
    inhoud             LONGTEXT NULL,
    meta_omschrijving  VARCHAR(255) NULL,
    in_menu            TINYINT(1) NOT NULL DEFAULT 0,
    actief             TINYINT(1) NOT NULL DEFAULT 1,
    volgorde           INT NOT NULL DEFAULT 0,
    bijgewerkt         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
