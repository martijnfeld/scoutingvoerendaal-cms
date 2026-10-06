-- Opkomsten-feeds worden voortaan alleen nog via https opgehaald (zie
-- scoutdash_http_get()). Bestaande http://-feed-URL's omzetten naar https://,
-- anders laden die na de update ongemerkt niet meer. Alleen het schema wordt
-- aangepast; de rest van de URL blijft ongewijzigd.
UPDATE speltakken
   SET feed_url = CONCAT('https://', SUBSTRING(TRIM(feed_url), 8))
 WHERE LOWER(LEFT(TRIM(feed_url), 7)) = 'http://';
