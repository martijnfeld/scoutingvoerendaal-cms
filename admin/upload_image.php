<?php
/**
 * Upload-endpoint voor afbeeldingen die vanuit CKEditor (in het "inhoud"-veld
 * van pagina's, en andere rich-text velden) worden geplakt of geüpload. Zie
 * assets/js/admin.js voor de CKEditor-upload-adapter die hiernaartoe post.
 *
 * Geeft een absolute URL terug (met site_url) in plaats van het relatieve
 * pad dat handle_upload() teruggeeft: de opgeslagen HTML wordt zowel getoond
 * in dit admin-paneel (onder /admin/) als op de publieke pagina (op de
 * projectroot), en een relatief pad zou in een van de twee contexten breken.
 */

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Niet ingelogd.']]);
    exit;
}

$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Ongeldige aanvraag (csrf-token klopt niet). Ververs de pagina en probeer het opnieuw.']]);
    exit;
}

try {
    $relativeUrl = handle_upload('upload', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    if ($relativeUrl === null) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Geen bestand ontvangen.']]);
        exit;
    }
    $absoluteUrl = rtrim(get_setting('site_url'), '/') . '/' . ltrim($relativeUrl, '/');
    echo json_encode(['url' => $absoluteUrl]);
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
}
