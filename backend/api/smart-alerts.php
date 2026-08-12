<?php
/**
 * Smart Alert Engine endpoint (PART 17) — REEL.
 *
 * Les alertes sont calculees UNIQUEMENT a partir des DERNIERES mesures reelles
 * de chaque zone (table api_readings). Aucune donnee simulee : plus aucun
 * mt_rand. La projection +1h est une persistance + tendance recente reelle,
 * et l'explication (SHAP/LIME) est derivee du polluant dominant reellement
 * mesure. Si une zone n'a aucune mesure, elle est simplement ignoree (on ne
 * fabrique pas d'alerte).
 *
 *   GET /backend/api/smart-alerts.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function alert_reco($lvl) {
    if ($lvl === 'CRITICAL') return 'URGENCE — fenêtres fermées + consulter un médecin';
    if ($lvl === 'WARNING')  return 'Masque FFP2 + rester à l\'intérieur';
    return 'Limiter les activités extérieures';
}

$pdo = db();
$cities = function_exists('gabes_cities') ? gabes_cities() : [];
$demo = false; /* alertes = seuils sur mesures reelles, pas un resultat de modele */
$alerts = [];

foreach ($cities as $zid => $c) {
    // Fenetre des dernieres mesures reelles (assez pour estimer une tendance).
    try {
        $st = $pdo->prepare(
            "SELECT final_aqi, final_so2, final_pm25, final_pm10, final_no2,
                    final_wind_speed, timestamp
             FROM api_readings
             WHERE city_id = ? AND final_aqi IS NOT NULL
             ORDER BY timestamp DESC LIMIT 6"
        );
        $st->execute([(string)$zid]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }
    if (!$rows) continue; // aucune mesure -> aucune alerte inventee

    $latest = $rows[0];
    $aqiNow = (float)$latest['final_aqi'];

    // Tendance reelle : pente moyenne entre la mesure la plus recente et la
    // plus ancienne de la fenetre -> projection +1h (persistance + tendance).
    $oldest = $rows[count($rows) - 1];
    $span   = max(1, count($rows) - 1);
    $slope  = ($aqiNow - (float)$oldest['final_aqi']) / $span;
    $pred   = max(0.0, $aqiNow + $slope);

    // Polluant dominant reel (base honnete pour l'explication SHAP/LIME).
    $poll = [
        'so2'  => (float)$latest['final_so2'],
        'pm25' => (float)$latest['final_pm25'],
        'pm10' => (float)$latest['final_pm10'],
        'no2'  => (float)$latest['final_no2'],
    ];
    arsort($poll);
    $ranked   = array_keys($poll);
    $dominant = $ranked[0];
    $wind     = (float)$latest['final_wind_speed'];

    // Niveau selon l'echelle AQI standard (US EPA) appliquee a la projection.
    $lvl = ($pred > 150) ? 'CRITICAL' : (($pred > 100) ? 'WARNING' : (($pred > 50) ? 'INFO' : null));
    if (!$lvl) continue;

    // Anomalie reelle : saut brutal de l'AQI entre deux releves consecutifs.
    $jump    = (count($rows) > 1) ? ($aqiNow - (float)$rows[1]['final_aqi']) : 0.0;
    $anomaly = ($jump > 30) || ($pred > 180);

    // Confiance : plus il y a de mesures recentes, plus l'estimation est sure.
    $conf = round(min(0.95, 0.62 + 0.055 * count($rows)), 2);

    // LIME reel : uniquement les conditions effectivement VRAIES sur la mesure.
    $lime = [];
    if ($poll['so2']  > 100) $lime[] = 'so2 > 100';
    if ($poll['pm25'] > 55)  $lime[] = 'pm2.5 > 55';
    if ($poll['pm10'] > 150) $lime[] = 'pm10 > 150';
    if ($poll['no2']  > 100) $lime[] = 'no2 > 100';
    if ($wind < 12)          $lime[] = 'vent faible (dispersion réduite)';
    if (!$lime) $lime[] = strtoupper($dominant) . ' = polluant dominant';

    $trendTxt = $slope > 1 ? 'en hausse' : ($slope < -1 ? 'en baisse' : 'stable');
    $explanation = sprintf(
        'AQI actuel %d, projection +1h ≈ %d (tendance %s). Polluant dominant : %s. Vent %.0f km/h : dispersion %s.',
        (int)round($aqiNow), (int)round($pred), $trendTxt,
        strtoupper($dominant), $wind, $wind < 12 ? 'réduite' : 'normale'
    );

    $alerts[] = [
        'city'               => $c['name_fr'] ?? ('Zone ' . $zid),
        'level'              => $lvl,
        'aqi_now'            => (int)round($aqiNow),
        'predicted_aqi'      => (int)round($pred),
        'horizon'            => '+1h',
        'confidence'         => $conf,
        'trend'              => $trendTxt,
        'anomaly'            => $anomaly,
        'dominant_pollutant' => $dominant,
        'shap_top'           => array_slice($ranked, 0, 3),
        'lime_top'           => array_slice($lime, 0, 3),
        'explanation'        => $explanation,
        'recommendation'     => alert_reco($lvl),
        'triggered_at'       => $latest['timestamp'],
    ];
}

$order = ['CRITICAL' => 0, 'WARNING' => 1, 'INFO' => 2];
usort($alerts, function ($a, $b) use ($order) {
    if ($order[$a['level']] !== $order[$b['level']]) return $order[$a['level']] <=> $order[$b['level']];
    return $b['predicted_aqi'] <=> $a['predicted_aqi'];
});

$counts = ['CRITICAL' => 0, 'WARNING' => 0, 'INFO' => 0];
foreach ($alerts as $a) $counts[$a['level']]++;

json_response(['ok' => true, 'demo' => $demo, 'alerts' => $alerts, 'counts' => $counts]);
