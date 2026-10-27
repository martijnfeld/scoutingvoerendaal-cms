<?php
/** Gedeeld veldschema; bestaande settingskeys blijven behouden. */
function settings_schema(): array
{
    return [
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
            ],
        ],
        'speltakken' => [
            'label' => 'Speltakken',
            'fields' => [
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

}

function system_settings_schema(): array
{
    return array_intersect_key(settings_schema(), array_flip(['algemeen', 'analytics', 'organisatie', 'footer']));
}
