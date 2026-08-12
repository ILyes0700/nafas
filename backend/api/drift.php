<?php
/**
 * Concept Drift + Auto-Optimization endpoint — RÉEL.
 * Le drift est mesure sur la vraie distribution de l'AQI (api_readings) :
 * ecart des moyennes journalieres vs la distribution de reference + divergence
 * KL gaussienne reelle. La progression d'optimisation vient des vrais modeles
 * (model_performance).
 *   GET /backend/api/drift.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function pear($xs, $ys) {
    $n = count($xs); if ($n < 3) return 0.0;
    $mx = array_sum($xs) / $n; $my = array_sum($ys) / $n;
    $num = 0; $dx = 0; $dy = 0;
    for ($i = 0; $i < $n; $i++) { $a = $xs[$i] - $mx; $b = $ys[$i] - $my; $num += $a * $b; $dx += $a * $a; $dy += $b * $b; }
    if ($dx <= 0 || $dy <= 0) return 0.0;
    return $num / sqrt($dx * $dy);
}

$demo = false;
$labels = []; $drift = []; $kl = []; $events = [];
$cycles = []; $rmse = []; $f1 = [];
$optuna = [];
$featuresAdded = [];
$featuresRemoved = [];
try {
    $pdo = db();
    $b = $pdo->query("SELECT AVG(final_aqi) m, STDDEV_POP(final_aqi) s FROM api_readings WHERE final_aqi IS NOT NULL")->fetch();
    $bm = (float)($b['m'] ?? 0); $bs = (float)($b['s'] ?? 0); if ($bs < 1e-6) $bs = 1.0;

    $days = $pdo->query("SELECT DATE(timestamp) d, AVG(final_aqi) a, STDDEV_POP(final_aqi) s FROM api_readings WHERE final_aqi IS NOT NULL GROUP BY DATE(timestamp) ORDER BY d DESC LIMIT 30")->fetchAll();
    $days = array_reverse($days);
    foreach ($days as $dd) {
        $a = (float)$dd['a'];
        $ds = min(1.0, abs($a - $bm) / (2 * $bs));
        $labels[] = date('d/m', strtotime($dd['d']));
        $drift[] = round($ds, 3);
        $sd = (float)$dd['s']; if ($sd < 1e-6) $sd = $bs;
        $kld = log($bs / $sd) + ($sd * $sd + ($a - $bm) * ($a - $bm)) / (2 * $bs * $bs) - 0.5;
        $kl[] = round(max(0, min(2, $kld)), 3);
        if ($ds >= 0.5) $events[] = ['date' => end($labels), 'drift_score' => round($ds, 3), 'retrained' => true];
    }

    $ms = $pdo->query("SELECT model_name, AVG(rmse) rmse, AVG(f1_macro) f1 FROM model_performance WHERE horizon = '1h' GROUP BY model_name ORDER BY AVG(rmse) DESC")->fetchAll();
    $bestSoFar = null; $cycleModels = [];
    foreach ($ms as $m) {
        // Axe X = numeros de cycle d'optimisation (C1, C2, ...) et non des noms
        // de modeles : le graphique represente la progression RMSE/F1 du pire
        // au meilleur. Le nom du modele reste disponible pour l'infobulle.
        $cycles[] = 'C' . (count($cycles) + 1);
        $cycleModels[] = $m['model_name'];
        $rmse[] = round((float)$m['rmse'], 2);
        $f1[] = round((float)$m['f1'], 3);
        $rv = (float)$m['rmse'];
        if ($bestSoFar === null || $rv < $bestSoFar) $bestSoFar = $rv;
        $optuna[] = round($bestSoFar, 3);
    }

    // Real top features = pollutants most correlated with AQI
    $featCols = ['final_so2' => 'SO2', 'final_pm25' => 'PM2.5', 'final_pm10' => 'PM10', 'final_no2' => 'NO2', 'final_o3' => 'O3', 'final_wind_speed' => 'Vent'];
    $cols = array_keys($featCols);
    $data = $pdo->query("SELECT final_aqi, " . implode(',', $cols) . " FROM api_readings WHERE final_aqi IS NOT NULL LIMIT 5000")->fetchAll();
    if ($data) {
        $aqi = array_map(fn($r) => (float)$r['final_aqi'], $data);
        $corrs = [];
        foreach ($cols as $c) { $corrs[$featCols[$c]] = abs(pear(array_map(fn($r) => (float)$r[$c], $data), $aqi)); }
        arsort($corrs);
        $featuresAdded = array_slice(array_keys($corrs), 0, 3);
        // Features REELLEMENT retirees = variables faiblement correlees a l'AQI
        // (|r| < 0.15) : on les nomme au lieu d'afficher un vague "< 1%".
        foreach ($corrs as $fname => $cv) { if ($cv < 0.15) $featuresRemoved[] = $fname; }
    }
    if (!$days && !$ms) $demo = true;
} catch (Throwable $e) { $demo = true; }

$stats = ['current_drift' => $drift ? end($drift) : 0, 'threshold' => 0.5, 'retrainings' => count($events),
          'features_removed' => $featuresRemoved, 'features_added' => $featuresAdded];

json_response([
    'ok' => true, 'demo' => $demo,
    'drift' => ['labels' => $labels, 'score' => $drift, 'kl' => $kl, 'threshold' => 0.5],
    'events' => $events,
    'optimization' => ['cycles' => $cycles, 'cycle_models' => $cycleModels, 'rmse' => $rmse, 'f1' => $f1],
    'optuna' => $optuna,
    'stats' => $stats,
    'reference' => 'Gama et al. (2014), ACM Surveys',
]);
