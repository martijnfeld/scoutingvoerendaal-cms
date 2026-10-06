-- ============================================================
-- Scouting Voerendaal - CMS database schema + startgegevens
--
-- Importeer dit bestand eenmalig via phpMyAdmin (of vergelijkbaar)
-- in de MySQL-database die je bij je hostingprovider hebt
-- aangemaakt. Vul daarna config.php in met de databasegegevens en
-- open /install.php in de browser om je eerste beheerdersaccount
-- aan te maken.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Instellingen: alle losse teksten en gegevens van de site
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Speltakken
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS speltakken (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam         VARCHAR(100) NOT NULL,
    slug         VARCHAR(100) NOT NULL,
    kleur        VARCHAR(20)  NOT NULL DEFAULT '#00A551',
    leeftijd     VARCHAR(100) NULL,
    dag_tijd     VARCHAR(190) NULL,
    leiding      VARCHAR(255) NULL,
    toelichting  TEXT NULL,
    feed_url     VARCHAR(500) NULL,
    afmeld_url   VARCHAR(500) NULL,
    actief       TINYINT(1) NOT NULL DEFAULT 1,
    volgorde     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Documenten (PDF's zoals inschrijfformulier, uitschrijfformulier, ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam        VARCHAR(150) NOT NULL,
    bestand     VARCHAR(255) NOT NULL,
    bijgewerkt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Info-vakjes (bv. de kaarten bij "Doe mee!" / "Gratis kennismaken")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS info_cards (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sectie       VARCHAR(50)  NOT NULL DEFAULT 'lidworden',
    titel        VARCHAR(150) NOT NULL,
    tekst        MEDIUMTEXT NULL,
    afbeelding   VARCHAR(255) NULL,
    document_id  INT UNSIGNED NULL,
    knop_tekst   VARCHAR(150) NULL,
    anchor       VARCHAR(100) NULL,
    volgorde     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_sectie (sectie),
    CONSTRAINT fk_info_cards_document FOREIGN KEY (document_id)
        REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Losse pagina's (informatieve pagina's naast de hoofdpagina, bv. voor
-- leden), inhoud bewerkbaar via een uitgebreide CKEditor (met
-- afbeeldingen en YouTube-video's).
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Beheerders (admin-login voor het CMS)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(100) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    aangemaakt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Mislukte inlogpogingen (brute-force bescherming admin-login)
-- LET OP: op een bestaande installatie voer je deze CREATE TABLE
-- handmatig uit via phpMyAdmin, want dit bestand wordt alleen bij
-- de allereerste installatie automatisch geïmporteerd.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(100) NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    attempted_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_username_tijd (username, attempted_at),
    KEY idx_ip_tijd (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Toegepaste migraties (Beheerpaneel → Updates, zie sql/migrations/ en
-- includes/updater.php). Een verse installatie via install.php krijgt het
-- schema hierboven al compleet, dus alle migratiebestanden die op het
-- moment van deze install.sql al bestaan worden direct als "toegepast"
-- gemarkeerd door install.php — alleen migraties uit latere updates
-- moeten dan nog echt uitgevoerd worden.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename     VARCHAR(190) NOT NULL,
    applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Startgegevens (voorbeeldinhoud — pas alles aan via het beheerpaneel)
-- ============================================================

INSERT INTO documents (id, naam, bestand) VALUES
    (1, '4x kijken formulier', '4x-kijken-formulier.pdf'),
    (2, 'Inschrijfformulier', 'inschrijfformulier.pdf'),
    (3, 'Uitschrijfformulier', 'uitschrijfformulier.pdf')
ON DUPLICATE KEY UPDATE naam = VALUES(naam);

INSERT INTO speltakken (id, naam, slug, kleur, leeftijd, dag_tijd, leiding, toelichting, feed_url, afmeld_url, actief, volgorde) VALUES
    (1, 'Bevers', 'bevers', '#E1071A', '5–7 jaar', NULL, NULL,
        'Op dit moment hebben wij geen actieve beverspeltak. Zodra dit verandert, maken we dit bekend via onze social media. Lijkt het je leuk om leiding te worden bij de bevers (of een andere speltak)? Mail naar <a href="mailto:info@example.org">info@example.org</a>.',
        NULL, NULL, 1, 1),
    (2, 'Welpen', 'welpen', '#019340', '7–11 jaar', 'Zaterdag 10:00 – 12:00 uur', 'leiding: Anna, Bram en Chris',
        '<p>Voorbeeldtekst. Vul in het beheerpaneel een Scoutdash-feed-URL in om hier automatisch het programma te tonen.</p>',
        NULL, NULL, 1, 2),
    (3, 'Scouts', 'scouts', '#f29102', '11–15 jaar', 'Vrijdag 19:30 – 21:30 uur', 'leiding: Dana en Eva',
        '<p>Voorbeeldtekst. Vul in het beheerpaneel een Scoutdash-feed-URL in om hier automatisch het programma te tonen.</p>',
        NULL, NULL, 1, 3),
    (4, 'Explorers', 'explorers', '#9e365b', '15–18 jaar', 'Zaterdag 20:00 – 22:00 uur', 'leiding: Finn en Gijs',
        '<p>Voorbeeldtekst. Vul in het beheerpaneel een Scoutdash-feed-URL in om hier automatisch het programma te tonen.</p>',
        NULL, NULL, 1, 4),
    (5, 'Roverscouts', 'roverscouts', '#0066B2', '18–21 jaar', 'Zaterdag 18:00 – 20:00 uur', 'adviseur: Hanna',
        '<p>Voorbeeldtekst. Vul in het beheerpaneel een Scoutdash-feed-URL in om hier automatisch het programma te tonen.</p>',
        NULL, NULL, 1, 5),
    (6, 'Stam', 'stam', '#6c757d', '18+ (vrijwilligers/leden)', 'Voor vrijwilligers en leden boven de 21 jaar · ongeveer iedere 2 maanden', NULL,
        '<p>Voorbeeldtekst. Vul in het beheerpaneel een Scoutdash-feed-URL in om hier automatisch het programma te tonen.</p>',
        NULL, NULL, 1, 6)
ON DUPLICATE KEY UPDATE naam = VALUES(naam);

INSERT INTO info_cards (id, sectie, titel, tekst, afbeelding, document_id, knop_tekst, anchor, volgorde) VALUES
    (1, 'lidworden', 'Gratis kennismaken',
        '<p>Twijfel je nog? Je mag vier keer gratis en vrijblijvend meedoen voordat je je aanmeldt als lid. We hebben voor elke leeftijd een eigen groep – kijk bij <a href="#programma">Programma</a> wanneer je welkom bent.</p><p>Vul het 4x kijken formulier in, dan nemen we contact met je op om een startdatum af te spreken. Let op: tijdens deze periode sta je nog niet in de maillijst, houd dus zelf het programma in de gaten.</p>',
        NULL, 1, '4x kijken formulier (PDF)', NULL, 1),
    (2, 'lidworden', 'Inschrijven &amp; contributie',
        '<p>De contributie bedraagt &euro;9,50 per maand (automatische incasso). Lukt betalen niet? Kijk op <a href="https://www.leergeld.nl/parkstad" target="_blank" rel="noopener">leergeld.nl/parkstad</a> of <a href="https://www.samenvoorallekinderen.nl" target="_blank" rel="noopener">samenvoorallekinderen.nl</a>.</p><p>Download het inschrijfformulier, vul het in en mail het naar <a href="mailto:info@example.org">info@example.org</a>.</p>',
        NULL, 2, 'Inschrijfformulier (PDF)', 'inschrijven', 2),
    (3, 'lidworden', 'Vrijwilliger worden',
        '<p>Met jouw interesse en talenten als uitgangspunt bekijken we graag welke rol bij je past – er is voor elk wat wils. Mail naar <a href="mailto:info@example.org">info@example.org</a>.</p>',
        NULL, NULL, NULL, NULL, 3),
    (4, 'lidworden', 'Uitschrijven',
        '<p>Opzeggen kan per begin van de eerstvolgende kalendermaand; we hanteren een opzegtermijn van een maand. Na verwerking ontvang je een bevestiging per mail.</p><p>Vul het digitale uitschrijfformulier in, onderteken het en verzend het. Let op: lopende aanmeldingen voor activiteiten vervallen hiermee niet automatisch – neem daarvoor contact op met de leiding.</p>',
        NULL, 3, 'Uitschrijfformulier (PDF)', NULL, 4)
ON DUPLICATE KEY UPDATE titel = VALUES(titel);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_title', 'Scouting Voorbeeld | Een wereld vol avontuur'),
    ('meta_description', 'Scouting Voorbeeld biedt kinderen en jongeren van 5 tot 21 jaar wekelijks een avontuurlijk programma. Bekijk het programma per speltak, word lid of huur ons clubgebouw.'),
    ('site_url', 'https://www.example.org/'),
    ('og_image', ''),
    ('ga_measurement_id', ''),
    ('notice_bar_enabled', '0'),
    ('notice_bar_text', '<strong>Let op:</strong> dit is een voorbeeldmelding.'),

    ('logo_image', ''),
    ('org_naam', 'Scouting Voorbeeld'),
    ('org_straat', 'Voorbeeldstraat 1'),
    ('org_postcode', '1234 AB'),
    ('org_plaats', 'Voorbeeldstad'),
    ('org_land', 'NL'),
    ('org_email', 'info@example.org'),
    ('org_telefoon', '+31201234567'),
    ('org_telefoon_display', '020 - 123 45 67'),
    ('org_telefoon_note', '(alleen bereikbaar tijdens opkomsttijden)'),
    ('org_maps_link', 'https://www.google.com/maps'),
    ('org_gebouw_extra', '(Bereikbaar via de poort aan de achterzijde)'),
    ('correspondentie_adres', 'Postbus 1<br>1234 AB Voorbeeldstad'),
    ('declaratie_link', 'https://www.example.org/declareren'),
    ('social_facebook', 'https://facebook.com/'),
    ('social_instagram', 'https://instagram.com/'),
    ('social_youtube', 'https://www.youtube.com/'),

    ('hero_title', 'Een wereld vol avontuur'),
    ('hero_text', 'Scouting Voorbeeld biedt kinderen en jongeren van 5 tot 21 jaar elke week een avontuurlijk programma vol spel, vriendschap en uitdaging.'),
    ('hero_btn1_text', 'Doe mee!'),
    ('hero_btn1_link', '#lidworden'),
    ('hero_btn2_text', 'Bekijk het programma'),
    ('hero_btn2_link', '#programma'),

    ('feature1_image', ''),
    ('feature1_alt', 'Bever met microscoop'),
    ('feature1_title', 'Wat is Scouting?'),
    ('feature1_text', 'Scouting biedt leuke en spannende activiteiten voor meiden en jongens vanaf 5 jaar waarmee ze worden uitgedaagd zich persoonlijk te ontwikkelen.<br><a href="#lidworden">Verder lezen &rarr;</a>'),

    ('feature2_image', ''),
    ('feature2_alt', 'Scouts activiteit'),
    ('feature2_title', 'Kom kijken!'),
    ('feature2_text', 'Bij Scouting Voorbeeld ben je altijd welkom om vier keer gratis te komen kijken. Stuur wel vooraf even een mailtje.<br><a href="#lidworden">Meer info &rarr;</a>'),

    ('feature3_image', ''),
    ('feature3_alt', 'Bevers buiten'),
    ('feature3_title', 'Snel naar'),
    ('feature3_text', 'Direct naar het programma, contactgegevens of informatie over verhuur van ons clubgebouw.<br><a href="#programma">Programma</a> &middot; <a href="#contact">Contact</a> &middot; <a href="#verhuur">Verhuur</a>'),

    ('programma_intro', 'Het actuele programma per speltak, rechtstreeks opgehaald uit onze planning.'),
    ('programma_footer_note', 'Het programma is ook te zien in de Scouting-app.'),

    ('lidworden_intro', 'Nieuwsgierig naar Scouting? Je bent van harte welkom.'),
    ('lidworden_tekst_1', 'Scouting staat voor uitdaging! Scouting biedt leuke en spannende activiteiten waarmee meiden en jongens worden uitgedaagd zich persoonlijk te ontwikkelen. Al vanaf 5 jaar ben je welkom bij Scouting, waar je iedere week weer uitdagende activiteiten doet. Bij Scouting Voorbeeld willen we kinderen en jongeren iedere week een uitdagend spelaanbod bieden. Lid worden? <a href="#inschrijven">Klik hier</a> voor meer informatie!'),
    ('lidworden_tekst_2', 'Bij scouting worden de leden onderverdeeld in speltakken. Dit zijn groepen gebaseerd op de leeftijd van de kinderen. De jongste groep heet de bevers, daarna komen de welpen, vervolgens de scouts, de explorers en de roverscouts. Afhankelijk van je speltak ben je bezig met andere activiteiten. De leeftijden per speltak zie je hier onder. Bekijk ook eens het <a href="#programma">programma</a> van de verschillende speltakken.'),

    ('verhuur_intro', 'Ons clubgebouw in Voorbeeldstad is te huur voor scoutingverenigingen en andere jeugdgroepen.'),
    ('verhuur_photo', ''),
    ('verhuur_voorzieningen', '<ul><li>Grote zaal voor max. 25 personen</li><li>Slaap-/activiteitenzaal (15 x 6,5 meter)</li><li>Keuken/stafkamer met complete kookapparatuur</li><li>2 reguliere toiletten + 1 rolstoeltoegankelijk toilet</li><li>Douche en wasgelegenheid (4 kranen)</li><li>Verlichte overkapping en grasveld (tenten toegestaan)</li><li>WiFi en parkeergelegenheid</li></ul>'),
    ('verhuur_adres_tekst', 'Adres: Voorbeeldstraat 1, Voorbeeldstad – ca. 10 minuten lopen vanaf het station.'),
    ('verhuur_voorwaarden', '<ul><li>Verhuur vanaf 2 nachten, uitsluitend in de zomervakantie</li><li>Alleen aan scoutingverenigingen en andere jeugdgroepen</li><li>Niet beschikbaar voor feesten; alcoholgebruik niet toegestaan</li><li>Incidentele verhuur (dagdeel) op aanvraag mogelijk</li></ul>'),
    ('verhuur_contact_tekst', 'Interesse of vragen over beschikbaarheid?'),
    ('verhuur_email', 'verhuur@example.org'),

    ('contact_intro', 'We horen graag van je.'),

    ('footer_copyright', 'Copyright &copy; {jaar} Scouting Voorbeeld')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
