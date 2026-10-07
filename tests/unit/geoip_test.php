<?php
require_once TEST_ROOT . '/config.php';
require_once TEST_ROOT . '/includes/geoip.php';

test('geo-databestanden zijn aanwezig en hebben hele records', function () {
    assert_true(geo_data_available());
    foreach (['europe-ipv4.bin' => 10, 'europe-ipv6.bin' => 18] as $file => $recordSize) {
        $size = filesize(GEO_DATA_DIR . '/' . $file);
        assert_true($size > 0, "{$file} is niet leeg");
        assert_same(0, $size % $recordSize, "{$file} bestaat uit records van {$recordSize} bytes");
    }
});

test('geo-ranges zijn gesorteerd, overlappen niet en hebben Europese landcodes', function () {
    foreach (['europe-ipv4.bin' => 4, 'europe-ipv6.bin' => 8] as $file => $keyLength) {
        $data = file_get_contents(GEO_DATA_DIR . '/' . $file);
        $recordSize = $keyLength * 2 + 2;
        $previousEnd = null;
        for ($offset = 0; $offset < strlen($data); $offset += $recordSize) {
            $start = substr($data, $offset, $keyLength);
            $end = substr($data, $offset + $keyLength, $keyLength);
            $country = substr($data, $offset + $keyLength * 2, 2);
            if (strcmp($start, $end) > 0) {
                fail("{$file}: record " . ($offset / $recordSize) . ' heeft begin > eind');
            }
            if ($previousEnd !== null && strcmp($start, $previousEnd) <= 0) {
                fail("{$file}: record " . ($offset / $recordSize) . ' overlapt met of staat vóór het vorige');
            }
            if (!in_array($country, GEO_EUROPE_COUNTRIES, true)) {
                fail("{$file}: onbekende landcode " . test_export($country));
            }
            $previousEnd = $end;
        }
    }
});

test('geo_europe_country() kent bekende adressen', function () {
    assert_same('NL', geo_europe_country('193.0.0.1'), 'RIPE NCC (IPv4)');
    assert_same('NL', geo_europe_country('::ffff:193.0.0.1'), 'IPv4-mapped IPv6');
    assert_same('NL', geo_europe_country('2001:67c:2e8::1'), 'RIPE NCC (IPv6)');
    assert_same('GB', geo_europe_country('212.58.244.1'), 'BBC');
    assert_same(null, geo_europe_country('8.8.8.8'), 'Google DNS (VS)');
    assert_same(null, geo_europe_country('2001:4860:4860::8888'), 'Google DNS IPv6 (VS)');
    assert_same(null, geo_europe_country('geen ip'));
});

test('admin_login_allowed_from_ip(): Europa ja, elders nee', function () {
    assert_true(ADMIN_GEO_BLOCK, 'standaard staat de geoblokkade aan');
    assert_true(admin_login_allowed_from_ip('193.0.0.1'));
    assert_true(admin_login_allowed_from_ip('2001:67c:2e8::1'));
    assert_false(admin_login_allowed_from_ip('8.8.8.8'));
    assert_false(admin_login_allowed_from_ip('2001:4860:4860::8888'));
});

test('admin_login_allowed_from_ip(): privé-adressen altijd, ongeldige nooit', function () {
    foreach (['127.0.0.1', '10.1.2.3', '192.168.1.10', '172.18.0.1', '::1', 'fd00::1'] as $ip) {
        assert_true(admin_login_allowed_from_ip($ip), $ip);
    }
    foreach (['', 'localhost', '999.1.1.1', '1.2.3'] as $ip) {
        assert_false(admin_login_allowed_from_ip($ip), test_export($ip));
    }
});
