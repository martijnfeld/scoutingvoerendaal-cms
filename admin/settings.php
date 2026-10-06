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
            'og_image' => ['label' => 'Deelafbeelding (social media)', 'type' => 'image'],
            'ga_measurement_id' => ['label' => 'Google Analytics Measurement ID', 'type' => 'text', 'hint' => 'Leeg laten om Google Analytics uit te schakelen.'],
            'notice_bar_enabled' => ['label' => 'Meldingsbalk bovenaan tonen', 'type' => 'checkbox'],
            'notice_bar_text' => ['label' => 'Tekst meldingsbalk', 'type' => 'html'],
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
                foreach ($SETTINGS_SCHEMA[$activeTab]['fields'] as $key => $field) {
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
