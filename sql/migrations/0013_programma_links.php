<?php
/**
 * Het programma staat niet meer als sectie op de homepage maar op /programma.
 * Oude ankers naar #programma in beheerde links (heroknoppen, eigen menu-URL's)
 * en in HTML-teksten (instellingen, info-vakjes, pagina's) wijzen voortaan naar
 * die pagina. Alle andere inhoud blijft ongewijzigd; opnieuw draaien is veilig.
 *
 * PHP i.p.v. SQL zodat elke statement apart wordt uitgevoerd en een fout niet
 * door PDO gemist kan worden.
 */
$oldLinks = "('#programma', './#programma', '/#programma', 'index.php#programma')";
$replaceHtml = function (string $column): string {
    return "REPLACE(REPLACE({$column}, 'href=\"./#programma\"', 'href=\"programma\"'), 'href=\"#programma\"', 'href=\"programma\"')";
};
$hasOldHtml = function (string $column): string {
    return "({$column} LIKE '%href=\"#programma\"%' OR {$column} LIKE '%href=\"./#programma\"%')";
};

db()->exec("UPDATE settings SET setting_value = 'programma' WHERE setting_value IN {$oldLinks}");
// Directe speltakankers (bv. een heroknop naar #sk-welpen) behouden hun speltak.
db()->exec("UPDATE settings SET setting_value = CONCAT('programma', setting_value) WHERE setting_value REGEXP '^#sk-[a-z0-9-]+$'");
db()->exec("UPDATE header_menu_items SET link_value = CONCAT('programma', link_value) WHERE link_type = 'url' AND link_value REGEXP '^#sk-[a-z0-9-]+$'");
db()->exec('UPDATE settings SET setting_value = ' . $replaceHtml('setting_value') . ' WHERE ' . $hasOldHtml('setting_value'));
db()->exec('UPDATE info_cards SET tekst = ' . $replaceHtml('tekst') . ' WHERE ' . $hasOldHtml('tekst'));
db()->exec('UPDATE pages SET inhoud = ' . $replaceHtml('inhoud') . ' WHERE ' . $hasOldHtml('inhoud'));
db()->exec("UPDATE header_menu_items SET link_type = 'programma', link_value = '' WHERE link_type = 'url' AND link_value IN {$oldLinks}");
