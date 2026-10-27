-- Bestaande programmakoppelingen verwijzen voortaan naar de eigen pagina.
-- Labels, dropdowns, zichtbaarheid en volgorde blijven behouden.
UPDATE header_menu_items
SET link_type = 'programma', link_value = ''
WHERE link_type = 'section' AND link_value = 'programma';
