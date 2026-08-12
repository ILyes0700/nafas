<?php
/**
 * WAQI client — fetches the closest station around (lat, lng) and converts
 * its AQI to our internal 0..100 pollution_level scale (same piecewise
 * mapping as IQAir for consistency).
 *
 * All functions are defensive: missing token, network error, or unexpected
 * JSON shape all return NULL. The error is captured in waqi_last_error().
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/waqi.php';
require_once __DIR__ . '/iqair.php';   // reuse iqair_aqi_to_pollution()

$GLOBALS['__waqi_last_error'] = null;
function waqi_set_error(string $msg): void {
    $GLOBALS['__waqi_last_error'] = $msg;
    error_log('[waqi] ' . $msg);
}
function waqi_last_error(): ?string {
    return $GLOBALS['__waqi_last_error'] ?? null;
}

function waqi_fetch_nearest(float $lat, float $lng): ?array
{
    if (!defined('WAQI_API_KEY') || WAQI_API_KEY === '') {
        waqi_set_error('skip: WAQI_API_KEY not configured');
        return null;
    }
    if (!function_exists('curl_init')) {
        waqi_set_error('skip: php_curl missing');
        return null;
    }
    $url = WAQI_ENDPOINT . rawurlencode((string)$lat) . ';'
         . rawurlencode((string)$lng) . '/?token=' . rawurlencode(WAQI_API_KEY);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => WAQI_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'Gabes-Tatenafas/2.1',
    ]);
    if (defined('WAQI_INSECURE') && WAQI_INSECURE === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $no   = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $no !== 0) {
        waqi_set_error("curl errno={$no}: {$err}");
        return null;
    }
    if ($code < 200 || $code >= 300) {
        waqi_set_error("HTTP {$code}: " . substr((string)$body, 0, 200));
        return null;
    }
    $j = json_decode($body, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'ok') {
        waqi_set_error('unexpected payload: ' . substr((string)$body, 0, 200));
        return null;
    }
    $aqi = (int)($j['data']['aqi'] ?? -1);
    if ($aqi < 0) {
        waqi_set_error('aqi missing from response');
        return null;
    }

    return [
        'aqi'             => $aqi,
        'pollution_level' => iqair_aqi_to_pollution($aqi),
        'station'         => $j['data']['city']['name'] ?? 'unknown',
        'ts'              => $j['data']['time']['s']    ?? null,
    ];
}

/**
 * Cached version — reads waqi_cache table and only refreshes after
 * WAQI_CACHE_MIN minutes. Returns the same shape as waqi_fetch_nearest().
 */
function waqi_get_cached(PDO $pdo, int $zoneId, float $lat, float $lng): ?array
{
    try {
        $st = $pdo->prepare('SELECT aqi, pollution, station_name, fetched_at
                             FROM waqi_cache WHERE zone_id = ?');
        $st->execute([$zoneId]);
        $row = $st->fetch();
        if ($row) {
            $age = (time() - strtotime((string)$row['fetched_at'])) / 60;
            if ($age < WAQI_CACHE_MIN) {
                return [
                    'aqi'             => (int)$row['aqi'],
                    'pollution_level' => (int)$row['pollution'],
                    'station'         => $row['station_name'] ?? 'cache',
                    'ts'              => $row['fetched_at'],
                    'from_cache'      => true,
                ];
            }
        }
    } catch (Throwable $e) {
        // table might not exist yet — ignore
    }
    $fresh = waqi_fetch_nearest($lat, $lng);
    if (!$fresh) return null;

    try {
        $up = $pdo->prepare(
            'INSERT INTO waqi_cache (zone_id, aqi, pollution, station_name)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE aqi=VALUES(aqi), pollution=VALUES(pollution),
                                     station_name=VALUES(station_name),
                                     fetched_at=NOW()'
        );
        $up->execute([
            $zoneId, $fresh['aqi'], $fresh['pollution_level'],
            $fresh['station'] ?? null,
        ]);
    } catch (Throwable $e) {
        // ignore — cache is opportunistic
    }
    return $fresh;
}
