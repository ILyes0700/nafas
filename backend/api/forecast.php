<?php
/**
 * A1 — Prévision pollution 24h.
 *
 * GET                  → renvoie la prévision pour toutes les zones.
 * GET ?zone_id=N       → renvoie la prévision détaillée pour la zone N.
 * POST persist=1 (admin) → recompute + sauvegarde en BD (pour cron).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/forecast.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = auth_user();
    if (!$me || $me['role'] !== 'admin') {
        json_response(['ok' => false, 'error' => 'admin_required'], 403);
    }
    $count = forecast_persist_all();
    json_response(['ok' => true, 'forecasts_written' => $count]);
}

$zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

if ($zoneId > 0) {
    $z = $pdo->prepare("SELECT * FROM zones WHERE id = ?");
    $z->execute([$zoneId]);
    $zone = $z->fetch();
    if (!$zone) json_response(['ok' => false, 'error' => 'zone_not_found'], 404);
    $f = forecast_compute_zone($zoneId);
    json_response(['ok' => true, 'zone' => $zone, 'forecast' => $f]);
}

$rows = $pdo->query("SELECT id, name, name_ar, pollution_level, status FROM zones ORDER BY id ASC")->fetchAll();
$out = [];
foreach ($rows as $z) {
    $f = forecast_compute_zone((int)$z['id']);
    $out[] = [
        'zone'      => $z,
        'current'   => $f['current'],
        'horizons'  => $f['horizons'],
        'method'    => $f['method'],
    ];
}

json_response(['ok' => true, 'zones' => $out]);
