-- Teksten voor de cookiebanner bij Google Analytics (Beheerpaneel →
-- Teksten & gegevens → Statistieken & cookies). Zelfde standaardwaarden als
-- sql/install.sql; INSERT IGNORE laat al bestaande waarden ongemoeid.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('ga_measurement_id', ''),
    ('cookie_banner_title', 'Wij gebruiken cookies'),
    ('cookie_banner_text', 'Met jouw toestemming gebruiken we Google Analytics om anoniem bij te houden hoe onze website wordt gebruikt. Zonder toestemming plaatsen we geen analytische cookies.'),
    ('cookie_banner_accept', 'Accepteren'),
    ('cookie_banner_reject', 'Weigeren'),
    ('cookie_banner_settings', 'Instellingen'),
    ('cookie_privacy_link', '');
