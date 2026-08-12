<?php
/**
 * CLI — recompute the hybrid ML/DL forecast for every zone.
 *
 * Schedule every hour:
 *   - WAMP (Windows Task Scheduler): php scripts\predict.php
 *   - cron (Linux):  0 * * * * cd /var/www/html/gabes-tatenafas && php scripts/predict.php >> logs/predict.log 2>&1
 */
declare(strict_types=1);
require_once __DIR__ . '/../backend/lib/forecast_ml.php';

$pdo = db();
echo "[predict] running hybrid ML/DL forecaster on every zone...\n";
$res = ml_forecast_all_zones($pdo);

foreach ($res as $r) {
    $name = $r['zone'] ?? '?';
    if (empty($r['ok'])) {
        printf("  - %-22s  SKIPPED (%s)\n", $name, $r['error'] ?? 'unknown');
        continue;
    }
    $m = $r['metrics']['ensemble'] ?? [];
    printf("  - %-22s  α=%.1f  RMSE=%.2f  MAE=%.2f  R²=%s\n",
        $name, $r['alpha'],
        $m['rmse'] ?? 0, $m['mae'] ?? 0,
        $m['r2']   !== null ? number_format($m['r2'], 2) : 'NA'
    );
}
echo "[predict] done.\n";
