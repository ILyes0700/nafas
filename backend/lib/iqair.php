<?php
/**
 * IQAir (AirVisual) — récupère l'AQI US le plus proche d'un point lat/lng
 * et le convertit en pollution_level (0-100) compatible avec notre échelle.
 *
 * Conversion AQI US (échelle EPA) → pollution_level :
 *   AQI 0    → 0    (Good = safe)
 *   AQI 50   → 20   (Good)
 *   AQI 100  → 40   (Moderate = warning)
 *   AQI 150  → 60   (Unhealthy SG)
 *   AQI 200  → 80   (Unhealthy = critical)
 *   AQI 300  → 95   (Very Unhealthy)
 *   AQI 500+ → 100  (Hazardous)
 *
 * Ces seuils s'alignent sur la grille santé US EPA et sur les seuils
 * existants de l'app : pollution_level >= 40 = warning, >= 70 = critical.
 *
 * Toutes les fonctions sont défensives : si la clé est absente, si l'API
 * répond mal, ou si la conversion échoue, on retourne null + on log dans
 * iqair_last_error().
 */

require_once __DIR__ . '/../config/iqair.php';

$GLOBALS['__iqair_last_error'] = null;
function iqair_set_error(string $msg): void {
    $GLOBALS['__iqair_last_error'] = $msg;
    error_log('[iqair] ' . $msg);
}
function iqair_last_error(): ?string {
    return $GLOBALS['__iqair_last_error'] ?? null;
}

/**
 * Convertit un AQI US (0..500+) en pollution_level (0..100), interpolation
 * linéaire piecewise calée sur l'échelle EPA.
 */
function iqair_aqi_to_pollution(int $aqi): int
{
    if ($aqi <= 0)   return 0;
    if ($aqi >= 500) return 100;

    // Breakpoints (AQI -> pollution)
    $bp = [
        [0,   0],
        [50,  20],
        [100, 40],
        [150, 60],
        [200, 80],
        [300, 95],
        [500, 100],
    ];
    for ($i = 0; $i < count($bp) - 1; $i++) {
        [$a1, $p1] = $bp[$i];
        [$a2, $p2] = $bp[$i + 1];
        if ($aqi >= $a1 && $aqi <= $a2) {
            $ratio = ($aqi - $a1) / max(1, ($a2 - $a1));
            return (int) round($p1 + ($p2 - $p1) * $ratio);
        }
    }
    return 100;
}

/**
 * Appelle IQAir pour des coordonnées lat/lng et retourne :
 *   ['aqi' => 87, 'pollution_level' => 35, 'station' => 'Sfax', 'ts' => '...']
 * ou null en cas d'échec (raison dans iqair_last_error()).
 */
function iqair_fetch_nearest(float $lat, float $lng): ?array
{
    if (!defined('IQAIR_API_KEY') || IQAIR_API_KEY === '') {
        iqair_set_error('skip: IQAIR_API_KEY not configured in backend/config/iqair.php');
        return null;
    }
    if (!function_exists('curl_init')) {
        iqair_set_error('skip: php_curl extension not loaded');
        return null;
    }

    $url = IQAIR_ENDPOINT
        . '?lat=' . rawurlencode((string)$lat)
        . '&lon=' . rawurlencode((string)$lng)
        . '&key=' . rawurlencode(IQAIR_API_KEY);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => IQAIR_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'Gabes-Tatenafas/2.0',
    ]);
    if (defined('IQAIR_INSECURE') && IQAIR_INSECURE === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $no   = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $no !== 0) {
        iqair_set_error("curl errno={$no} : {$err}");
        return null;
    }
    if ($code < 200 || $code >= 300) {
        $snippet = substr((string)$body, 0, 220);
        iqair_set_error("HTTP {$code} — {$snippet}");
        return null;
    }

    $j = json_decode($body, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'success') {
        iqair_set_error('unexpected response: ' . substr((string)$body, 0, 220));
        return null;
    }

    $cur = $j['data']['current'] ?? null;
    $pol = $cur['pollution'] ?? null;
    if (!is_array($pol) || !isset($pol['aqius'])) {
        iqair_set_error('aqius missing from response');
        return null;
    }

    $aqi = (int) $pol['aqius'];
    return [
        'aqi'             => $aqi,
        'pollution_level' => iqair_aqi_to_pollution($aqi),
        'station'         => $j['data']['city'] ?? 'unknown',
        'ts'              => $pol['ts'] ?? null,
        'main_pollutant'  => $pol['mainus'] ?? null,
    ];
}

/**
 * Met à jour `pollution_level` d'une zone via IQAir, en respectant le
 * cache (IQAIR_REFRESH_INTERVAL_MIN). Recalcule ensuite le risk_score.
 *
 * Retourne ['ok' => bool, 'changed' => bool, ...details].
 */
function iqair_refresh_zone(PDO $pdo, int $zoneId, bool $force = false): array
{
    $z = $pdo->prepare(
        'SELECT id, name, lat, lng, pollution_level, pollution_updated_at
           FROM zones WHERE id = ? LIMIT 1'
    );
    $z->execute([$zoneId]);
    $zone = $z->fetch();
    if (!$zone) {
        return ['ok' => false, 'error' => 'zone not found', 'zone_id' => $zoneId];
    }
    if (!is_numeric($zone['lat']) || !is_numeric($zone['lng'])) {
        return ['ok' => false, 'error' => 'coordinates missing', 'zone_id' => $zoneId];
    }

    // Cache
    if (!$force && !empty($zone['pollution_updated_at'])) {
        $age = strtotime((string)$zone['pollution_updated_at']);
        if ($age && (time() - $age) < (IQAIR_REFRESH_INTERVAL_MIN * 60)) {
            return [
                'ok'              => true,
                'changed'         => false,
                'cached'          => true,
                'zone_id'         => $zoneId,
                'pollution_level' => (int)$zone['pollution_level'],
                'updated_at'      => (string)$zone['pollution_updated_at'],
            ];
        }
    }

    /* --- Multi-source verification (IQAir + WAQI + outlier detection) ---
       If the api_verifier module is available we use it to fuse IQAir +
       WAQI and compute a trust score. Otherwise we fall back to the legacy
       IQAir-only path.                                                    */
    $verifierAvailable = false;
    $verifierPath = __DIR__ . '/api_verifier.php';
    if (is_file($verifierPath)) {
        require_once $verifierPath;
        $verifierAvailable = true;
    }

    // `$live` is also populated so that we can return real station / AQI
    // metadata even when the fused multi-source path is taken.
    $live = null;
    $verifiedTrust = 1.0;

    if ($verifierAvailable) {
        $verified = verify_zone($pdo, $zoneId, (float)$zone['lat'], (float)$zone['lng']);
        $verifiedTrust = (float)($verified['trust'] ?? 0.0);
        if ($verified['ok'] && $verifiedTrust >= 0.35) {
            $newLevel = (int)$verified['pollution'];
            // Try to also fetch IQAir for station/aqi metadata; ignore failure.
            $live = iqair_fetch_nearest((float)$zone['lat'], (float)$zone['lng']);
        } else {
            // trust too low — try IQAir alone as fallback
            $live = iqair_fetch_nearest((float)$zone['lat'], (float)$zone['lng']);
            if ($live === null) {
                return [
                    'ok'      => false,
                    'zone_id' => $zoneId,
                    'error'   => 'verification failed (trust=' . $verifiedTrust . '), iqair also unavailable',
                ];
            }
            $newLevel = (int)$live['pollution_level'];
        }
    } else {
        // Legacy path — IQAir only
        $live = iqair_fetch_nearest((float)$zone['lat'], (float)$zone['lng']);
        if ($live === null) {
            return [
                'ok'      => false,
                'zone_id' => $zoneId,
                'error'   => iqair_last_error() ?? 'iqair unavailable',
            ];
        }
        $newLevel = (int)$live['pollution_level'];
    }

    $oldLevel = (int) $zone['pollution_level'];

    $upd = $pdo->prepare(
        'UPDATE zones SET pollution_level = ?, pollution_updated_at = NOW() WHERE id = ?'
    );
    $upd->execute([$newLevel, $zoneId]);

    // Recompute the risk_score so the dashboard and history stay in sync.
    if (function_exists('compute_risk_score') && function_exists('recompute_all_scores')) {
        $r = compute_risk_score($zoneId);
        $ins = $pdo->prepare('INSERT INTO risk_scores (zone_id, score, level) VALUES (?,?,?)');
        $ins->execute([$zoneId, $r['score'], $r['level']]);
        $upd2 = $pdo->prepare('UPDATE zones SET status = ? WHERE id = ?');
        $upd2->execute([$r['level'], $zoneId]);
    }

    return [
        'ok'              => true,
        'changed'         => $oldLevel !== $newLevel,
        'cached'          => false,
        'zone_id'         => $zoneId,
        'aqi'             => $live['aqi']            ?? null,
        'pollution_level' => $newLevel,
        'previous_level'  => $oldLevel,
        'station'         => $live['station']        ?? 'multi-source-fusion',
        'main_pollutant'  => $live['main_pollutant'] ?? null,
        'ts'              => $live['ts']             ?? null,
        'trust'           => $verifiedTrust,
    ];
}

/**
 * Met à jour toutes les zones. Respecte le cache sauf si $force=true.
 */
function iqair_refresh_all(PDO $pdo, bool $force = false): array
{
    $rows = $pdo->query('SELECT id FROM zones ORDER BY id ASC')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = iqair_refresh_zone($pdo, (int)$r['id'], $force);
        // Petit délai entre appels pour éviter rate-limit côté IQAir
        // (10 req/sec max sur le plan gratuit).
        usleep(150000); // 150 ms
    }
    return $out;
}
