<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Teksten & gegevens';

$SETTINGS_SCHEMA = [
    'algemeen' => [
        'label' => 'Algemeen',
        'fields' => [
            'site_title' => ['label' => 'Site titel', 'type' => 'text', 'hint' => 'Zichtbaar in het browsertabblad en zoekmachines.'],
            'meta_description' => ['label' => 'Meta omschrijving (SEO)', 'type' => 'textarea'],
            'site_url' => ['label' => 'Website-adres', 'type' => 'text', 'hint' => 'Bijvoorbeeld https://scoutingvoerendaal.nl/'],
            'og_image' => ['label' => 'Deelafbeelding (social media)', 'type' => 'image', 'hint' => 'Getoond als de website wordt gedeeld (WhatsApp, Facebook, …). Bij voorkeur 1200 × 630 pixels. Losse pagina\'s kunnen een eigen afbeelding hebben.'],
            'google_site_verification' => ['label' => 'Google Search Console - verificatiecode', 'type' => 'text', 'hint' => 'In Search Console: eigendom toevoegen als "URL-voorvoegsel", kies verificatie via "HTML-tag" en plak die tag (of alleen de code) hier. Leeg laten als je Search Console niet gebruikt.', 'normalize' => 'google_site_verification_normalize'],
            'notice_bar_enabled' => ['label' => 'Meldingsbalk bovenaan tonen', 'type' => 'checkbox'],
            'notice_bar_text' => ['label' => 'Tekst meldingsbalk', 'type' => 'html'],
        ],
    ],
    'analytics' => [
        'label' => 'Statistieken & cookies',
        'intro' => '<p><strong>Google Analytics instellen</strong></p>'
            . '<ol>'
            . '<li>Log in op <a href="https://analytics.google.com/" target="_blank" rel="noopener">analytics.google.com</a> en maak (als je die nog niet hebt) een account en een <em>property</em> aan voor de website.</li>'
            . '<li>Kies bij <em>Beheer → Gegevensstreams</em> voor <em>Web</em> en vul het adres van de website in.</li>'
            . '<li>Kopieer het <em>measurement-ID</em> van die stream (begint met <code>G-</code>) en plak het hieronder.</li>'
            . '</ol>'
            . '<p>Zodra er een ID staat, toont de website een cookiebanner. Google Analytics draait in <em>Consent Mode</em>: zonder toestemming worden er geen analytische cookies geplaatst, en advertentiefuncties staan altijd uit. Bezoekers kunnen hun keuze later aanpassen via <em>Cookie-instellingen</em> onderaan de pagina.</p>'
            . '<p>Vermeld Google Analytics in je privacyverklaring en vul hieronder de link ernaar in. Meer uitleg (ook over de AVG-instellingen in Google Analytics) staat in <code>INSTALL.md</code>, onder <em>Google Analytics</em>.</p>',
        'fields' => [
            'ga_measurement_id' => ['label' => 'Google Analytics measurement-ID', 'type' => 'text', 'hint' => 'Bijvoorbeeld G-AB12CD34EF. Leeg laten om Google Analytics en de cookiebanner uit te schakelen.', 'normalize' => 'ga_measurement_id_normalize'],
            'cookie_privacy_link' => ['label' => 'Link naar privacyverklaring', 'type' => 'text', 'hint' => 'Een volledig adres (https://…) of de slug van een pagina op deze site, bijvoorbeeld privacy. Wordt in de cookiebanner getoond.', 'normalize' => 'cookie_privacy_link_normalize'],
            'cookie_banner_title' => ['label' => 'Cookiebanner - titel', 'type' => 'text', 'hint' => 'Leeg laten voor: ' . COOKIE_BANNER_DEFAULTS['cookie_banner_title']],
            'cookie_banner_text' => ['label' => 'Cookiebanner - tekst', 'type' => 'textarea', 'hint' => 'Leg kort uit waarvoor je de cookies gebruikt. Eenvoudige HTML (zoals <strong>) is toegestaan.'],
            'cookie_banner_accept' => ['label' => 'Cookiebanner - knop accepteren', 'type' => 'text', 'hint' => 'Leeg laten voor: ' . COOKIE_BANNER_DEFAULTS['cookie_banner_accept']],
            'cookie_banner_reject' => ['label' => 'Cookiebanner - knop weigeren', 'type' => 'text', 'hint' => 'Leeg laten voor: ' . COOKIE_BANNER_DEFAULTS['cookie_banner_reject']],
            'cookie_banner_settings' => ['label' => 'Cookiebanner - knop instellingen', 'type' => 'text', 'hint' => 'Leeg laten voor: ' . COOKIE_BANNER_DEFAULTS['cookie_banner_settings']],
        ],
    ],
    'organisatie' => [
        'label' => 'Organisatie & contact',
        'fields' => [
            'logo_image' => ['label' => 'Logo', 'type' => 'image'],
            'org_naam' => ['label' => 'Naam organisatie', 'type' => 'text'],
            'org_straat' => ['label' => 'Straat + huisnummer (clubgebouw)', 'type' => 'text'],
            'org_postcode' => ['label' => 'Postcode', 'type' => 'text'],
            'org_plaats' => ['label' => 'Plaats', 'type' => 'text'],
            'org_land' => ['label' => 'Landcode', 'type' => 'text', 'hint' => 'Bijvoorbeeld NL'],
            'org_maps_link' => ['label' => 'Google Maps link', 'type' => 'text'],
            'org_gebouw_extra' => ['label' => 'Extra routebeschrijving', 'type' => 'text'],
            'org_email' => ['label' => 'E-mailadres', 'type' => 'text'],
            'org_telefoon' => ['label' => 'Telefoonnummer (internationaal, voor structured data)', 'type' => 'text', 'hint' => 'Bijvoorbeeld +31455751117'],
            'org_telefoon_display' => ['label' => 'Telefoonnummer (weergave)', 'type' => 'text', 'hint' => 'Bijvoorbeeld 045 - 575 11 17'],
            'org_telefoon_note' => ['label' => 'Toelichting bij telefoonnummer', 'type' => 'text'],
            'correspondentie_adres' => ['label' => 'Correspondentieadres', 'type' => 'html'],
            'declaratie_link' => ['label' => 'Link declaratieformulier', 'type' => 'text'],
            'social_facebook' => ['label' => 'Facebook URL', 'type' => 'text'],
            'social_instagram' => ['label' => 'Instagram URL', 'type' => 'text'],
            'social_youtube' => ['label' => 'YouTube URL', 'type' => 'text'],
        ],
    ],
    'hero' => [
        'label' => 'Hero (bovenaan)',
        'fields' => [
            'hero_title' => ['label' => 'Titel', 'type' => 'text'],
            'hero_text' => ['label' => 'Tekst', 'type' => 'textarea'],
            'hero_btn1_text' => ['label' => 'Knop 1 - tekst', 'type' => 'text'],
            'hero_btn1_link' => ['label' => 'Knop 1 - link', 'type' => 'text'],
            'hero_btn2_text' => ['label' => 'Knop 2 - tekst', 'type' => 'text'],
            'hero_btn2_link' => ['label' => 'Knop 2 - link', 'type' => 'text'],
        ],
    ],
    'kennismaken' => [
        'label' => 'Kennismaken-strip',
        'fields' => [
            'feature1_image' => ['label' => 'Kaart 1 - afbeelding', 'type' => 'image'],
            'feature1_alt' => ['label' => 'Kaart 1 - alt-tekst afbeelding', 'type' => 'text'],
            'feature1_title' => ['label' => 'Kaart 1 - titel', 'type' => 'text'],
            'feature1_text' => ['label' => 'Kaart 1 - tekst', 'type' => 'html'],
            'feature2_image' => ['label' => 'Kaart 2 - afbeelding', 'type' => 'image'],
            'feature2_alt' => ['label' => 'Kaart 2 - alt-tekst afbeelding', 'type' => 'text'],
            'feature2_title' => ['label' => 'Kaart 2 - titel', 'type' => 'text'],
            'feature2_text' => ['label' => 'Kaart 2 - tekst', 'type' => 'html'],
            'feature3_image' => ['label' => 'Kaart 3 - afbeelding', 'type' => 'image'],
            'feature3_alt' => ['label' => 'Kaart 3 - alt-tekst afbeelding', 'type' => 'text'],
            'feature3_title' => ['label' => 'Kaart 3 - titel', 'type' => 'text'],
            'feature3_text' => ['label' => 'Kaart 3 - tekst', 'type' => 'html'],
        ],
    ],
    'programma' => [
        'label' => 'Programma',
        'fields' => [
            'programma_intro' => ['label' => 'Introtekst', 'type' => 'textarea'],
            'programma_footer_note' => ['label' => 'Tekst onderaan programma', 'type' => 'text'],
            'speltakken_intro' => ['label' => 'Introtekst overzichtspagina speltakken', 'type' => 'textarea', 'hint' => 'Getoond bovenaan /speltakken en gebruikt als omschrijving in zoekmachines.'],
        ],
    ],
    'lidworden' => [
        'label' => 'Lid worden',
        'fields' => [
            'lidworden_intro' => ['label' => 'Introtekst (onder titel)', 'type' => 'text'],
            'lidworden_tekst_1' => ['label' => 'Alinea 1', 'type' => 'html'],
            'lidworden_tekst_2' => ['label' => 'Alinea 2', 'type' => 'html'],
        ],
    ],
    'verhuur' => [
        'label' => 'Verhuur',
        'fields' => [
            'verhuur_intro' => ['label' => 'Introtekst', 'type' => 'textarea'],
            'verhuur_photo' => ['label' => 'Foto clubgebouw', 'type' => 'image'],
            'verhuur_voorzieningen' => ['label' => 'Voorzieningen', 'type' => 'html'],
            'verhuur_adres_tekst' => ['label' => 'Adrestekst', 'type' => 'text'],
            'verhuur_voorwaarden' => ['label' => 'Voorwaarden', 'type' => 'html'],
            'verhuur_contact_tekst' => ['label' => 'Contacttekst', 'type' => 'text'],
            'verhuur_email' => ['label' => 'E-mailadres verhuur', 'type' => 'text'],
        ],
    ],
    'contact' => [
        'label' => 'Contact',
        'fields' => [
            'contact_intro' => ['label' => 'Introtekst', 'type' => 'textarea'],
        ],
    ],
    'footer' => [
        'label' => 'Footer',
        'fields' => [
            'footer_copyright' => ['label' => 'Copyright-tekst', 'type' => 'html', 'hint' => 'Gebruik {jaar} voor het huidige jaartal.'],
        ],
    ],
];

$tabs = array_keys($SETTINGS_SCHEMA);
$activeTab = $_GET['tab'] ?? $tabs[0];
if (!isset($SETTINGS_SCHEMA[$activeTab])) {
    $activeTab = $tabs[0];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ongeldige aanvraag. Ververs de pagina en probeer het opnieuw.';
    } else {
        $postedTab = $_POST['tab'] ?? $activeTab;
        if (isset($SETTINGS_SCHEMA[$postedTab])) {
            $activeTab = $postedTab;
            try {
                // Eerst alle velden met een normalize-functie controleren, zodat
                // er bij een fout niets (half) wordt opgeslagen.
                $normalized = [];
                foreach ($SETTINGS_SCHEMA[$activeTab]['fields'] as $key => $field) {
                    if (!empty($field['normalize']) && array_key_exists($key, $_POST)) {
                        $normalized[$key] = $field['normalize']((string) $_POST[$key]);
                    }
                }
                foreach ($SETTINGS_SCHEMA[$activeTab]['fields'] as $key => $field) {
                    if (array_key_exists($key, $normalized)) {
                        set_setting($key, $normalized[$key]);
                        continue;
                    }
                    if ($field['type'] === 'checkbox') {
                        set_setting($key, isset($_POST[$key]) ? '1' : '0');
                        continue;
                    }
                    if ($field['type'] === 'image') {
                        $uploaded = handle_upload("upload_$key", ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        if ($uploaded !== null) {
                            set_setting($key, $uploaded);
                            continue;
                        }
                    }
                    if (array_key_exists($key, $_POST)) {
                        set_setting($key, trim((string) $_POST[$key]));
                    }
                }
                flash_set('success', 'Instellingen opgeslagen.');
                header('Location: settings.php?tab=' . urlencode($activeTab));
                exit;
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$existingImages = get_uploaded_files(['jpg', 'jpeg', 'png', 'gif', 'webp']);

include __DIR__ . '/includes/layout_top.php';
?>
<div class="tabs">
  <?php foreach ($SETTINGS_SCHEMA as $key => $group): ?>
  <a href="settings.php?tab=<?= e($key) ?>" class="<?= $key === $activeTab ? 'active' : '' ?>"><?= e($group['label']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($error): ?><div class="admin-flash admin-flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!empty($SETTINGS_SCHEMA[$activeTab]['intro'])): ?>
<div class="admin-card">
  <?= $SETTINGS_SCHEMA[$activeTab]['intro'] /* vaste tekst uit deze file */ ?>
</div>
<?php endif; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
    <?php foreach ($SETTINGS_SCHEMA[$activeTab]['fields'] as $key => $field):
        $value = get_setting($key);
    ?>
    <div class="field">
      <label for="f_<?= e($key) ?>"><?= e($field['label']) ?></label>
      <?php if ($field['type'] === 'html'): ?>
        <textarea class="rich-text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="6"><?= e($value) ?></textarea>
      <?php elseif ($field['type'] === 'textarea'): ?>
        <textarea id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($value) ?></textarea>
      <?php elseif ($field['type'] === 'checkbox'): ?>
        <label class="field-inline-label"><input type="checkbox" id="f_<?= e($key) ?>" name="<?= e($key) ?>" <?= $value === '1' ? 'checked' : '' ?>> Ingeschakeld</label>
      <?php elseif ($field['type'] === 'image'): ?>
        <?php if ($value): ?><div style="margin-bottom:8px;"><img src="../<?= e($value) ?>" alt="" class="thumb" style="width:120px; height:90px;"></div><?php endif; ?>
        <input type="text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <select class="file-picker" data-target="f_<?= e($key) ?>" style="margin-top:6px;">
          <option value="">— of kies een reeds geüpload bestand —</option>
          <?php foreach ($existingImages as $img): ?>
          <option value="<?= e($img['path']) ?>"><?= e($img['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Of upload hieronder een nieuw bestand:</span>
        <input type="file" name="upload_<?= e($key) ?>" accept="image/*" style="margin-top:6px;">
      <?php else: ?>
        <input type="text" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>">
      <?php endif; ?>
      <?php if (!empty($field['hint'])): ?><span class="hint"><?= e($field['hint']) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn">Opslaan</button>
  </form>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
