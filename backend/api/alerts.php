<?php
require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $in = read_json_input();
    $stmt = $pdo->prepare(
        'INSERT INTO alerts (zone_id, title, message, severity, type) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $in['zone_id']  ?? null,
        $in['title']    ?? 'Alerte',
        $in['message']  ?? '',
        $in['severity'] ?? 'info',
        $in['type']     ?? 'pollution',
    ]);
    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

$rows = $pdo->query("
    SELECT a.*, z.name AS zone_name, z.name_ar AS zone_name_ar
    FROM alerts a
    LEFT JOIN zones z ON z.id = a.zone_id
    ORDER BY a.created_at DESC
")->fetchAll();
json_response(['alerts' => $rows]);
