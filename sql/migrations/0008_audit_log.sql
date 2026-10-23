-- Auditlog: wie heeft wanneer welke wijziging gedaan in het beheerpaneel,
-- getoond op Beheerpaneel → Logs (vastgelegd via audit_log()).
CREATE TABLE IF NOT EXISTS audit_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aangemaakt_op DATETIME NOT NULL,
    user_id       INT UNSIGNED NULL,
    username      VARCHAR(100) NOT NULL,
    actie         VARCHAR(50)  NOT NULL,
    entiteit      VARCHAR(50)  NOT NULL,
    entiteit_id   INT UNSIGNED NULL,
    omschrijving  VARCHAR(255) NOT NULL DEFAULT '',
    ip_address    VARCHAR(45)  NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_tijd (aangemaakt_op),
    KEY idx_entiteit_actie (entiteit, actie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
