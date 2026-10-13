-- Deelafbeelding (og:image) per losse pagina, voor social media. Leeg =
-- de algemene deelafbeelding uit Teksten & gegevens → Algemeen.
ALTER TABLE pages ADD COLUMN og_afbeelding VARCHAR(255) NULL AFTER meta_omschrijving;
