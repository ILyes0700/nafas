<?php
/**
 * A2 — Endpoint corrélation pollution ↔ symptômes.
 *
 * GET ?zone_id=<int>&days=<int>   → analyse d'une zone précise
 * GET                              → analyse toutes les zones (résumé global)
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/correlation.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo  = db();
$days = max(7, min(180, (int)($_GET['days'] ?? 30)));
$zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

if ($zoneId > 0) {
    $row = $pdo->prepare("SELECT id, name, name_ar FROM zones WHERE id = ?");
    $row->execute([$zoneId]);
    $zone = $row->fetch();
    if (!$zone) json_response(['ok' => false, 'error' => 'zone_not_found'], 404);

    $analysis = correlation_analyze_zone($zoneId, $days);
    json_response(['ok' => true, 'zone' => $zone, 'analysis' => $analysis]);
}

/* Toutes les zones */
$zones = $pdo->query("SELECT id, name, name_ar FROM zones ORDER BY id ASC")->fetchAll();
$out = [];
foreach ($zones as $z) {
    $a = correlation_analyze_zone((int)$z['id'], $days);
    $out[] = [
        'zone'    => $z,
        'r'       => $a['r'],
        'label'   => $a['label'],
        'trend'   => $a['trend'],
        'n_points'=> $a['n_points'],
    ];
}

usort($out, fn($A, $B) => abs($B['r']) <=> abs($A['r']));

json_response([
    'ok'     => true,
    'days'   => $days,
    'zones'  => $out,
]);
