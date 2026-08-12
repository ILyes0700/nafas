<?php
/**
 * scripts/gan_generate.php — load the trained pure-PHP GAN weights
 * (`storage/gan/weights.json`) and insert synthetic pollution series
 * into the `risk_scores_augmented` table with method = 'gan_php'.
 *
 * Usage
 * -----
 *   php scripts/gan_generate.php --per-zone=50          # 50 rows per zone
 *   php scripts/gan_generate.php --total=500            # 500 rows split equally
 *
 * The synthetic data is then automatically considered by the hybrid
 * forecaster (`forecast_hybrid`) which combines both the real and
 * augmented series.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/lib/gan.php';

/* ── argv parsing ─────────────────────────────────────────────────── */
$opts = ['per_zone' => 0, 'total' => 0];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--per[_-]zone=(\d+)$/', $arg, $m)) $opts['per_zone'] = (int)$m[1];
    elseif (preg_match('/^--total=(\d+)$/', $arg, $m)) $opts['total'] = (int)$m[1];
}
if ($opts['per_zone'] === 0 && $opts['total'] === 0) $opts['per_zone'] = 50;

/* ── load weights ────────────────────────────────────────────────── */
$path = __DIR__ . '/../storage/gan/weights.json';
if (!is_file($path)) {
    fwrite(STDERR, "ERROR: No GAN weights found at $path.\nRun first:  php scripts/train_gan.php\n");
    exit(2);
}
$wd = gan_load_weights($path);
$G  = $wd['G'];
echo "Loaded GAN weights (trained on " . ($wd['meta']['trained_on_windows'] ?? '?') . " windows).\n";

/* ── zones ───────────────────────────────────────────────────────── */
$pdo = db();
$zones = $pdo->query('SELECT id, name FROM zones ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
if (count($zones) === 0) {
    fwrite(STDERR, "ERROR: no zones in DB.\n");
    exit(3);
}

$perZone = $opts['per_zone'] ?: max(1, (int)ceil($opts['total'] / count($zones)));

/* ── prepare INSERT ──────────────────────────────────────────────── */
$ins = $pdo->prepare(
    "INSERT INTO risk_scores_augmented
        (zone_id, synthetic_at, score, generation_method, generator_version, fidelity_score)
     VALUES (?, ?, ?, 'gan_php', 'php-v1', ?)"
);

$total = 0;
foreach ($zones as $z) {
    $samples = gan_sample($G, $perZone);
    foreach ($samples as $idx => $window) {
        // Each window is a 24-hour series. Use the LAST value as a single
        // synthetic point timed `idx` hours back from now. This keeps
        // synthetic_at unique while still being realistic.
        $score = gan_denorm((float)end($window));
        $ts    = (new DateTime('now'))
            ->modify(sprintf('-%d hours', $perZone - $idx))
            ->format('Y-m-d H:i:s');
        // Coarse fidelity proxy: 1 - distance to expected mean (50 in score space)
        $fidelity = round(max(0.0, 1.0 - abs($score - 50) / 100.0), 2);
        $ins->execute([(int)$z['id'], $ts, $score, $fidelity]);
        $total++;
    }
    echo sprintf("  zone %2d (%s) → %d synthetic rows\n", $z['id'], $z['name'], $perZone);
}

echo sprintf("Inserted %d rows in `risk_scores_augmented` (method='gan_php').\n", $total);
echo "These are now available to the hybrid forecaster.\n";
