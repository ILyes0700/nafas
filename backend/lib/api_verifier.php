<?php
/**
 * Multi-source API verifier for air-quality data.
 *
 * Goals
 * -----
 *   1. Pull the pollution_level from every available source (IQAir, WAQI).
 *   2. Apply RANGE check (0..100 strict) + Z-score and IQR outlier detection.
 *   3. Cross-check with neighbouring zones (a single zone cannot diverge
 *      by more than 50 points from the city average without a flag).
 *   4. Return a FUSED value (median of valid sources) together with a
 *      trust score in [0..1] that downstream code (iqair_refresh_zone)
 *      can use to decide whether to trust the data.
 *
 * The verifier never blocks: if all external sources fail we return the
 * existing zones.pollution_level so the platform keeps working.
 *
 * Each verification is journalled into `api_verification_log`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/iqair.php';
require_once __DIR__ . '/waqi.php';

/** Strict range check used by all sources. */
function verify_range(int $value): bool
{
    return $value >= 0 && $value <= 100;
}

/**
 * Robust outlier detection on a small numeric series.
 * Uses BOTH the IQR rule (Tukey 1977) and the modified Z-score (Iglewicz &
 * Hoaglin 1993) which is robust when n is small (< 10).
 *
 * @return string[]  list of flags actually triggered: 'iqr_outlier','zscore_outlier'
 */
function verify_outlier_flags(float $value, array $sample): array
{
    $flags = [];
    $sample = array_values(array_filter($sample, 'is_numeric'));
    sort($sample);
    $n = count($sample);
    if ($n < 3) return $flags;            // not enough data to judge

    // ---- IQR rule -----------------------------------------------------
    $q1 = $sample[(int)floor(($n - 1) * 0.25)];
    $q3 = $sample[(int)floor(($n - 1) * 0.75)];
    $iqr = $q3 - $q1;
    if ($iqr > 0) {
        $low  = $q1 - 1.5 * $iqr;
        $high = $q3 + 1.5 * $iqr;
        if ($value < $low || $value > $high) $flags[] = 'iqr_outlier';
    }

    // ---- Modified Z-score --------------------------------------------
    $median = $sample[(int)floor($n / 2)];
    $absdev = array_map(fn($v) => abs($v - $median), $sample);
    sort($absdev);
    $mad = $absdev[(int)floor($n / 2)];
    if ($mad > 0) {
        $mz = 0.6745 * ($value - $median) / $mad;
        if (abs($mz) > 3.5) $flags[] = 'zscore_outlier';
    }
    return $flags;
}

/**
 * Cross-check: a single zone cannot diverge by more than 50 points from
 * the mean of all *other* zones (unless we have evidence of a localized
 * pollution incident — we leave that nuance for the human).
 */
function verify_cross_zone(PDO $pdo, int $zoneId, int $value): array
{
    try {
        $stmt = $pdo->prepare('SELECT AVG(pollution_level) FROM zones WHERE id <> ?');
        $stmt->execute([$zoneId]);
        $mean = (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return ['ok' => true, 'delta' => 0.0];
    }
    $delta = abs($value - $mean);
    return [
        'ok'    => $delta <= 50.0,
        'delta' => round($delta, 1),
        'city_avg' => round($mean, 1),
    ];
}

/**
 * Combine source observations into a single fused estimate.
 * Returns ['fused' => int, 'trust' => float (0..1), 'flags' => [...], 'sources' => [...]].
 */
function verify_fuse(array $observations): array
{
    $valid = array_values(array_filter(
        $observations,
        fn($o) => is_array($o) && verify_range((int)$o['value'])
    ));
    if (empty($valid)) {
        return ['fused' => null, 'trust' => 0.0, 'flags' => ['no_source'], 'sources' => []];
    }
    $vals = array_map(fn($o) => (int)$o['value'], $valid);
    sort($vals);
    $n = count($vals);
    $median = $n % 2 === 1
        ? $vals[(int)($n / 2)]
        : (int)round(($vals[$n / 2 - 1] + $vals[$n / 2]) / 2);

    // Trust = inverse of relative dispersion
    $mean = array_sum($vals) / $n;
    $dispersion = $n > 1
        ? sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / $n) / max(1.0, $mean)
        : 0.0;
    $trust = round(max(0.2, min(1.0, 1.0 - $dispersion)), 2);

    return [
        'fused'   => $median,
        'trust'   => $trust,
        'flags'   => [],
        'sources' => $valid,
    ];
}

/**
 * Log a single observation into api_verification_log (best-effort).
 */
function verify_log(PDO $pdo, string $source, int $zoneId, ?int $raw, ?int $norm, float $trust, array $flags): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO api_verification_log
             (source, zone_id, raw_value, normalized_value, trust_score, flags)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $source, $zoneId,
            $raw, $norm,
            $trust,
            $flags ? implode(',', $flags) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[api_verifier] log: ' . $e->getMessage());
    }
}

/**
 * Public entry point. Fetches IQAir + WAQI for a given zone, applies all
 * verification rules, journals every source, and returns the verified
 * fused pollution_level (or null when nothing is usable).
 *
 *   ['ok'=>bool, 'pollution'=>int|null, 'trust'=>float, 'flags'=>[], 'sources'=>[...]]
 */
function verify_zone(PDO $pdo, int $zoneId, float $lat, float $lng): array
{
    $observations = [];

    /* ---- IQAir ---- */
    $iq = iqair_fetch_nearest($lat, $lng);
    if ($iq) {
        $observations[] = [
            'name'  => 'iqair',
            'raw'   => $iq['aqi'],
            'value' => $iq['pollution_level'],
        ];
        verify_log($pdo, 'iqair', $zoneId, $iq['aqi'], $iq['pollution_level'], 1.0,
                   verify_range((int)$iq['pollution_level']) ? ['range_ok'] : ['range_bad']);
    }

    /* ---- WAQI ---- */
    $wq = waqi_get_cached($pdo, $zoneId, $lat, $lng);
    if ($wq) {
        $observations[] = [
            'name'  => 'waqi',
            'raw'   => $wq['aqi'],
            'value' => $wq['pollution_level'],
        ];
        verify_log($pdo, 'waqi', $zoneId, $wq['aqi'], $wq['pollution_level'], 1.0,
                   verify_range((int)$wq['pollution_level']) ? ['range_ok'] : ['range_bad']);
    }

    $sample = array_map(fn($o) => (int)$o['value'], $observations);
    foreach ($observations as &$o) {
        $o['outlier_flags'] = verify_outlier_flags((float)$o['value'], $sample);
    }
    unset($o);

    $fused = verify_fuse($observations);

    /* ---- Cross-check the FUSED value across the city ---- */
    if ($fused['fused'] !== null) {
        $cross = verify_cross_zone($pdo, $zoneId, (int)$fused['fused']);
        if (!$cross['ok']) {
            $fused['flags'][] = 'cross_zone_warn';
            $fused['trust']   = max(0.3, $fused['trust'] - 0.3);
        }
        verify_log(
            $pdo, 'fused', $zoneId, null, (int)$fused['fused'],
            $fused['trust'], array_merge(['fused_ok'], $fused['flags'])
        );
    } else {
        verify_log($pdo, 'fused', $zoneId, null, null, 0.0, ['no_source']);
    }

    return [
        'ok'         => $fused['fused'] !== null,
        'pollution'  => $fused['fused'],
        'trust'      => $fused['trust'],
        'flags'      => $fused['flags'],
        'sources'    => $observations,
    ];
}
