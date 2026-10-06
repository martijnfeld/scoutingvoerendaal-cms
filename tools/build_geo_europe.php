<?php
/**
 * Genereert includes/geo/europe-ipv4.bin en europe-ipv6.bin (zie
 * includes/geoip.php) uit de openbare "delegated-extended"-statistieken van
 * de vijf regionale internetregisters.
 *
 * Alleen voor ontwikkelaars, draait NIET op de webserver. Draai dit vóór een
 * release zodat de lijst actueel blijft, bv. via Docker:
 *
 *   docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tools/build_geo_europe.php
 *
 * Vereist 64-bit PHP (voor het rekenen met IPv6-prefixen).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (PHP_INT_SIZE < 8) {
    fwrite(STDERR, "Dit script vereist 64-bit PHP.\n");
    exit(1);
}

require __DIR__ . '/../includes/geoip.php';

const RIR_SOURCES = [
    'https://ftp.ripe.net/pub/stats/ripencc/delegated-ripencc-extended-latest',
    'https://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
    'https://ftp.apnic.net/stats/apnic/delegated-apnic-extended-latest',
    'https://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
    'https://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-extended-latest',
];

$countries = array_flip(GEO_EUROPE_COUNTRIES);
$ranges = ['ipv4' => [], 'ipv6' => []];

foreach (RIR_SOURCES as $url) {
    fwrite(STDOUT, "Ophalen: {$url}\n");
    $handle = fopen($url, 'r');
    if ($handle === false) {
        fwrite(STDERR, "Kon {$url} niet ophalen — afgebroken, bestaande bestanden blijven staan.\n");
        exit(1);
    }

    while (($line = fgets($handle)) !== false) {
        // registry|cc|type|start|value|date|status[|opaque-id]
        $fields = explode('|', trim($line));
        if (count($fields) < 7 || $line[0] === '#') {
            continue;
        }
        [, $cc, $type, $start, $value, , $status] = $fields;
        $cc = strtoupper($cc);
        if (!isset($countries[$cc]) || ($status !== 'allocated' && $status !== 'assigned')) {
            continue;
        }

        if ($type === 'ipv4') {
            $begin = ip2long($start);
            if ($begin === false) {
                continue;
            }
            $ranges['ipv4'][] = [$begin, $begin + (int) $value - 1, $cc];
        } elseif ($type === 'ipv6') {
            $bin = inet_pton($start);
            $prefix = (int) $value;
            if ($bin === false) {
                continue;
            }
            // Alles wat RIR's delegeren valt in 2000::/3, dus de eerste 64
            // bits passen als positieve signed 64-bit integer.
            $begin = unpack('J', substr($bin, 0, 8))[1];
            if ($begin < 0) {
                continue;
            }
            $hostBits = max(0, 64 - $prefix);
            $end = $hostBits >= 63 ? PHP_INT_MAX : $begin | ((1 << $hostBits) - 1);
            $ranges['ipv6'][] = [$begin, $end, $cc];
        }
    }
    fclose($handle);
}

$outDir = GEO_DATA_DIR;
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

foreach (['ipv4' => 'N', 'ipv6' => 'J'] as $family => $packFormat) {
    $list = $ranges[$family];
    if (count($list) < 1000) {
        fwrite(STDERR, "Verdacht weinig {$family}-ranges (" . count($list) . ") — afgebroken.\n");
        exit(1);
    }
    usort($list, function ($a, $b) {
        return $a[0] <=> $b[0];
    });

    // Aangrenzende/overlappende ranges van hetzelfde land samenvoegen.
    $merged = [];
    foreach ($list as $range) {
        $last = count($merged) - 1;
        if ($last >= 0 && $merged[$last][2] === $range[2] && $range[0] <= $merged[$last][1] + 1) {
            $merged[$last][1] = max($merged[$last][1], $range[1]);
        } else {
            $merged[] = $range;
        }
    }

    $data = '';
    foreach ($merged as [$begin, $end, $cc]) {
        $data .= pack($packFormat . $packFormat, $begin, $end) . $cc;
    }

    $file = "{$outDir}/europe-{$family}.bin";
    file_put_contents($file . '.tmp', $data);
    rename($file . '.tmp', $file);
    fwrite(STDOUT, sprintf("%s: %d ranges (samengevoegd uit %d), %d KB\n", $file, count($merged), count($list), strlen($data) / 1024));
}

file_put_contents("{$outDir}/GENERATED.txt", "Gegenereerd door tools/build_geo_europe.php op " . gmdate('Y-m-d H:i') . " UTC.\n");
fwrite(STDOUT, "Klaar.\n");
