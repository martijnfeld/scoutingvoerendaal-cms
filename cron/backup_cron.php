<?php
/**
 * Verouderd startpunt, bewaard zodat bestaande cronjobs blijven werken die
 * nog naar cron/backup_cron.php wijzen (zelfde ?key=, zelfde CLI-gebruik).
 * Dit voert nu gewoon cron/cron.php uit: dat bepaalt zelf of er een back-up
 * en/of opruimronde aan de beurt is. Stel nieuwe cronjobs in op
 * cron/cron.php (dagelijks) — zie INSTALL.md, hoofdstuk "Geplande taken".
 */

require __DIR__ . '/cron.php';
