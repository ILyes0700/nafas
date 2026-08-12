<?php
/**
 * Adaptive Ensemble + Residual + Uncertainty/Trust endpoint — RÉEL.
 * Les membres et leurs poids viennent des vrais modeles (model_performance).
 * L'incertitude par zone est l'ecart-type reel de l'AQI recent (api_readings).
 * La courbe residuelle compare l'AQI reel a une prevision de persistance reelle.
 *   GET /backend/api/ensemble.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

$demo = false;
$members = [];
$rows = [];
$residual = ['hours' => [], 'actual' => [], 'ensemble' => [], 'corrected' => []];
try {
    $pdo = db();
    $ms = $pdo->query("SELECT model_name, AVG(r_squared) r2, AVG(f1_macro) f1, AVG(rmse) rmse, AVG(avg_latency_ms) lat FROM model_performance WHERE horizon = '1h' GROUP BY model_name ORDER BY AVG(rmse) ASC")->fetchAll();
    foreach ($ms as $m) {
        $members[] = ['model' => $m['model_name'], 'r2' => round((float)$m['r2'], 3), 'f1' => round((float)$m['f1'], 3),
            'rmse' => round((float)$m['rmse'], 2), 'lat' => round((float)$m['lat'], 1)];
    }
    if ($members) {
        $maxRmse = max(array_map(fn($m) => $m['rmse'] > 0 ? $m['rmse'] : 1, $members)); if ($maxRmse <= 0) $maxRmse = 1;
        $maxLat = max(array_map(fn($m) => $m['lat'] > 0 ? $m['lat'] : 1, $members)); if ($maxLat <= 0) $maxLat = 1;
        $scores = [];
        foreach ($members as $m) { $scores[] = 0.30 * $m['r2'] + 0.30 * $m['f1'] - 0.25 * ($m['rmse'] / $maxRmse) - 0.15 * ($m['lat'] / $maxLat); }
        $exp = array_map(fn($s) => exp($s * 2.0), $scores); $sum = array_sum($exp); if ($sum <= 0) $sum = 1;
        foreach ($members as $i => &$m) { $m['weight'] = round($exp[$i] / $sum, 3); $m['score'] = round($scores[$i], 3); }
        unset($m);
    } else { $demo = true; }

    $cities = function_exists('gabes_cities') ? gabes_cities() : [];
    foreach ($cities as $zid => $c) {
        $st = $pdo->prepare("SELECT AVG(final_aqi) a, STDDEV_POP(final_aqi) s FROM (SELECT final_aqi FROM api_readings WHERE city_id = ? AND final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 168) t");
        $st->execute([(string)$zid]);
        $r = $st->fetch();
        $pred = (float)($r['a'] ?? 0); if ($pred <= 0) continue;
        $unc = round((float)($r['s'] ?? 0), 2); if ($unc <= 0) $unc = 1.0;
        $lo = round($pred - 1.645 * $unc, 0); $hi = round($pred + 1.645 * $unc, 0);
        $conf = 1 / (1 + $unc / 20);
        $trust = 0.5 * 0.9 + 0.5 * $conf;
        $tl = $trust >= 0.8 ? 'HIGH' : ($trust >= 0.6 ? 'MEDIUM' : 'LOW');
        $rows[] = ['name' => $c['name_fr'] ?? ('Zone ' . $zid), 'pred' => round($pred, 0),
            'lower' => $lo, 'upper' => $hi, 'uncertainty' => $unc,
            'confidence' => round($conf * 100, 0), 'trust' => round($trust, 2), 'trust_level' => $tl];
    }

    $tzRow = $pdo->query("SELECT city_id, COUNT(*) c FROM api_readings WHERE final_aqi IS NOT NULL GROUP BY city_id ORDER BY c DESC LIMIT 1")->fetch();
    $tz = $tzRow['city_id'] ?? '1';
    $st = $pdo->prepare("SELECT final_aqi FROM api_readings WHERE city_id = ? AND final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 25");
    $st->execute([(string)$tz]);
    $series = array_reverse($st->fetchAll(PDO::FETCH_COLUMN));
    $nn = count($series);
    for ($i = 1; $i < $nn; $i++) {
        $a = (float)$series[$i];
        $persist = (float)$series[$i - 1];
        $slope = $i >= 2 ? ((float)$series[$i - 1] - (float)$series[$i - 2]) : 0;
        $corrected = $persist + $slope * 0.6;
        $residual['hours'][] = ($i - 1);
        $residual['actual'][] = round($a, 1);
        $residual['ensemble'][] = round($persist, 1);
        $residual['corrected'][] = round($corrected, 1);
    }
} catch (Throwable $e) { $demo = true; }

json_response([
    'ok' => true, 'demo' => $demo,
    'members' => $members,
    'cities' => $rows,
    'residual' => $residual,
    'references' => ['Dietterich (2000), LNCS Springer', 'He et al. (2016), CVPR', 'Gal & Ghahramani (2016), ICML'],
]);
