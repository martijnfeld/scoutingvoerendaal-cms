<?php
require_once __DIR__ . '/functions.php';

/**
 * Haalt opkomsten van alle actieve speltakken bij Scoutdash op en houdt
 * dat 10 minuten in een bestandscache. Zo doet de website maar één (trage)
 * externe aanroep per speltak per 10 minuten in plaats van bij elk bezoek,
 * en blijft het laatst bekende programma zichtbaar als Scoutdash even niet
 * bereikbaar is.
 */

const OPKOMSTEN_CACHE_TTL = 600; // 10 minuten

// Ophogen zodra de opschoning van de feed-inhoud verandert: een cache met
// een oudere versie wordt bij het inlezen opnieuw opgeschoond en als
// verlopen behandeld (zie opkomsten_read_cache()).
const OPKOMSTEN_CACHE_VERSION = 2;

/* ------------------------------------------------------------------ *
 * Opschonen van HTML uit de Scoutdash-feed
 *
 * De "omschrijving" van een opkomst is HTML uit de CKEditor van Scoutdash
 * en wordt in main.js als HTML in de pagina gezet. Die inhoud komt van
 * buiten (iedereen met schrijfrechten in Scoutdash), dus alles wat
 * JavaScript kan uitvoeren wordt hier verwijderd. Gewone tags en
 * attributen (inclusief style) blijven staan.
 *
 * We serialiseren de DOM zelf in plaats van saveHTML() te gebruiken: zo
 * wordt alle tekst en elke attribuutwaarde volledig ge-escaped, en kan een
 * verschil tussen hoe libxml en een browser de HTML parsen (mutation-XSS)
 * geen nieuwe tags opleveren.
 * ------------------------------------------------------------------ */

/**
 * Elementen die inclusief inhoud verwijderd worden: ze voeren script uit,
 * laden actieve inhoud, of worden door browsers anders geparsed dan door
 * libxml.
 */
const FEED_HTML_DROP_ELEMENTS = [
    'script', 'style', 'noscript', 'template', 'object', 'embed', 'applet',
    'base', 'meta', 'link', 'frame', 'frameset', 'noembed', 'noframes',
    'xmp', 'plaintext', 'title', 'head', 'svg', 'math',
];

/** Attributen die een URL bevatten en dus geen javascript:-URL mogen zijn. */
const FEED_HTML_URL_ATTRIBUTES = [
    'href', 'src', 'srcset', 'action', 'formaction', 'background', 'poster',
    'data', 'cite', 'longdesc', 'lowsrc', 'dynsrc', 'ping', 'xlink:href',
];

const FEED_HTML_VOID_ELEMENTS = [
    'area', 'br', 'col', 'hr', 'img', 'input', 'param', 'source', 'track', 'wbr',
];

function sanitize_feed_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    // ext-dom ontbreekt (zeldzaam op shared hosting): liever alleen de
    // tekst tonen dan onopgeschoonde HTML doorgeven.
    if (!class_exists('DOMDocument')) {
        return nl2br(e(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML(
        '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
        . $html . '</body></html>',
        LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $loaded ? $doc->getElementsByTagName('body')->item(0) : null;
    if ($body === null) {
        return '';
    }
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= sanitize_feed_node($child);
    }
    return $out;
}

function sanitize_feed_node(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if (!$node instanceof DOMElement) {
        return ''; // commentaar, processing instructions e.d.
    }

    $tag = strtolower($node->nodeName);
    if (in_array($tag, FEED_HTML_DROP_ELEMENTS, true)) {
        return '';
    }

    $children = '';
    // Een browser behandelt de inhoud van een iframe als platte tekst;
    // die inhoud wordt nooit getoond, dus laten we hem helemaal weg.
    if ($tag !== 'iframe') {
        foreach ($node->childNodes as $child) {
            $children .= sanitize_feed_node($child);
        }
    }
    if (!preg_match('/^[a-z][a-z0-9-]*$/', $tag)) {
        return $children;
    }

    $attrs = '';
    foreach ($node->attributes as $attr) {
        $name = strtolower($attr->nodeName);
        $value = (string) $attr->nodeValue;
        if (sanitize_feed_attribute_allowed($tag, $name, $value)) {
            $attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
    }

    if (in_array($tag, FEED_HTML_VOID_ELEMENTS, true)) {
        return "<{$tag}{$attrs}>";
    }
    return "<{$tag}{$attrs}>{$children}</{$tag}>";
}

function sanitize_feed_attribute_allowed(string $tag, string $name, string $value): bool
{
    if (!preg_match('/^[a-z_:][a-z0-9_:.-]*$/', $name)) {
        return false;
    }
    // Event-handlers (onclick, onerror, ...) en srcdoc (complete HTML-pagina in een iframe).
    if (strpos($name, 'on') === 0 || $name === 'srcdoc') {
        return false;
    }
    if ($name === 'style') {
        return !sanitize_feed_css_is_dangerous($value);
    }
    if ($name === 'srcset') {
        foreach (explode(',', $value) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            if (!sanitize_feed_url_allowed($parts[0] ?? '', false)) {
                return false;
            }
        }
        return true;
    }
    if (in_array($name, FEED_HTML_URL_ATTRIBUTES, true) || substr($name, -5) === ':href') {
        return sanitize_feed_url_allowed($value, $tag === 'img' && $name === 'src');
    }
    return true;
}

/**
 * Staat alleen relatieve URL's en http(s)/mailto/tel toe, plus (alleen bij
 * <img src>) ingebedde afbeeldingen als data:-URL. Entities zijn door de
 * DOM-parser al gedecodeerd; browsers negeren witruimte en control-tekens
 * in het schema ("java\tscript:"), dus die halen we eerst weg.
 */
function sanitize_feed_url_allowed(string $url, bool $allowDataImage): bool
{
    $normalized = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $url));
    if (!preg_match('/^([a-z][a-z0-9+.-]*):/', $normalized, $m)) {
        return true; // relatieve URL
    }
    if (in_array($m[1], ['http', 'https', 'mailto', 'tel'], true)) {
        return true;
    }
    return $allowDataImage && preg_match('#^data:image/(png|gif|jpe?g|webp);#', $normalized) === 1;
}

/**
 * Style-attributen blijven staan (CKEditor gebruikt ze voor opmaak), behalve
 * als ze een van de oude manieren bevatten om via CSS script uit te voeren.
 * CSS-escapes (bv. "\6a avascript") worden eerst gedecodeerd.
 */
function sanitize_feed_css_is_dangerous(string $css): bool
{
    $normalized = preg_replace('#/\*.*?\*/#s', '', $css);
    $normalized = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', function (array $m): string {
        $code = hexdec($m[1]);
        return $code > 0 && $code < 128 ? chr($code) : '';
    }, $normalized);
    $normalized = strtolower(preg_replace('/[\\\\\x00-\x20\x7f]+/', '', $normalized));

    foreach (['expression(', 'javascript:', 'vbscript:', '-moz-binding', 'behavior:', '@import'] as $needle) {
        if (strpos($normalized, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/** Schoont de omschrijving van elke opkomst op; overige velden escapet main.js zelf. */
function opkomsten_sanitize_items(array $items): array
{
    foreach ($items as $key => $item) {
        if (is_array($item)) {
            $item['omschrijving'] = is_string($item['omschrijving'] ?? null)
                ? sanitize_feed_html($item['omschrijving'])
                : '';
            $items[$key] = $item;
        }
    }
    return $items;
}

function opkomsten_cache_dir(): string
{
    return __DIR__ . '/cache';
}

function opkomsten_cache_file(): string
{
    return opkomsten_cache_dir() . '/opkomsten.json';
}

function opkomsten_lock_file(): string
{
    return opkomsten_cache_dir() . '/opkomsten.lock';
}

/* ------------------------------------------------------------------ *
 * Ophalen van de feed
 *
 * De feed-URL wordt door een beheerder ingevuld en door de server zelf
 * opgehaald. Om te voorkomen dat zo'n URL de server iets anders laat
 * ophalen (file://, gopher://, onversleuteld http://, of via een redirect
 * naar zoiets) is alleen https toegestaan, ook bij elke redirect, en wordt
 * het TLS-certificaat altijd gecontroleerd.
 * ------------------------------------------------------------------ */

const SCOUTDASH_MAX_REDIRECTS = 3;
const SCOUTDASH_MAX_BYTES = 2097152; // 2 MB; een echte feed is maar een paar KB

/**
 * Of $url een geldige absolute URL met host is waarvan het schema in
 * $schemes staat (hoofdletterongevoelig). Ook gebruikt door
 * admin/speltak_form.php om feed- en afmeld-URL's te controleren.
 */
function url_has_allowed_scheme(string $url, array $schemes): bool
{
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($scheme) && is_string($host) && $host !== ''
        && in_array(strtolower($scheme), $schemes, true);
}

function scoutdash_http_get(string $url, int $timeoutSeconds = 6): ?string
{
    if (!url_has_allowed_scheme($url, ['https'])) {
        return null;
    }

    if (function_exists('curl_init')) {
        $body = '';
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => SCOUTDASH_MAX_REDIRECTS,
            // Alleen https, ook na een redirect.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ScoutingVoerendaal-OpkomstenCache/1.0',
            // Zelf bufferen zodat we kunnen afbreken boven SCOUTDASH_MAX_BYTES
            // (CURLOPT_MAXFILESIZE werkt alleen als de server Content-Length stuurt).
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > SCOUTDASH_MAX_BYTES) {
                    $tooLarge = true;
                    return 0; // curl breekt de overdracht af
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($ok === false || $tooLarge || $status < 200 || $status >= 300) {
            return null;
        }
        return $body;
    }

    // Fallback voor hosting zonder curl-extensie. Redirects volgen we zelf:
    // de http-wrapper van PHP zou ook een redirect van https naar http volgen.
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'header' => "User-Agent: ScoutingVoerendaal-OpkomstenCache/1.0\r\n",
            'follow_location' => 0,
            'ignore_errors' => true, // ook bij 3xx/4xx de headers kunnen lezen
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);
    for ($hop = 0; $hop <= SCOUTDASH_MAX_REDIRECTS; $hop++) {
        // Eén byte meer lezen dan toegestaan, zodat we "te groot" herkennen.
        $body = @file_get_contents($url, false, $context, 0, SCOUTDASH_MAX_BYTES + 1);
        if ($body === false || strlen($body) > SCOUTDASH_MAX_BYTES) {
            return null;
        }
        $headers = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?: [])
            : ($http_response_header ?? []);
        $response = scoutdash_parse_response_headers($headers);
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $body;
        }
        if ($response['status'] < 300 || $response['status'] >= 400 || $response['location'] === null) {
            return null;
        }
        $url = scoutdash_resolve_redirect($url, $response['location']);
        if ($url === null || !url_has_allowed_scheme($url, ['https'])) {
            return null;
        }
    }
    return null; // te veel redirects
}

/**
 * Statuscode en Location-header van het laatste antwoord. $headers bevat de
 * headers van alle antwoorden achter elkaar (elk beginnend met de
 * "HTTP/1.1 200 OK"-regel), dus we nemen alleen het laatste blok.
 */
function scoutdash_parse_response_headers(array $headers): array
{
    $status = 0;
    $location = null;
    foreach ($headers as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $m)) {
            $status = (int) $m[1];
            $location = null;
        } elseif (preg_match('/^Location:\s*(.+)$/i', $line, $m)) {
            $location = trim($m[1]);
        }
    }
    return ['status' => $status, 'location' => $location];
}

/** Maakt van een (mogelijk relatieve) Location-header een absolute URL. */
function scoutdash_resolve_redirect(string $base, string $location): ?string
{
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
        return $location; // absoluut; schema wordt door de aanroeper gecontroleerd
    }
    $parts = parse_url($base);
    if (!isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    if (strpos($location, '//') === 0) {
        return $parts['scheme'] . ':' . $location;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (strpos($location, '/') === 0) {
        return $origin . $location;
    }
    $dir = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';
    return $origin . ($dir === '' ? '/' : $dir) . $location;
}

function scoutdash_fetch_items(string $url): ?array
{
    $body = scoutdash_http_get($url);
    if ($body === null) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function opkomsten_read_cache(): ?array
{
    $file = opkomsten_cache_file();
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['generated_at'], $decoded['data']) || !is_array($decoded['data'])) {
        return null;
    }
    if (($decoded['version'] ?? 0) !== OPKOMSTEN_CACHE_VERSION) {
        // Cache van vóór de huidige opschoning: alsnog opschonen (deze data
        // kan ook als terugvaloptie dienen als Scoutdash onbereikbaar is) en
        // bij het eerstvolgende verzoek opnieuw ophalen.
        foreach ($decoded['data'] as $slug => $entry) {
            if (is_array($entry) && is_array($entry['items'] ?? null)) {
                $decoded['data'][$slug]['items'] = opkomsten_sanitize_items($entry['items']);
            }
        }
        $decoded['stale'] = true;
    }
    return $decoded;
}

function opkomsten_write_cache(array $data): void
{
    $dir = opkomsten_cache_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $payload = json_encode(
        ['version' => OPKOMSTEN_CACHE_VERSION, 'generated_at' => time(), 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    file_put_contents(opkomsten_cache_file(), $payload, LOCK_EX);
}

/** Haalt voor elke actieve speltak met een feed-URL de opkomsten op. */
function opkomsten_refresh(array $previousData): array
{
    $result = [];
    foreach (get_speltakken(true) as $sp) {
        if (empty($sp['feed_url'])) {
            continue;
        }
        $slug = $sp['slug'];
        $items = scoutdash_fetch_items($sp['feed_url']);
        if ($items !== null) {
            $result[$slug] = ['items' => opkomsten_sanitize_items($items), 'ok' => true, 'fetched_at' => time()];
        } elseif (isset($previousData[$slug])) {
            // Scoutdash niet bereikbaar: toon het laatst bekende programma.
            $result[$slug] = [
                'items' => $previousData[$slug]['items'],
                'ok' => false,
                'fetched_at' => $previousData[$slug]['fetched_at'] ?? null,
            ];
        } else {
            $result[$slug] = ['items' => [], 'ok' => false, 'fetched_at' => null];
        }
    }
    return $result;
}

/** Wist de opkomsten-cache; het volgende bezoek haalt alles opnieuw op bij Scoutdash. */
function opkomsten_clear_cache(): void
{
    @unlink(opkomsten_cache_file());
}

/**
 * Huidige cache-inhoud voor de beheerpagina: wanneer de cache voor het
 * laatst is ververst en, per speltak, de opgeslagen opkomsten met
 * tijdstip van laatste geslaagde ophaling. Negeert de 10-minuten-houdbaarheid
 * (dat is alleen relevant voor `get_alle_opkomsten()`), dus dit toont ook
 * verlopen cache-inhoud.
 */
function opkomsten_cache_overview(): ?array
{
    return opkomsten_read_cache();
}

/**
 * Opkomsten van alle speltakken, per speltak-slug. Resultaat wordt 10
 * minuten gecached; ververst hooguit door één proces tegelijk zodat
 * gelijktijdige bezoekers niet allemaal op Scoutdash wachten.
 */
function get_alle_opkomsten(): array
{
    $cached = opkomsten_read_cache();
    if ($cached !== null && empty($cached['stale']) && (time() - $cached['generated_at']) < OPKOMSTEN_CACHE_TTL) {
        return $cached['data'];
    }

    $dir = opkomsten_cache_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $lockHandle = fopen(opkomsten_lock_file(), 'c');
    if ($lockHandle !== false && flock($lockHandle, LOCK_EX | LOCK_NB)) {
        try {
            $fresh = opkomsten_refresh($cached['data'] ?? []);
            opkomsten_write_cache($fresh);
            return $fresh;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
    if ($lockHandle !== false) {
        fclose($lockHandle);
    }

    // Een ander verzoek is al bezig met verversen: geef de bekende data
    // terug (ook al is die net verlopen) in plaats van te wachten.
    return $cached['data'] ?? [];
}
