<?php
/**
 * Endpoint — run multi-source verification for every zone (or one zone)
 * and return the verifier output as JSON. Admin only.
 *
 *   GET  /backend/api/verify-data.php              → verify every zone
 *   GET  /backend/api/verify-data.php?zone_id=3    → verify a single zone
 *   GET  /backend/api/verify-data.php?history=1    → last 50 log entries
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/api_verifier.php';

$me = auth_user();
if (!$me || $me['role'] !== 'admin') {
    json_response(['ok' => false, 'error' => 'admin_only'], 403);
}

$pdo = db();

if (!empty($_GET['history'])) {
    $rows = $pdo->query(
        "SELECT source, zone_id, raw_value, normalized_value, trust_score, flags, verified_at
         FROM api_verification_log
         ORDER BY verified_at DESC LIMIT 50"
    )->fetchAll();
    json_response(['ok' => true, 'history' => $rows]);
}

$zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

if ($zoneId > 0) {
    $z = $pdo->prepare('SELECT id, name, lat, lng FROM zones WHERE id = ?');
    $z->execute([$zoneId]);
    $zone = $z->fetch();
    if (!$zone) json_response(['ok' => false, 'error' => 'zone_not_found'], 404);
    $out = verify_zone($pdo, (int)$zone['id'], (float)$zone['lat'], (float)$zone['lng']);
    json_response(['ok' => true, 'zone' => $zone['name'], 'result' => $out]);
}

$zones = $pdo->query('SELECT id, name, lat, lng FROM zones')->fetchAll();
$out = [];
foreach ($zones as $z) {
    $r = verify_zone($pdo, (int)$z['id'], (float)$z['lat'], (float)$z['lng']);
    $out[] = ['zone' => $z['name']] + $r;
}
json_response(['ok' => true, 'verified' => count($out), 'results' => $out]);
