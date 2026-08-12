<?php
declare(strict_types=1);

/**
 * PART 45 — Validation qualité des données EN AMONT (type Great Expectations).
 *
 * Valide chaque lecture d'API (IQAir / WAQI / fusion) AVANT qu'elle n'alimente
 * le pipeline. Complémentaire à api_verifier.php (qui détecte des outliers a
 * posteriori) : ici on fait une validation de schéma/plage en amont, en PHP pur.
 *
 * Écrit chaque contrôle dans `data_quality_checks` et signale les échecs
 * critiques. Dégrade proprement si la table n'existe pas.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Valide une lecture normalisée.
 *
 * @param array  $reading  ex: ['pollution_level'=>..,'timestamp'=>..,'pm25'=>..,'so2'=>..]
 * @param string $source   'iqair' | 'waqi' | 'fusion'
 * @return array{ok:bool, critical:bool, checks:array, passed:int, failed:int}
 */
function validate_reading(array $reading, string $source = 'unknown'): array
{
    $checks = [];
    $add = function (string $name, bool $passed, bool $critical, string $details = '')
        use (&$checks) {
        $checks[] = compact('name', 'passed', 'critical', 'details');
    };

    // 1) pollution_level dans [0, 500]
    if (array_key_exists('pollution_level', $reading) && $reading['pollution_level'] !== null) {
        $p = (float)$reading['pollution_level'];
        $add('pollution_range_0_500', $p >= 0 && $p <= 500, true, "pollution_level={$p}");
    } else {
        $add('pollution_present', false, false, 'pollution_level manquant');
    }

    // 2) timestamp pas dans le futur (tolérance 10 min)
    if (!empty($reading['timestamp'])) {
        $ts = is_numeric($reading['timestamp']) ? (int)$reading['timestamp'] : strtotime((string)$reading['timestamp']);
        $add('timestamp_not_future', $ts !== false && $ts <= time() + 600, true,
            'ts=' . ($ts ? date('c', $ts) : 'invalide'));
    }

    // 3) pas plus de 2 champs NULL simultanés parmi les mesures clés
    $keys = ['pollution_level', 'pm25', 'pm10', 'so2', 'no2', 'o3'];
    $nulls = 0;
    foreach ($keys as $k) {
        if (!array_key_exists($k, $reading) || $reading[$k] === null || $reading[$k] === '') $nulls++;
    }
    $add('not_too_many_nulls', $nulls <= 2, true, "champs_nuls={$nulls}");

    // 4) plages plausibles par polluant (non critiques, informatif)
    $bounds = ['pm25' => [0, 800], 'pm10' => [0, 1200], 'so2' => [0, 2000],
               'no2' => [0, 2000], 'o3' => [0, 1000]];
    foreach ($bounds as $k => [$lo, $hi]) {
        if (isset($reading[$k]) && $reading[$k] !== '') {
            $v = (float)$reading[$k];
            $add("range_{$k}", $v >= $lo && $v <= $hi, false, "{$k}={$v}");
        }
    }

    $passed = 0; $failed = 0; $critical = false;
    foreach ($checks as $c) {
        if ($c['passed']) $passed++;
        else { $failed++; if ($c['critical']) $critical = true; }
    }

    _dq_persist($source, $checks);

    return [
        'ok'       => !$critical,
        'critical' => $critical,
        'checks'   => $checks,
        'passed'   => $passed,
        'failed'   => $failed,
    ];
}

/** Persiste les contrôles dans data_quality_checks (best effort). */
function _dq_persist(string $source, array $checks): void
{
    try {
        $pdo = db();
        $ins = $pdo->prepare(
            'INSERT INTO data_quality_checks (checked_at, source, check_name, passed, details)
             VALUES (NOW(), ?, ?, ?, ?)'
        );
        foreach ($checks as $c) {
            $ins->execute([$source, $c['name'], $c['passed'] ? 1 : 0, $c['details']]);
        }
    } catch (Throwable $e) {
        error_log('[data_quality_validator] persist: ' . $e->getMessage());
    }
}
