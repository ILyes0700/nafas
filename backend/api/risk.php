<?php
require_once __DIR__ . '/../lib/helpers.php';

$action = $_GET['action'] ?? 'list';
$pdo = db();

if ($action === 'recompute') {
    json_response(['ok' => true, 'updated' => recompute_all_scores()]);
}

if ($action === 'zone' && isset($_GET['zone_id'])) {
    json_response(compute_risk_score((int)$_GET['zone_id']));
}

$rows = $pdo->query("
    SELECT z.id, z.name, z.name_ar, z.status, z.pollution_level,
           (SELECT score FROM risk_scores rs WHERE rs.zone_id = z.id ORDER BY id DESC LIMIT 1) AS score
    FROM zones z ORDER BY pollution_level DESC
")->fetchAll();
json_response(['scores' => $rows, 'global' => global_status()]);
