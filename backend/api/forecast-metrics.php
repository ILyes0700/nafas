<?php
/**
 * Forecast metrics endpoint — exposes the comparison table of every model
 * (EWMA baseline, AR(7), MEWMA, Ensemble) used by the admin dashboard to
 * prove the hybrid pipeline beats the legacy EWMA.
 *
 *   GET /backend/api/forecast-metrics.php             → latest per model
 *   GET /backend/api/forecast-metrics.php?zone_id=3   → filtered by zone
 *   GET /backend/api/forecast-metrics.php?train=1     → re-train all zones
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/forecast_ml.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

$pdo = db();

if (!empty($_GET['train'])) {
    $results = ml_forecast_all_zones($pdo);
    json_response(['ok' => true, 'trained' => count($results), 'results' => $results]);
}

$zone = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
$where = $zone ? 'WHERE zone_id = ' . $zone : '';

$rows = $pdo->query(
    "SELECT model_name, zone_id, mae, rmse, mape, r2, smape, sample_size, trained_at
     FROM forecast_metrics $where
     ORDER BY trained_at DESC LIMIT 200"
)->fetchAll();

/* Fix #5: forecast_metrics is written by the PHP EWMA/AR path. When the REAL
   Python pipeline (models/train_all.py) produced the numbers instead, they live
   in model_performance. Fall back to it so this page stays consistent with the
   Deep-Learning / Forecast-ML pages rather than showing an empty table. */
if (!$rows) {
    $mpWhere = $zone ? ('WHERE city_id = ' . $zone) : '';
    $rows = $pdo->query(
        "SELECT model_name, city_id AS zone_id, mae, rmse, mape,
                r_squared AS r2, smape, NULL AS sample_size, evaluated_at AS trained_at
         FROM model_performance $mpWhere
         ORDER BY evaluated_at DESC LIMIT 200"
    )->fetchAll();
}

/* Build a comparison table: latest metric per model, averaged across zones */
$byModel = [];
foreach ($rows as $r) {
    $m = $r['model_name'];
    if (!isset($byModel[$m])) {
        $byModel[$m] = [
            'model' => $m, 'n_zones' => 0,
            'mae_sum'=>0,'rmse_sum'=>0,'mape_sum'=>0,'r2_sum'=>0,'smape_sum'=>0,
            'latest'=>$r['trained_at'],
        ];
    }
    $byModel[$m]['n_zones']  += 1;
    $byModel[$m]['mae_sum']  += (float)$r['mae'];
    $byModel[$m]['rmse_sum'] += (float)$r['rmse'];
    $byModel[$m]['mape_sum'] += (float)$r['mape'];
    $byModel[$m]['r2_sum']   += (float)$r['r2'];
    $byModel[$m]['smape_sum']+= (float)$r['smape'];
}
$summary = [];
foreach ($byModel as $m) {
    $n = max(1, $m['n_zones']);
    $summary[] = [
        'model' => $m['model'],
        'mae'   => round($m['mae_sum'] / $n, 3),
        'rmse'  => round($m['rmse_sum'] / $n, 3),
        'mape'  => round($m['mape_sum'] / $n, 3),
        'r2'    => round($m['r2_sum']   / $n, 3),
        'smape' => round($m['smape_sum']/ $n, 3),
        'n_runs'=> $m['n_zones'],
        'latest'=> $m['latest'],
    ];
}

json_response([
    'ok'      => true,
    'summary' => $summary,
    'rows'    => $rows,
]);
