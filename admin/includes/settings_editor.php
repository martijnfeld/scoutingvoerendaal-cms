<?php
/** Slaat uitsluitend velden van de server-side geselecteerde groep op. */
function save_settings_group(array $group, string $tab, string $redirect): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    if (!csrf_verify()) {
        return 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    }
    if (($_POST['tab'] ?? null) !== $tab) {
        return 'Ongeldig tabblad. Ververs de pagina en probeer het opnieuw.';
    }
    try {
        // Valideer en verwerk uploads vóór de eerste databasewijziging.
        $values = [];
        foreach ($group['fields'] as $key => $field) {
            if (isset($_POST[$key]) && !is_string($_POST[$key])) {
                throw new RuntimeException('Ongeldige veldwaarde.');
            }
            if ($field['type'] === 'checkbox') {
                $values[$key] = isset($_POST[$key]) ? '1' : '0';
            } elseif (array_key_exists($key, $_POST)) {
                $values[$key] = !empty($field['normalize'])
                    ? $field['normalize']($_POST[$key]) : trim($_POST[$key]);
            }
        }
        foreach ($group['fields'] as $key => $field) {
            if ($field['type'] === 'image') {
                $uploaded = handle_upload("upload_$key", ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                if ($uploaded !== null) {
                    $values[$key] = $uploaded;
                }
            }
        }
        db()->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                set_setting($key, $value);
            }
            audit_log('settings', 'instelling', null, 'Instellingen opgeslagen (tab "' . $tab . '")');
            db()->commit();
        } catch (Throwable $exception) {
            db()->rollBack();
            throw $exception;
        }
        flash_set('success', 'Instellingen opgeslagen.');
        header('Location: ' . $redirect);
        exit;
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }
}
