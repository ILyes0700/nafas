<?php
/**
 * Citizen-facing history of their telemedicine consultations,
 * including the doctor's post-consultation notes (diagnosis, recommendations,
 * prescription, follow-up).
 *
 * Health staff and admins can also fetch any citizen's history by passing
 * `?citizen_id=N`. Citizens are forced to their own id.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo = db();

$citizenId = (int)$me['id'];
if (in_array($me['role'], ['health', 'admin'], true) && isset($_GET['citizen_id'])) {
    $citizenId = (int)$_GET['citizen_id'];
}

$stmt = $pdo->prepare(
    "SELECT t.id, t.room, t.status, t.requested_at, t.joined_at, t.closed_at,
            t.pre_consult, t.post_consult,
            h.full_name AS doctor_name
       FROM telemed_requests t
       LEFT JOIN users h ON h.id = t.joined_health_id
      WHERE t.citizen_id = ?
      ORDER BY t.id DESC
      LIMIT 50"
);
$stmt->execute([$citizenId]);

json_response([
    'ok'             => true,
    'citizen_id'     => $citizenId,
    'consultations'  => $stmt->fetchAll(),
]);
