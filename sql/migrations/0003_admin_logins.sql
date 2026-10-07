-- Inloggeschiedenis: één rij per geslaagde login op het beheerpaneel,
-- getoond op Beheerpaneel → Accounts (vastgelegd in admin/login.php).
CREATE TABLE IF NOT EXISTS admin_logins (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    username     VARCHAR(100) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    user_agent   VARCHAR(255) NOT NULL DEFAULT '',
    ingelogd_op  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user_tijd (user_id, ingelogd_op),
    KEY idx_tijd (ingelogd_op)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
