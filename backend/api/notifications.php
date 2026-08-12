<?php
require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();
$role = $_GET['role'] ?? 'all';

$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE target_role = ? OR target_role = 'all'
    ORDER BY created_at DESC LIMIT 50
");
$stmt->execute([$role]);
json_response(['notifications' => $stmt->fetchAll()]);
