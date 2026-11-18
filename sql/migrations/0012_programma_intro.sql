-- Vervang de oude standaardintro; behoud zelf aangepaste teksten.
UPDATE settings
SET setting_value = 'Bekijk het actuele programma per speltak.'
WHERE setting_key = 'programma_intro'
  AND setting_value = 'Het actuele programma per speltak, rechtstreeks opgehaald uit onze planning.';
