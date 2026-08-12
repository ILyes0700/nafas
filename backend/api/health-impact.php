<?php
/**
 * Health Impact Index endpoint — RÉEL.
 * Score sanitaire par zone calcule depuis les vraies mesures api_readings
 * (AQI, PM2.5, SO2) et la population reelle (config villes). Tendance 30 jours
 * agregee depuis la base.
 *   GET /backend/api/health-impact.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function hi_level($s) {
    if ($s <= 25) return ['Negligeable', '#16a34a', 'Activites normales'];
    if ($s <= 50) return ['Faible', '#eab308', 'Surveiller les symptomes'];
    if ($s <= 75) return ['Modere', '#d97706', 'Limiter activites exterieures'];
    if ($s <= 90) return ['Eleve', '#dc2626', 'Rester a l\'interieur, masque FFP2'];
    return ['Critique', '#7e0023', 'URGENCE - evacuation zones industrielles'];
}

$cities = function_exists('gabes_cities') ? gabes_cities() : [];
$demo = false;
$rows = [];
$trend = ['labels' => [], 'avg' => [], 'worst' => []];
try {
    $pdo = db();
    foreach ($cities as $zid => $c) {
        $st = $pdo->prepare("SELECT final_aqi, final_pm25, final_so2 FROM api_readings WHERE city_id = ? AND final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $st->execute([(string)$zid]);
        $r = $st->fetch();
        if (!$r) continue;
        $aqi = (float)$r['final_aqi']; $pm25 = (float)$r['final_pm25']; $so2 = (float)$r['final_so2'];
        $pop = (float)($c['population'] ?? 20000);
        $vuln = min(100, round($pop / 75000 * 40 + 15));
        $exposure = min(24, round((float)($c['pollution_factor'] ?? 1.0) * 12));
        $score = min(100, max(0, (
            ($aqi / 500) * 0.25 + ($pm25 / 75) * 0.25 + ($so2 / 100) * 0.20 +
            ($vuln / 100) * 0.15 + ($exposure / 24) * 0.15) * 100));
        list($lvl, $color, $reco) = hi_level($score);
        $rows[] = ['name' => $c['name_fr'] ?? ('Zone ' . $zid), 'name_ar' => $c['name_ar'] ?? '',
            'score' => round($score, 1), 'level' => $lvl, 'color' => $color, 'recommendation' => $reco,
            'aqi' => round($aqi, 0), 'pm25' => round($pm25, 1), 'so2' => round($so2, 1),
            'vuln_pct' => $vuln];
    }
    usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);

    $tr = $pdo->query("SELECT DATE(timestamp) d, AVG(final_aqi) a, MAX(final_aqi) mx FROM api_readings WHERE final_aqi IS NOT NULL GROUP BY DATE(timestamp) ORDER BY d DESC LIMIT 30")->fetchAll();
    $tr = array_reverse($tr);
    foreach ($tr as $t) {
        $trend['labels'][] = date('d/m', strtotime($t['d']));
        $trend['avg'][] = round((float)$t['a'], 1);
        $trend['worst'][] = round((float)$t['mx'], 1);
    }
    if (!$rows) $demo = true;
} catch (Throwable $e) { $demo = true; }

json_response(['ok' => true, 'demo' => $demo, 'cities' => $rows, 'trend' => $trend]);
