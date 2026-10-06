<?php
/**
 * Bepaalt in welk (Europees) land een IP-adres ligt, voor de geoblokkade op
 * admin/login.php.
 *
 * Werkt volledig offline met meegeleverde bestanden in includes/geo/ (geen
 * externe API: er gaan dus geen IP-adressen naar derden, en uitgaand HTTPS
 * is niet nodig). Die bestanden worden gegenereerd uit de openbare
 * "delegated"-statistieken van de vijf regionale internetregisters (RIPE,
 * ARIN, APNIC, LACNIC, AFRINIC) met tools/build_geo_europe.php, en komen
 * met elke release mee via Beheerpaneel → Updates.
 *
 * Bestandsformaat: gesorteerde records met vaste lengte, zodat we met
 * fseek() binair kunnen zoeken zonder het hele bestand in te laden:
 *   europe-ipv4.bin  — 4 bytes begin + 4 bytes eind + 2 bytes landcode
 *   europe-ipv6.bin  — 8 bytes begin + 8 bytes eind + 2 bytes landcode
 *                      (alleen de eerste 64 bits; RIR's delegeren nooit
 *                      kleiner dan een /64)
 * Adressen zijn big-endian, dus een gewone strcmp() op de bytes vergelijkt
 * ze correct — ook op 32-bit PHP, waar 64-bit integers niet bestaan.
 */

const GEO_DATA_DIR = __DIR__ . '/geo';

/**
 * Landen (ISO 3166-1) die als "Europa" gelden. Dit is de geografische
 * definitie; site-eigenaren kunnen de lijst inperken via
 * ADMIN_LOGIN_COUNTRIES in config.local.php. "EU" is geen land maar wordt
 * door RIPE gebruikt voor pan-Europese toewijzingen.
 */
const GEO_EUROPE_COUNTRIES = [
    'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE',
    'DK', 'EE', 'ES', 'EU', 'FI', 'FO', 'FR', 'GB', 'GG', 'GI', 'GR', 'HR',
    'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD',
    'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI',
    'SJ', 'SK', 'SM', 'UA', 'VA', 'XK',
];

/**
 * Geeft de landcode van een IP-adres terug als dat in een Europese range
 * valt, anders null (buiten Europa, onbekend, of geen geldig adres).
 */
function geo_europe_country(string $ip): ?string
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return null;
    }

    // IPv4-mapped IPv6-adres (::ffff:a.b.c.d) behandelen als IPv4.
    if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        $bin = substr($bin, 12);
    }

    if (strlen($bin) === 4) {
        return geo_lookup_range_file(GEO_DATA_DIR . '/europe-ipv4.bin', $bin, 4);
    }
    return geo_lookup_range_file(GEO_DATA_DIR . '/europe-ipv6.bin', substr($bin, 0, 8), 8);
}

/** Binair zoeken in een rangebestand (zie formaat bovenaan dit bestand). */
function geo_lookup_range_file(string $file, string $key, int $keyLength): ?string
{
    $recordSize = $keyLength * 2 + 2;
    $handle = @fopen($file, 'rb');
    if ($handle === false) {
        return null;
    }

    $low = 0;
    $high = (int) (filesize($file) / $recordSize) - 1;
    $match = null;

    // Zoek het laatste record waarvan het begin <= $key.
    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);
        fseek($handle, $mid * $recordSize);
        $record = fread($handle, $recordSize);
        if (strcmp(substr($record, 0, $keyLength), $key) <= 0) {
            $match = $record;
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }
    fclose($handle);

    if ($match !== null && strcmp($key, substr($match, $keyLength, $keyLength)) <= 0) {
        return substr($match, $keyLength * 2, 2);
    }
    return null;
}

/** Of de geo-databestanden aanwezig zijn (bv. niet na een half geüploade update). */
function geo_data_available(): bool
{
    return is_file(GEO_DATA_DIR . '/europe-ipv4.bin') && is_file(GEO_DATA_DIR . '/europe-ipv6.bin');
}

/**
 * Of vanaf dit IP-adres ingelogd mag worden op het beheerpaneel.
 *
 * Laat altijd door: als de blokkade uit staat (ADMIN_GEO_BLOCK), voor
 * privé-/gereserveerde adressen (lokale Docker-omgeving, interne proxy), en
 * als de databestanden ontbreken — liever tijdelijk geen geoblokkade dan
 * dat de beheerder zichzelf buitensluit door een half geüploade update. De
 * brute-force-blokkade en wachtwoordeisen blijven dan gewoon gelden.
 */
function admin_login_allowed_from_ip(string $ip): bool
{
    // defined()-check: een oude config.php (half geüploade update) kent deze constante nog niet.
    if (defined('ADMIN_GEO_BLOCK') && !ADMIN_GEO_BLOCK) {
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    if (!geo_data_available()) {
        return true;
    }

    $allowed = defined('ADMIN_LOGIN_COUNTRIES')
        ? array_map('trim', explode(',', strtoupper(ADMIN_LOGIN_COUNTRIES)))
        : GEO_EUROPE_COUNTRIES;

    $country = geo_europe_country($ip);
    return $country !== null && in_array($country, $allowed, true);
}
