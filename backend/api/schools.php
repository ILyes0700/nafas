<?php
require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $in = read_json_input();
    $action = $in['action'] ?? '';
    $id = isset($in['id']) ? (int)$in['id'] : 0;

    if ($action === 'suspend' && $id) {
        broadcast_school_action($pdo, $id, 'suspended');
        json_response(['ok' => true, 'action' => 'suspend']);
    }

    if ($action === 'reactivate' && $id) {
        broadcast_school_action($pdo, $id, 'normal');
        json_response(['ok' => true, 'action' => 'reactivate']);
    }

    if ($action === 'set_status' && $id) {
        $newStatus = $in['status'] ?? '';
        if (!in_array($newStatus, ['normal','vigilance','danger','suspended'], true)) {
            json_response(['ok' => false, 'error' => 'Invalid status'], 400);
        }
        broadcast_school_action($pdo, $id, $newStatus);
        json_response(['ok' => true, 'action' => 'set_status', 'status' => $newStatus]);
    }

    if ($action === 'notify_parents' && $id) {
        $row = $pdo->prepare('SELECT s.school_name, s.zone_id, z.name AS zone_name FROM school_status s LEFT JOIN zones z ON z.id=s.zone_id WHERE s.id=?');
        $row->execute([$id]);
        $info = $row->fetch();
        $name = $info['school_name'] ?? 'School';
        $msg  = trim($in['message'] ?? '') ?: "Notification sent to parents of $name.";

        $pdo->prepare("INSERT INTO notifications (target_role, title, message, level) VALUES ('school',?,?, 'warning')")
            ->execute(["Parents notification — $name", $msg]);
        json_response(['ok' => true, 'action' => 'notify_parents']);
    }

    if ($action === 'broadcast_parents') {
        $msg = trim($in['message'] ?? '');
        if ($msg === '') json_response(['ok' => false, 'error' => 'Empty message'], 400);
        $schools = $pdo->query("SELECT id, school_name FROM school_status")->fetchAll();
        $stmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message, level) VALUES ('school',?,?, 'warning')");
        foreach ($schools as $s) {
            $stmt->execute(["Parents notification — " . $s['school_name'], $msg]);
        }
        json_response(['ok' => true, 'action' => 'broadcast_parents', 'count' => count($schools)]);
    }

    // ---------- Module : saisie des absents ----------
    if ($action === 'add_absentee') {
        $schoolId  = (int)($in['school_id'] ?? 0);
        $student   = trim($in['student_name'] ?? '');
        $class     = trim($in['student_class'] ?? '');
        $date      = trim($in['absent_date'] ?? date('Y-m-d'));
        $reason    = trim($in['reason'] ?? 'non_precise');
        $notes     = trim($in['notes'] ?? '');
        $reporter  = trim($in['reported_by'] ?? 'school');

        if (!$schoolId || $student === '') {
            json_response(['ok' => false, 'error' => 'School and student name are required'], 400);
        }
        $allowedReasons = ['respiratoire','allergie','fievre','oculaire','digestif','asthme','autre','non_precise'];
        if (!in_array($reason, $allowedReasons, true)) $reason = 'non_precise';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $pdo->prepare("
            INSERT INTO school_absences
                (school_id, student_name, student_class, absent_date, reason, notes, reported_by)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([$schoolId, $student, $class ?: null, $date, $reason, $notes ?: null, $reporter ?: null]);

        recompute_school_absentees($pdo, $schoolId);

        // Si c'est une raison médicale, on incrémente aussi le compteur symptômes
        if (in_array($reason, ['respiratoire','allergie','fievre','oculaire','digestif','asthme'], true)) {
            $pdo->prepare("UPDATE school_status SET symptoms_count = symptoms_count + 1 WHERE id=?")
                ->execute([$schoolId]);
        }

        json_response([
            'ok'          => true,
            'action'      => 'add_absentee',
            'id'          => (int)$pdo->lastInsertId(),
            'absentees'   => fetch_school_absentees_today($pdo, $schoolId),
        ]);
    }

    if ($action === 'delete_absentee') {
        $absenceId = (int)($in['id'] ?? 0);
        if (!$absenceId) json_response(['ok' => false, 'error' => 'ID required'], 400);

        $schoolIdStmt = $pdo->prepare("SELECT school_id FROM school_absences WHERE id=?");
        $schoolIdStmt->execute([$absenceId]);
        $schoolId = (int)$schoolIdStmt->fetchColumn();
        if (!$schoolId) json_response(['ok' => false, 'error' => 'Absence not found'], 404);

        $pdo->prepare("DELETE FROM school_absences WHERE id=?")->execute([$absenceId]);
        recompute_school_absentees($pdo, $schoolId);

        json_response(['ok' => true, 'action' => 'delete_absentee']);
    }

    if ($action === 'list_absentees') {
        $schoolId = (int)($in['school_id'] ?? 0);
        $date     = trim($in['date'] ?? '');
        $limit    = min((int)($in['limit'] ?? 100), 500);

        $where = [];
        $args  = [];
        if ($schoolId) { $where[] = 'a.school_id = ?'; $args[] = $schoolId; }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $where[] = 'a.absent_date = ?';
            $args[]  = $date;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT a.*, s.school_name
            FROM school_absences a
            LEFT JOIN school_status s ON s.id = a.school_id
            $whereSql
            ORDER BY a.absent_date DESC, a.reported_at DESC
            LIMIT $limit
        ");
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        json_response(['ok' => true, 'absentees' => $rows]);
    }

    json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}

$rows = $pdo->query("
    SELECT s.*, z.name AS zone_name, z.name_ar AS zone_name_ar, z.status AS zone_status, z.pollution_level
    FROM school_status s LEFT JOIN zones z ON z.id = s.zone_id
    ORDER BY FIELD(s.status,'suspended','danger','vigilance','normal'), s.school_name
")->fetchAll();

$summary = [
    'total'      => count($rows),
    'suspended'  => 0,
    'danger'     => 0,
    'vigilance'  => 0,
    'normal'     => 0,
    'absentees'  => 0,
    'symptoms'   => 0,
];
foreach ($rows as $r) {
    $summary[$r['status']] = ($summary[$r['status']] ?? 0) + 1;
    $summary['absentees'] += (int)$r['absentees'];
    $summary['symptoms']  += (int)$r['symptoms_count'];
}

$schoolAlerts = $pdo->query("
    SELECT a.*, z.name AS zone_name FROM alerts a
    LEFT JOIN zones z ON z.id = a.zone_id
    WHERE a.type = 'school'
    ORDER BY a.created_at DESC LIMIT 12
")->fetchAll();

// Absences du jour (toutes écoles confondues) pour affichage dans la page école.
$todayAbsentees = $pdo->query("
    SELECT a.*, s.school_name
    FROM school_absences a
    LEFT JOIN school_status s ON s.id = a.school_id
    WHERE a.absent_date = CURDATE()
    ORDER BY a.reported_at DESC
    LIMIT 200
")->fetchAll();

json_response([
    'schools'        => $rows,
    'summary'        => $summary,
    'school_alerts'  => $schoolAlerts,
    'absentees_today'=> $todayAbsentees,
]);


/**
 * Met à jour le statut d'une école et déclenche, si nécessaire, des
 * notifications + alerte globale (visibles dans la cloche pour tous les rôles).
 */
function broadcast_school_action(PDO $pdo, int $schoolId, string $newStatus): void
{
    $row = $pdo->prepare('
        SELECT s.school_name, s.status AS prev_status, s.zone_id, z.name AS zone_name
        FROM school_status s LEFT JOIN zones z ON z.id = s.zone_id WHERE s.id = ?
    ');
    $row->execute([$schoolId]);
    $info = $row->fetch();
    if (!$info) return;

    $name      = $info['school_name'];
    $zoneName  = $info['zone_name'] ?: '—';
    $zoneId    = $info['zone_id'];
    $prev      = $info['prev_status'];

    $pdo->prepare("UPDATE school_status SET status=?, last_update=NOW() WHERE id=?")
        ->execute([$newStatus, $schoolId]);

    if ($newStatus === $prev) return;

    if ($newStatus === 'suspended') {
        $title = "[AUTO:school] School closed — $name";
        $msg   = "$name ($zoneName) is suspending activities due to environmental risk. Parents and staff: shelter-in-place guidance.";
        insert_alert_and_notify($pdo, $zoneId, $title, $msg, 'danger', 'school');
    } elseif ($newStatus === 'danger') {
        $title = "[AUTO:school] High risk — $name";
        $msg   = "$name ($zoneName) is moving to danger level. Outdoor activities suspended.";
        insert_alert_and_notify($pdo, $zoneId, $title, $msg, 'danger', 'school');
    } elseif ($newStatus === 'vigilance') {
        $title = "[AUTO:school] Watch — $name";
        $msg   = "$name ($zoneName) under heightened watch. Limit intense activities.";
        insert_alert_and_notify($pdo, $zoneId, $title, $msg, 'warning', 'school');
    } elseif ($newStatus === 'normal' && $prev === 'suspended') {
        $title = "[AUTO:school] Reopening — $name";
        $msg   = "$name ($zoneName) is resuming normal activities. The zone is no longer at critical threshold.";
        insert_alert_and_notify($pdo, $zoneId, $title, $msg, 'info', 'school');
    }
}

function insert_alert_and_notify(PDO $pdo, ?int $zoneId, string $title, string $msg, string $severity, string $type): void
{
    $pdo->prepare("INSERT INTO alerts (zone_id, title, message, severity, type) VALUES (?,?,?,?,?)")
        ->execute([$zoneId, $title, $msg, $severity, $type]);

    $level = $severity === 'critical' ? 'danger' : $severity;
    if (!in_array($level, ['info','warning','danger'], true)) $level = 'warning';

    $pdo->prepare("INSERT INTO notifications (target_role, title, message, level) VALUES ('all',?,?,?)")
        ->execute([$title, $msg, $level]);
}

/**
 * Recalcule school_status.absentees en comptant les absences du jour
 * pour l'école donnée. Appelé après INSERT/DELETE dans school_absences.
 */
function recompute_school_absentees(PDO $pdo, int $schoolId): void
{
    $count = fetch_school_absentees_today($pdo, $schoolId);
    $pdo->prepare("UPDATE school_status SET absentees=?, last_update=NOW() WHERE id=?")
        ->execute([$count, $schoolId]);
}

function fetch_school_absentees_today(PDO $pdo, int $schoolId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM school_absences WHERE school_id=? AND absent_date = CURDATE()");
    $stmt->execute([$schoolId]);
    return (int)$stmt->fetchColumn();
}
