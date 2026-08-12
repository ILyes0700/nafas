<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/notify.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/rate_limit.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$me = auth_user();   // peut être null (rétro-compat) — on n'oblige pas

if ($method === 'POST') {
    /* ---------- B9 rate limit : 8 symptômes / heure ---------- */
    $scope = rate_limit_scope_key('symptoms');
    if (!rate_limit_check($pdo, $scope, 'symptoms', 8, 3600)) {
        json_response([
            'ok'    => false,
            'error' => 'Too many submissions in a short time. Please wait.',
            'code'  => 'rate_limited',
        ], 429);
    }

    $in       = read_json_input();
    $zoneId   = isset($in['zone_id']) && $in['zone_id'] !== '' ? (int)$in['zone_id'] : null;
    $severity = $in['severity'] ?? 'mild';
    $symptom  = $in['symptom']  ?? 'Inconnu';

    // Si l'utilisateur est connecté en citoyen, on rattache automatiquement
    // (et on prend son nom comme citizen_name s'il n'a rien forcé).
    $citizenId   = null;
    $citizenName = $in['citizen_name'] ?? 'Anonyme';
    if ($me && $me['role'] === 'citizen') {
        $citizenId   = (int)$me['id'];
        if (empty($in['citizen_name'])) {
            $citizenName = $me['full_name'] ?: $me['username'];
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO symptoms (zone_id, citizen_id, citizen_name, symptom, severity, notes)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $zoneId,
        $citizenId,
        $citizenName,
        $symptom,
        $severity,
        $in['notes'] ?? null,
    ]);
    $symId = (int)$pdo->lastInsertId();

    /* ---------- UPGRADE v8 — Part 50.3 : triage intelligent (routage télémédecine) ---------- */
    $triage = null;
    try {
        require_once __DIR__ . '/../lib/chatbot_emergency_detector.php';
        $freeText = trim((string)($in['notes'] ?? '') . ' ' . (string)$symptom);
        $isEmergency = $freeText !== '' && function_exists('detect_emergency_signal') && detect_emergency_signal($freeText);
        $suggestTelemed = ($severity === 'severe') || $isEmergency;
        $triage = [
            'emergency'       => $isEmergency,
            'suggest_telemed' => $suggestTelemed,
            'route'           => $suggestTelemed ? 'telemed-request' : 'self-care',
        ];
    } catch (Throwable $e) { $triage = null; }

    $auto = null;
    $severeAlert = null;
    if ($zoneId) {
        compute_risk_score($zoneId);
        $auto = notify_check_threshold($zoneId, 'symptoms');
        if ($severity === 'severe') {
            $severeAlert = notify_severe_symptom($zoneId, $symptom);
        }
    }

    json_response([
        'ok'              => true,
        'id'              => $symId,
        'auto_alert_id'   => $auto,
        'severe_alert_id' => $severeAlert,
        'triage'          => $triage,
    ]);
}

$rows = $pdo->query("
    SELECT s.*, z.name AS zone_name,
           (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id AND m.sender_role IN ('health','admin')) AS health_msg_count
    FROM symptoms s LEFT JOIN zones z ON z.id = s.zone_id
    ORDER BY s.reported_at DESC LIMIT 200
")->fetchAll();
$stats = $pdo->query("
    SELECT severity, COUNT(*) c FROM symptoms
    WHERE reported_at >= NOW() - INTERVAL 7 DAY GROUP BY severity
")->fetchAll();
json_response(['symptoms' => $rows, 'stats' => $stats]);
