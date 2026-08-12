<?php
/**
 * scripts/train_gan.php — train the pure-PHP GAN on real pollution
 * series from the `risk_scores` table and save the weights to
 *   storage/gan/weights.json
 *
 * Usage
 * -----
 *   php scripts/train_gan.php                # defaults
 *   php scripts/train_gan.php --epochs=500 --batch=16 --lr=0.001 --seed=42
 *
 * The model is reproducible — same seed → same weights — so the jury
 * can replay the training and obtain identical metrics.
 *
 * After training, run `scripts/gan_generate.php` to insert synthetic
 * samples into `risk_scores_augmented` with method = 'gan_php'.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/lib/gan.php';

/* ── argv parsing ─────────────────────────────────────────────────── */
$opts = [
    'epochs'   => 300,
    'batch'    => 8,
    'lr'       => 0.001,
    'momentum' => 0.9,
    'seed'     => 1337,
    'min_win'  => 4,
];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(\w+)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = is_numeric($m[2]) ? $m[2] + 0 : $m[2];
    }
}

/* ── load real windows from risk_scores ───────────────────────────── */
$pdo = db();

echo "Loading history from `risk_scores` …\n";
$rows = $pdo->query(
    "SELECT zone_id, score, computed_at AS ts
       FROM risk_scores
      ORDER BY zone_id, computed_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* Fallback : if `risk_scores` is small (fresh install, seed-only), also
   read augmented history so the GAN has something to learn from. */
if (count($rows) < GAN_SEQ_LEN) {
    echo "  (only " . count($rows) . " real rows — also reading risk_scores_augmented)\n";
    try {
        $extra = $pdo->query(
            "SELECT zone_id, score, synthetic_at AS ts
               FROM risk_scores_augmented
              ORDER BY zone_id, synthetic_at ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_merge($rows, $extra);
    } catch (Throwable $e) { /* table may not exist yet — ignore */ }
}

if (count($rows) === 0) {
    fwrite(STDERR, "ERROR: `risk_scores` and `risk_scores_augmented` are both empty.\n"
        . "TIP: run `php scripts/augment_data.php` first to seed the augmented history.\n");
    exit(2);
}

/* Group all available scores by zone, then build sliding windows of length
   SEQ_LEN. If a zone has fewer scores than SEQ_LEN, we synthesise extra
   windows by jittering — same idea as time-series bootstrap. */
$L = GAN_SEQ_LEN;
$byZone = [];
foreach ($rows as $r) {
    $byZone[(int)$r['zone_id']][] = (int)$r['score'];
}

$windows = [];
foreach ($byZone as $zid => $vals) {
    $n = count($vals);
    if ($n >= $L) {
        // Sliding windows
        for ($i = 0; $i + $L <= $n; $i++) {
            $w = [];
            foreach (array_slice($vals, $i, $L) as $v) $w[] = gan_norm((float)$v);
            $windows[] = $w;
        }
    } else {
        // Not enough rows for this zone → bootstrap by jittered repetition.
        // We loop the available values and add small noise to fill SEQ_LEN.
        // Generate 8 such windows so the GAN sees enough variety.
        for ($k = 0; $k < 8; $k++) {
            $w = [];
            for ($t = 0; $t < $L; $t++) {
                $base  = (float)$vals[$t % $n];
                $jitt  = $base + 4.0 * gan_randn();   // ±4 score noise
                $w[]   = gan_norm($jitt);
            }
            $windows[] = $w;
        }
    }
}

if (count($windows) < (int)$opts['min_win']) {
    fwrite(STDERR, sprintf(
        "ERROR: not enough windows — only %d, need at least %d.\n",
        count($windows), (int)$opts['min_win']
    ));
    exit(3);
}

echo sprintf("Loaded %d windows of length %d (from %d rows, %d zones).\n",
    count($windows), $L, count($rows), count($byZone));

/* ── train ───────────────────────────────────────────────────────── */
echo sprintf(
    "Training GAN — epochs=%d batch=%d lr=%.4f momentum=%.2f seed=%d\n",
    (int)$opts['epochs'], (int)$opts['batch'], (float)$opts['lr'],
    (float)$opts['momentum'], (int)$opts['seed']
);
$t0 = microtime(true);
$res = gan_train($windows, [
    'epochs'   => (int)$opts['epochs'],
    'batch'    => (int)$opts['batch'],
    'lr'       => (float)$opts['lr'],
    'momentum' => (float)$opts['momentum'],
    'seed'     => (int)$opts['seed'],
    'verbose'  => true,
]);
$secs = microtime(true) - $t0;
echo sprintf("Done in %.1fs.  Final loss_D=%.4f  loss_G=%.4f\n",
    $secs,
    end($res['history'])['loss_D'] ?? 0,
    end($res['history'])['loss_G'] ?? 0
);

/* ── save weights ────────────────────────────────────────────────── */
$dir = __DIR__ . '/../storage/gan';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$path = $dir . '/weights.json';
gan_save_weights($path, $res['G'], $res['D'], [
    'trained_on_windows' => count($windows),
    'trained_seconds'    => round($secs, 2),
    'seed'               => (int)$opts['seed'],
    'epochs'             => (int)$opts['epochs'],
    'lr'                 => (float)$opts['lr'],
]);
echo "Weights saved to $path (" . filesize($path) . " bytes)\n";
echo "Now run:  php scripts/gan_generate.php  --per-zone=50\n";
