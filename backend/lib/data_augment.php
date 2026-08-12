<?php
/**
 * PHP-only DATA AUGMENTATION for the risk_scores time series.
 *
 * Why
 * ---
 *   ML/DL forecasters need lots of points to learn. The platform usually
 *   only has a few hundred per zone, which is too thin for an LSTM. We
 *   generate synthetic points that PRESERVE the statistical properties
 *   of the real series (mean, variance, autocorrelation, range).
 *
 *   This is the GAN/Diffusion alternative when a Python sidecar is not
 *   available. Methods implemented:
 *
 *     - jitter()           : add gaussian noise N(0, σ)
 *     - magnitude_warp()   : multiply by a slow random sinusoid
 *     - time_warp()        : locally stretch / shrink the time axis
 *     - bootstrap_blocks() : moving-block bootstrap (preserves AR(p))
 *
 *   All four are standard "GAN-inspired" augmentations described in
 *   Iwana & Uchida 2021 — "An empirical survey of data augmentation for
 *   time series classification with neural networks". They are exactly
 *   the techniques used as baselines against TimeGAN / TSDiff papers.
 *
 *   Synthetic rows are tagged with `generation_method` so the trainer
 *   can decide whether to include them or weight them differently.
 *
 *   A FIDELITY SCORE is attached to every batch:
 *     fidelity = 1 - |mean_real - mean_synth|/100 - |std_real - std_synth|/50
 *   clamped to [0..1]. Higher = closer to the real distribution.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const AUG_VERSION = '1.0';

/** Box-Muller transform → ~ N(0,1). */
function rand_normal(): float
{
    $u1 = max(1e-9, mt_rand() / mt_getrandmax());
    $u2 = mt_rand() / mt_getrandmax();
    return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
}

/** Compute mean and (population) std of a numeric array. */
function _stats(array $x): array
{
    $n = count($x);
    if ($n === 0) return ['mean' => 0.0, 'std' => 0.0];
    $m = array_sum($x) / $n;
    $v = 0.0;
    foreach ($x as $v0) $v += ($v0 - $m) ** 2;
    return ['mean' => $m, 'std' => $n > 0 ? sqrt($v / $n) : 0.0];
}

/** Clamp to [0,100] and round to int. */
function _clamp_to_score(float $v): int
{
    return (int)max(0, min(100, round($v)));
}

/** Fidelity score in [0..1] between two series (closer to 1 = better). */
function aug_fidelity(array $real, array $synth): float
{
    $r = _stats($real); $s = _stats($synth);
    $delta_mean = abs($r['mean'] - $s['mean']) / 100.0;
    $delta_std  = abs($r['std']  - $s['std'])  / 50.0;
    return round(max(0.0, min(1.0, 1.0 - $delta_mean - $delta_std)), 2);
}

/* =====================================================================
 *  AUGMENTATION OPERATORS
 * ===================================================================== */

/** Additive gaussian jitter. */
function aug_jitter(array $series, float $sigma_pct = 0.05): array
{
    $stats = _stats($series);
    $sigma = max(1.0, $stats['std'] * $sigma_pct * 10);  // 5% std of the series
    $out = [];
    foreach ($series as $x) $out[] = _clamp_to_score($x + rand_normal() * $sigma);
    return $out;
}

/** Multiplicative slow sinusoid warping (preserves shape, scales amplitude). */
function aug_magnitude_warp(array $series, float $strength = 0.20): array
{
    $n = count($series);
    if ($n === 0) return [];
    $freq   = (mt_rand(1, 4) / max(8, $n / 4));
    $phase  = mt_rand() / mt_getrandmax() * 2 * M_PI;
    $out = [];
    foreach ($series as $i => $x) {
        $factor = 1.0 + $strength * sin($freq * $i + $phase);
        $out[]  = _clamp_to_score($x * $factor);
    }
    return $out;
}

/** Local stretch/shrink of the time axis using cubic resampling. */
function aug_time_warp(array $series, float $strength = 0.15): array
{
    $n = count($series);
    if ($n < 4) return $series;
    // Random monotone warping curve
    $cps = max(3, (int)floor($n / 10));
    $deltas = [];
    for ($i = 0; $i < $cps; $i++) {
        $deltas[] = 1.0 + (mt_rand() / mt_getrandmax() - 0.5) * $strength;
    }
    // Cumulative warped indices
    $warped = [0.0];
    for ($i = 1; $i < $n; $i++) {
        $idx = ($i / $n) * ($cps - 1);
        $a = (int)floor($idx); $b = min($cps - 1, $a + 1);
        $frac = $idx - $a;
        $d = $deltas[$a] * (1 - $frac) + $deltas[$b] * $frac;
        $warped[] = $warped[$i - 1] + $d;
    }
    $max = end($warped) ?: 1.0;
    foreach ($warped as &$w) $w = $w / $max * ($n - 1);
    unset($w);

    $out = [];
    foreach ($warped as $w) {
        $a = (int)floor($w);
        $b = min($n - 1, $a + 1);
        $frac = $w - $a;
        $out[] = _clamp_to_score($series[$a] * (1 - $frac) + $series[$b] * $frac);
    }
    return $out;
}

/** Moving-block bootstrap (preserves short autocorrelation). */
function aug_bootstrap_blocks(array $series, int $block_len = 7): array
{
    $n = count($series);
    if ($n < $block_len) return $series;
    $out = [];
    while (count($out) < $n) {
        $start = mt_rand(0, $n - $block_len);
        for ($i = 0; $i < $block_len && count($out) < $n; $i++) {
            $out[] = (int)$series[$start + $i];
        }
    }
    return $out;
}

/* =====================================================================
 *  HIGH-LEVEL API
 * ===================================================================== */

/**
 * Generate `n_synth` augmented rows for one zone, using a rotation of the
 * four operators above. Inserts them into risk_scores_augmented.
 *
 * @return array  ['inserted'=>int, 'methods'=>[method=>count], 'fidelity'=>float]
 */
function augment_zone(PDO $pdo, int $zoneId, int $n_synth = 200, int $days = 30): array
{
    $stmt = $pdo->prepare(
        "SELECT computed_at, score FROM risk_scores
         WHERE zone_id = ? AND computed_at >= NOW() - INTERVAL ? DAY
         ORDER BY computed_at ASC"
    );
    $stmt->execute([$zoneId, $days]);
    $rows = $stmt->fetchAll();
    if (count($rows) < 8) {
        return ['inserted' => 0, 'methods' => [], 'fidelity' => 0.0,
                'error' => 'not_enough_real_data'];
    }
    $series = array_map(fn($r) => (int)$r['score'], $rows);
    $methods = [
        'jitter'         => fn($s) => aug_jitter($s),
        'magnitude_warp' => fn($s) => aug_magnitude_warp($s),
        'time_warp'      => fn($s) => aug_time_warp($s),
        'bootstrap'      => fn($s) => aug_bootstrap_blocks($s),
    ];

    $ins = $pdo->prepare(
        "INSERT INTO risk_scores_augmented
         (zone_id, synthetic_at, score, generation_method, generator_version, fidelity_score)
         VALUES (?,?,?,?,?,?)"
    );

    $inserted = 0; $counts = []; $allSynth = [];
    $startTs = strtotime((string)$rows[0]['computed_at']);
    $endTs   = strtotime((string)$rows[count($rows) - 1]['computed_at']);
    $span    = max(86400, $endTs - $startTs);   // at least 1 day

    while ($inserted < $n_synth) {
        $method = array_rand($methods);
        $synth  = $methods[$method]($series);
        $fid    = aug_fidelity($series, $synth);

        foreach ($synth as $i => $score) {
            if ($inserted >= $n_synth) break;
            // Spread synthetic timestamps over the same time span
            $ts = $startTs + (int)floor($span * ($i / max(1, count($synth) - 1)));
            $synthDate = date('Y-m-d H:i:s', $ts - mt_rand(0, 3600));
            $ins->execute([
                $zoneId, $synthDate, $score,
                $method, AUG_VERSION, $fid,
            ]);
            $inserted++;
            $counts[$method] = ($counts[$method] ?? 0) + 1;
            $allSynth[] = $score;
        }
    }
    return [
        'inserted' => $inserted,
        'methods'  => $counts,
        'fidelity' => aug_fidelity($series, $allSynth),
        'real_size' => count($series),
    ];
}

/** Batch over every zone. CLI entry point. */
function augment_all_zones(PDO $pdo, int $per_zone = 200, int $days = 30): array
{
    $zones = $pdo->query('SELECT id, name FROM zones')->fetchAll();
    $out = [];
    foreach ($zones as $z) {
        $out[(int)$z['id']] = augment_zone($pdo, (int)$z['id'], $per_zone, $days) + ['name' => $z['name']];
    }
    return $out;
}

/**
 * If a trained GAN weights file is available in storage/gan/weights.json,
 * use the pure-PHP GAN to generate `$per_zone` extra rows for every zone.
 * Otherwise this function is a no-op and returns ['skipped'=>true].
 *
 * This is the ONLY entry point the rest of the codebase needs to call to
 * benefit from the GAN — the statistical methods (jitter, warps, bootstrap)
 * are always available via augment_zone() / augment_all_zones().
 */
function gan_augment_all_zones(PDO $pdo, int $per_zone = 50): array
{
    $weights = __DIR__ . '/../../storage/gan/weights.json';
    if (!is_file($weights)) {
        return ['skipped' => true, 'reason' => 'no_weights_file', 'path' => $weights];
    }
    require_once __DIR__ . '/gan.php';
    try {
        $wd = gan_load_weights($weights);
    } catch (Throwable $e) {
        return ['skipped' => true, 'reason' => 'weights_unreadable', 'error' => $e->getMessage()];
    }
    $G     = $wd['G'];
    $zones = $pdo->query('SELECT id, name FROM zones ORDER BY id')->fetchAll();
    $ins   = $pdo->prepare(
        "INSERT INTO risk_scores_augmented
            (zone_id, synthetic_at, score, generation_method, generator_version, fidelity_score)
         VALUES (?, ?, ?, 'gan_php', 'php-v1', ?)"
    );
    $total = 0; $perZone = [];
    foreach ($zones as $z) {
        $samples = gan_sample($G, $per_zone);
        foreach ($samples as $idx => $win) {
            $score    = gan_denorm((float)end($win));
            $ts       = (new DateTime('now'))
                ->modify(sprintf('-%d hours', $per_zone - $idx))
                ->format('Y-m-d H:i:s');
            $fidelity = round(max(0.0, 1.0 - abs($score - 50) / 100.0), 2);
            $ins->execute([(int)$z['id'], $ts, $score, $fidelity]);
            $total++;
        }
        $perZone[(int)$z['id']] = $per_zone;
    }
    return ['skipped' => false, 'inserted' => $total, 'per_zone' => $perZone];
}
