<?php
/**
 * CLI helper — generate synthetic risk-score points for every zone.
 *
 * Usage (from the project root):
 *   php scripts/augment_data.php            # 200 synthetic points per zone
 *   php scripts/augment_data.php 500 60     # 500 points, looking back 60 days
 *
 * Designed to be called from a Windows scheduled task or a cron job:
 *   30 3 * * *  cd /var/www/html/gabes-tatenafas && php scripts/augment_data.php >> logs/augment.log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../backend/lib/data_augment.php';

$pdo  = db();
$per  = (int)($argv[1] ?? 200);
$days = (int)($argv[2] ?? 30);

echo "[augment] starting — $per synthetic points per zone, history=$days d\n";
$report = augment_all_zones($pdo, $per, $days);

foreach ($report as $zid => $info) {
    $name = $info['name'] ?? "zone#$zid";
    $ins  = $info['inserted'] ?? 0;
    $fid  = $info['fidelity'] ?? 0;
    $err  = $info['error']    ?? null;
    if ($err) {
        printf("  - %-20s  SKIPPED (%s)\n", $name, $err);
    } else {
        printf("  - %-20s  +%d synth (fidelity %.2f)\n", $name, $ins, $fid);
    }
}

/* If a trained GAN is available, also generate GAN samples to mix in. */
$ganOut = gan_augment_all_zones($pdo, 50);
if (!empty($ganOut['skipped'])) {
    echo "[augment] GAN skipped: {$ganOut['reason']}\n";
    echo "[augment] (Train a GAN once with:  php scripts/train_gan.php)\n";
} else {
    echo "[augment] GAN inserted {$ganOut['inserted']} synthetic rows (method='gan_php').\n";
}
echo "[augment] done.\n";
