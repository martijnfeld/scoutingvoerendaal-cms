-- Verificatiecode van Google Search Console (Beheerpaneel → Teksten &
-- gegevens → Algemeen). INSERT IGNORE laat een al bestaande waarde ongemoeid.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('google_site_verification', '');
