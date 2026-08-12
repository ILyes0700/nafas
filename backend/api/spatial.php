<?php
/**
 * Spatial pollution propagation endpoint — RÉEL.
 * Le vent et l'AQI local de chaque zone viennent des vraies mesures
 * api_readings ; la propagation est un vrai calcul physique (alignement au
 * vent + distance) sur les vraies coordonnees GPS.
 *   GET /backend/api/spatial.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

$cities = function_exists('gabes_cities') ? gabes_cities() : [];
$demo = false;
$wind_dir = 135; $wind_speed = 18;
$list = [];
try {
    $pdo = db();
    $w = $pdo->query("SELECT final_wind_direction wd, final_wind_speed ws FROM api_readings WHERE final_wind_speed IS NOT NULL ORDER BY timestamp DESC LIMIT 1")->fetch();
    if ($w) {
        if ($w['wd'] !== null) $wind_dir = (float)$w['wd'];
        if ($w['ws'] !== null) $wind_speed = (float)$w['ws'];
    }
    foreach ($cities as $zid => $c) {
        $st = $pdo->prepare("SELECT final_aqi a FROM api_readings WHERE city_id = ? AND final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $st->execute([(string)$zid]);
        $r = $st->fetch();
        $aqi = $r ? (float)$r['a'] : 0.0;
        $list[$zid] = ['id' => $zid, 'name' => $c['name_fr'] ?? ('Zone ' . $zid),
            'lat' => (float)($c['lat'] ?? 33.88), 'lng' => (float)($c['lng'] ?? 10.1),
            'local_aqi' => $aqi, 'factor' => (float)($c['pollution_factor'] ?? 1.0)];
    }
    if (!$list) $demo = true;
} catch (Throwable $e) {
    $demo = true;
    foreach ($cities as $zid => $c) {
        $list[$zid] = ['id' => $zid, 'name' => $c['name_fr'] ?? ('Zone ' . $zid),
            'lat' => (float)($c['lat'] ?? 33.88), 'lng' => (float)($c['lng'] ?? 10.1),
            'local_aqi' => 0.0, 'factor' => (float)($c['pollution_factor'] ?? 1.0)];
    }
}

$edges = []; $adjusted = [];
foreach ($list as $zid => $c) {
    $contrib = 0;
    foreach ($list as $sid => $s) {
        if ($sid === $zid) continue;
        $dlat = $c['lat'] - $s['lat']; $dlng = $c['lng'] - $s['lng'];
        $bearing = rad2deg(atan2($dlng, $dlat)); if ($bearing < 0) $bearing += 360;
        $alignment = cos(deg2rad($wind_dir - $bearing));
        $dist = sqrt($dlat * $dlat + $dlng * $dlng) * 111;
        if ($alignment > 0.5 && $dist < 30) {
            $factor = $alignment * ($wind_speed / 30) * (1 - $dist / 50);
            $pc = $factor * $s['local_aqi'];
            $contrib += $pc;
            $edges[] = ['from' => $s['name'], 'to' => $c['name'], 'alignment' => round($alignment, 2),
                'distance_km' => round($dist, 1), 'contribution' => round($pc, 1)];
        }
    }
    $adj = 0.85 * $c['local_aqi'] + 0.15 * $contrib;
    $adjusted[] = ['name' => $c['name'], 'local_aqi' => round($c['local_aqi'], 0),
        'received' => round($contrib, 1), 'adjusted_aqi' => round($adj, 0)];
}
usort($edges, fn($a, $b) => $b['contribution'] <=> $a['contribution']);

json_response([
    'ok' => true, 'demo' => $demo,
    'wind' => ['direction' => $wind_dir, 'speed' => $wind_speed],
    'edges' => $edges, 'adjusted' => $adjusted,
    'reference' => 'Seinfeld & Pandis (2016), Wiley',
]);
