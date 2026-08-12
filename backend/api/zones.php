<?php
require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();
$rows = $pdo->query("
    SELECT z.*,
        (SELECT score FROM risk_scores rs WHERE rs.zone_id = z.id ORDER BY id DESC LIMIT 1) AS risk_score,
        (SELECT COUNT(*) FROM reports r WHERE r.zone_id = z.id) AS reports_total,
        (SELECT COUNT(*) FROM symptoms s WHERE s.zone_id = z.id) AS symptoms_total
    FROM zones z
    ORDER BY z.pollution_level DESC
")->fetchAll();

json_response(['zones' => $rows]);
