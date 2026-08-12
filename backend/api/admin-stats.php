<?php
/**
 * Nafass — admin stats endpoint
 * Réservé au rôle "admin" uniquement.
 *
 * Modes :
 *   GET  ?action=summary           → KPIs + séries 30j + répartitions
 *   GET  ?action=users             → liste des utilisateurs
 *   POST ?action=recompute_risk    → recalcule les risk_scores de toutes les zones
 *   POST ?action=purge_auto_alerts → supprime les alertes [AUTO:*] de plus de 7 jours
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';

$me = auth_user();
if (!$me || $me['role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$pdo    = db();
$action = $_GET['action'] ?? 'summary';

try {
    if ($action === 'summary') {
        echo json_encode(['ok' => true, 'data' => admin_summary($pdo)]);
        exit;
    }
    if ($action === 'users') {
        $rows = $pdo->query("
            SELECT id, username, full_name, email, role, is_active, zone_id, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 50
        ")->fetchAll();
        echo json_encode(['ok' => true, 'users' => $rows]);
        exit;
    }
    if ($action === 'recompute_risk') {
        $n = 0;
        $zones = $pdo->query('SELECT id FROM zones')->fetchAll();
        foreach ($zones as $z) {
            compute_risk_score((int)$z['id']);
            $n++;
        }
        echo json_encode(['ok' => true, 'updated' => $n]);
        exit;
    }
    if ($action === 'purge_auto_alerts') {
        $st = $pdo->prepare("DELETE FROM alerts WHERE title LIKE '[AUTO:%' AND created_at < NOW() - INTERVAL 7 DAY");
        $st->execute();
        echo json_encode(['ok' => true, 'deleted' => $st->rowCount()]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'unknown-action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ----------------------------------------------------------------------

function admin_summary(PDO $pdo): array {
    // Users by role
    $rolesRaw = $pdo->query("SELECT role, COUNT(*) AS n FROM users GROUP BY role")->fetchAll();
    $usersByRole = ['admin' => 0, 'health' => 0, 'school' => 0, 'citizen' => 0];
    foreach ($rolesRaw as $r) { $usersByRole[$r['role']] = (int)$r['n']; }
    $usersTotal = array_sum($usersByRole);

    // Quick counts
    $alerts24h   = (int)$pdo->query("SELECT COUNT(*) FROM alerts   WHERE created_at  >= NOW() - INTERVAL 1 DAY")->fetchColumn();
    $alerts30d   = (int)$pdo->query("SELECT COUNT(*) FROM alerts   WHERE created_at  >= NOW() - INTERVAL 30 DAY")->fetchColumn();
    $reports24h  = (int)$pdo->query("SELECT COUNT(*) FROM reports  WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
    $reports30d  = (int)$pdo->query("SELECT COUNT(*) FROM reports  WHERE reported_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
    $symptoms24h = (int)$pdo->query("SELECT COUNT(*) FROM symptoms WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
    $symptoms30d = (int)$pdo->query("SELECT COUNT(*) FROM symptoms WHERE reported_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
    $chatbotMsgs = (int)$pdo->query("SELECT COUNT(*) FROM chatbot_logs WHERE created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
    $reportsPdfTotal = (int)$pdo->query("SELECT COUNT(*) FROM reports_pdf")->fetchColumn();

    // Time series 30j (alertes / signalements / symptômes par jour)
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[$d] = ['alerts' => 0, 'reports' => 0, 'symptoms' => 0];
    }
    $tsAlerts = $pdo->query("
        SELECT DATE(created_at) d, COUNT(*) n FROM alerts
        WHERE created_at >= CURDATE() - INTERVAL 29 DAY
        GROUP BY DATE(created_at)
    ")->fetchAll();
    foreach ($tsAlerts as $r) { if (isset($days[$r['d']])) $days[$r['d']]['alerts'] = (int)$r['n']; }

    $tsReports = $pdo->query("
        SELECT DATE(reported_at) d, COUNT(*) n FROM reports
        WHERE reported_at >= CURDATE() - INTERVAL 29 DAY
        GROUP BY DATE(reported_at)
    ")->fetchAll();
    foreach ($tsReports as $r) { if (isset($days[$r['d']])) $days[$r['d']]['reports'] = (int)$r['n']; }

    $tsSymptoms = $pdo->query("
        SELECT DATE(reported_at) d, COUNT(*) n FROM symptoms
        WHERE reported_at >= CURDATE() - INTERVAL 29 DAY
        GROUP BY DATE(reported_at)
    ")->fetchAll();
    foreach ($tsSymptoms as $r) { if (isset($days[$r['d']])) $days[$r['d']]['symptoms'] = (int)$r['n']; }

    $labels = array_keys($days);
    $serAlerts   = array_map(fn($d) => $d['alerts'],   array_values($days));
    $serReports  = array_map(fn($d) => $d['reports'],  array_values($days));
    $serSymptoms = array_map(fn($d) => $d['symptoms'], array_values($days));

    // Alerts by severity (30 derniers jours)
    $sevRaw = $pdo->query("
        SELECT severity, COUNT(*) n FROM alerts
        WHERE created_at >= NOW() - INTERVAL 30 DAY
        GROUP BY severity
    ")->fetchAll();
    $sev = ['info' => 0, 'warning' => 0, 'danger' => 0, 'critical' => 0];
    foreach ($sevRaw as $r) {
        $k = $r['severity'] ?: 'info';
        if (isset($sev[$k])) $sev[$k] = (int)$r['n'];
    }

    // Top 5 zones par signalements (30j)
    $topZones = $pdo->query("
        SELECT z.name, z.status,
               (SELECT COUNT(*) FROM reports r WHERE r.zone_id = z.id AND r.reported_at >= NOW() - INTERVAL 30 DAY) AS reports_count,
               (SELECT COUNT(*) FROM symptoms s WHERE s.zone_id = z.id AND s.reported_at >= NOW() - INTERVAL 30 DAY) AS symptoms_count,
               z.pollution_level
        FROM zones z
        ORDER BY reports_count DESC, z.pollution_level DESC
        LIMIT 5
    ")->fetchAll();

    // DB stats — nombre de lignes par table principale
    $tables = ['zones', 'alerts', 'reports', 'symptoms', 'users', 'chatbot_logs', 'risk_scores', 'reports_pdf', 'notifications', 'school_status'];
    $dbStats = [];
    foreach ($tables as $t) {
        try {
            $dbStats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (Throwable $e) {
            $dbStats[$t] = null;
        }
    }

    // Activité récente
    $recentReports = $pdo->query("
        SELECT r.id, r.citizen_name, r.category, r.reported_at, z.name AS zone_name
        FROM reports r LEFT JOIN zones z ON z.id = r.zone_id
        ORDER BY r.reported_at DESC LIMIT 6
    ")->fetchAll();

    $recentAlerts = $pdo->query("
        SELECT a.id, a.title, a.severity, a.created_at, z.name AS zone_name
        FROM alerts a LEFT JOIN zones z ON z.id = a.zone_id
        ORDER BY a.created_at DESC LIMIT 6
    ")->fetchAll();

    $recentUsers = $pdo->query("
        SELECT id, username, full_name, role, is_active, created_at
        FROM users
        ORDER BY created_at DESC LIMIT 6
    ")->fetchAll();

    return [
        'global_status' => global_status(),
        'users' => [
            'total'  => $usersTotal,
            'admin'  => $usersByRole['admin'],
            'health' => $usersByRole['health'],
            'school' => $usersByRole['school'],
            'citizen'=> $usersByRole['citizen'],
        ],
        'counts' => [
            'alerts_24h'   => $alerts24h,
            'alerts_30d'   => $alerts30d,
            'reports_24h'  => $reports24h,
            'reports_30d'  => $reports30d,
            'symptoms_24h' => $symptoms24h,
            'symptoms_30d' => $symptoms30d,
            'chatbot_30d'  => $chatbotMsgs,
            'pdf_total'    => $reportsPdfTotal,
        ],
        'timeseries' => [
            'labels'   => $labels,
            'alerts'   => $serAlerts,
            'reports'  => $serReports,
            'symptoms' => $serSymptoms,
        ],
        'severity'   => $sev,
        'top_zones'  => $topZones,
        'db_stats'   => $dbStats,
        'recent'     => [
            'reports' => $recentReports,
            'alerts'  => $recentAlerts,
            'users'   => $recentUsers,
        ],
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}
