<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ai_reco.php';

$pdo = db();
$globalStatus = global_status();

$counts = [
    'zones'    => (int)$pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn(),
    'alerts'   => (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn(),
    'reports'  => (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn(),
    'symptoms' => (int)$pdo->query("SELECT COUNT(*) FROM symptoms WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn(),
    'schools_danger' => (int)$pdo->query("SELECT COUNT(*) FROM school_status WHERE status IN ('danger','suspended')")->fetchColumn(),
];

// Round to 1 decimal so the dashboard shows realistic "57.4" rather than "57".
$avgScore = (float)$pdo->query(
    'SELECT ROUND(AVG(score), 1) FROM (SELECT score FROM risk_scores ORDER BY id DESC LIMIT 100) t'
)->fetchColumn();

$topZones = $pdo->query("
    SELECT z.id, z.name, z.name_ar, z.status, z.pollution_level,
           (SELECT score FROM risk_scores rs WHERE rs.zone_id=z.id ORDER BY id DESC LIMIT 1) AS score
    FROM zones z
    ORDER BY pollution_level DESC LIMIT 3
")->fetchAll();

$recentAlerts = $pdo->query("
    SELECT a.*, z.name AS zone_name FROM alerts a
    LEFT JOIN zones z ON z.id = a.zone_id
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll();

$trend = $pdo->query("
    SELECT DATE(reported_at) d, COUNT(*) c
    FROM reports
    WHERE reported_at >= NOW() - INTERVAL 7 DAY
    GROUP BY DATE(reported_at) ORDER BY d ASC
")->fetchAll();

/* Fuzzy logic recommendation for the connected user (always applied) */
$me = function_exists('auth_user') ? auth_user() : null;
$worstZone = $topZones[0] ?? null;
$recoOpts = [
    'pollution' => $worstZone ? (int)($worstZone['score'] ?? $worstZone['pollution_level'] ?? 0) : 0,
    'zone_id'   => $worstZone ? (int)$worstZone['id'] : 0,
];
// Unified engine: Fuzzy Type-1 + Fuzzy Type-2 (Karnik-Mendel) + AI/ML model.
$ai = ai_reco_for_user($pdo, $me ? (int)$me['id'] : 0, $recoOpts);
$fz = [
    'risk_score'    => $ai['type1']['risk_score'],
    'urgency_level' => $ai['type1']['urgency_level'],
    'fired_rules'   => $ai['type1']['fired_rules'],
    'explanation'   => $ai['type1']['explanation'],
    'actions'       => $ai['actions'],
    'inputs'        => $ai['inputs'],
];

json_response([
    'global_status' => $globalStatus,
    'avg_risk'      => $avgScore ?: 0,
    'counts'        => $counts,
    'top_zones'     => $topZones,
    'recent_alerts' => $recentAlerts,
    'trend'         => $trend,
    'recommendations' => [
        'safe'     => 'Normal activities. Stay vigilant.',
        'warning'  => 'Limit intense outdoor effort. Stay hydrated.',
        'critical' => 'Stay indoors. Keep windows closed. Vulnerable people: see a doctor.',
    ][$globalStatus],
    'fuzzy' => [
        'risk_score'    => $fz['risk_score'],
        'urgency_level' => $fz['urgency_level'],
        'fired_rules'   => array_slice($fz['fired_rules'], 0, 3),
        'explanation'   => $fz['explanation'],
        'actions'       => $fz['actions'],
        'inputs'        => $fz['inputs'],
    ],
    // Automatic recommendation = AI model + Fuzzy Type-2 (shown on dashboard).
    'ai_reco' => [
        'risk_score'    => $ai['risk_score'],
        'urgency_level' => $ai['urgency_level'],
        'actions'       => $ai['actions'],
        'explanation'   => $ai['explanation'],
        'type2'         => $ai['type2'],
        'model'         => $ai['model'],
    ],
]);
