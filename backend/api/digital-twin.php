<?php
/**
 * PART 48 — Digital Twin (admin only).
 *
 *   GET  /backend/api/digital-twin.php            → scénarios sauvegardés
 *   POST /backend/api/digital-twin.php            → lance un scénario "et si"
 *        body JSON: { scenario_name, zone_id, base_aqi, source_reduction_pct, wind_speed, distance_to_source_m, hours }
 *
 * La simulation réelle est en Python (models/digital_twin.py). Côté PHP on
 * fournit une simulation équivalente légère (panache gaussien) pour l'aperçu
 * temps réel, puis on persiste dans digital_twin_scenarios. Dégrade proprement.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_only'], 403);
}

$pdo = db();

function _plume(float $wind, float $dist): float
{
    $u = max(0.5, $wind);
    $x = max(1.0, $dist);
    $sy = max(1e-3, 0.08 * $x / sqrt(1 + 0.0001 * $x));
    $sz = max(1e-3, 0.06 * $x / sqrt(1 + 0.0015 * $x));
    return 1.0 / (2 * M_PI * $u * $sy * $sz);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = read_json_input();
    $name = (string)($in['scenario_name'] ?? 'Scenario');
    $zid  = (int)($in['zone_id'] ?? 0);
    $base = (float)($in['base_aqi'] ?? 90);
    $red  = (float)($in['source_reduction_pct'] ?? 0) / 100.0;
    $wind = (float)($in['wind_speed'] ?? 3.0);
    $dist = (float)($in['distance_to_source_m'] ?? 800);
    $hours = max(1, min(72, (int)($in['hours'] ?? 24)));

    $ref = _plume(3.0, $dist);
    $curve = [];
    for ($h = 0; $h < $hours; $h++) {
        $w = max(0.5, $wind + sin($h / 3.0) * 0.5);
        $ratio = $ref > 0 ? _plume($w, $dist) / $ref : 1.0;
        $aqi = $base * (1 - $red) * $ratio;
        if ($curve) { $aqi = 0.7 * $aqi + 0.3 * end($curve); }
        $curve[] = round(max(0.0, $aqi), 1);
    }
    $conf = ($red || $wind) ? 0.6 : 0.4;

    try {
        $pdo->prepare(
            'INSERT INTO digital_twin_scenarios
                (scenario_name, created_at, zone_id, parameters_json, simulated_aqi_curve, confidence)
             VALUES (?,NOW(),?,?,?,?)'
        )->execute([$name, $zid, json_encode($in), json_encode($curve), $conf]);
    } catch (Throwable $e) { /* persistance optionnelle */ }

    json_response(['ok' => true, 'curve' => $curve, 'confidence' => $conf]);
}

try {
    $rows = $pdo->query(
        'SELECT * FROM digital_twin_scenarios ORDER BY id DESC LIMIT 50'
    )->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'scenarios' => $rows]);
} catch (Throwable $e) {
    json_response(['ok' => true, 'scenarios' => [],
                   'note' => 'table absente (migration v6 requise): ' . $e->getMessage()]);
}
