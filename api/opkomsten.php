<?php
/**
 * Eigen tussenlaag voor de opkomsten-programma's: bundelt de feeds van alle
 * speltakken in één response en cachet die 10 minuten (zie
 * includes/scoutdash.php), zodat de website niet bij elk bezoek rechtstreeks
 * en per speltak bij Scoutdash uitkomt.
 */
require_once __DIR__ . '/../includes/scoutdash.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(get_alle_opkomsten(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
