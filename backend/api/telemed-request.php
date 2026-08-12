<?php
/**
 * H1 v2 — Téléconsultation in-app avec salle d'attente 15 min.
 *
 * Flux :
 *  1. Citizen POST {action:'create'} → crée une ligne `waiting`, expires_at = NOW + 15min.
 *     Une notification est envoyée à tous les agents `health` + `admin` pour les inviter
 *     à rejoindre la salle.
 *  2. Citizen GET ?id=N → polling toutes les 4 s pour savoir si un soignant a rejoint
 *     (status passe de `waiting` à `joined`).
 *  3. Health POST {action:'join', id:N} → marque la demande comme `joined`. Le citoyen
 *     voit le iframe Jitsi se charger.
 *  4. POST {action:'close', id:N} (citoyen ou soignant) → marque la salle `closed`.
 *  5. À chaque GET ou liste, on auto-passe en `expired` toutes les `waiting` dont
 *     expires_at < NOW. La salle est donc "fermée automatiquement après 15 min".
 *
 * Sécurité :
 *  - Le citoyen ne peut consulter / fermer que SES propres demandes.
 *  - Les rôles `health` et `admin` peuvent rejoindre / fermer toute demande.
 *  - Le nom de salle est dérivé d'un sha256(secret + id + ts) → non-devinable.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

const TELEMED_SALT_V2 = 'gabes-tatenafas-telemed-2026-v2';
const TELEMED_TTL_MIN = 15;

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

/* ---------- Auto-expiration des waiting > 15 min (à chaque appel) ---------- */
$pdo->exec(
    "UPDATE telemed_requests
       SET status = 'expired', closed_at = NOW()
     WHERE status = 'waiting' AND expires_at <= NOW()"
);

/* ---------- GET : lecture du statut (citoyen) ou liste (health/admin) ---------- */
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        // Read a specific request — a citizen can only view their own.
        // We include `seconds_remaining` computed by MySQL so the client does not
        // have to parse a DATETIME string (which is timezone-ambiguous in JS).
        $stmt = $pdo->prepare(
            "SELECT id, citizen_id, room, status, joined_health_id,
                    requested_at, expires_at, joined_at, closed_at,
                    pre_consult, post_consult,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS seconds_remaining
               FROM telemed_requests WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) json_response(['ok' => false, 'error' => 'not_found'], 404);

        if ($me['role'] === 'citizen' && (int)$req['citizen_id'] !== (int)$me['id']) {
            json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        json_response(['ok' => true, 'request' => $req]);
    }

    // Without id: list of consultations.
    //  - health → only active (waiting / joined) so they can answer
    //  - admin  → ALL recent consultations (waiting, joined, closed, expired)
    //             so they can monitor activity but cannot join
    //  - citizen → forbidden
    if ($me['role'] !== 'health' && $me['role'] !== 'admin') {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }

    if ($me['role'] === 'admin') {
        $stmt = $pdo->query(
            "SELECT t.id, t.citizen_id, t.room, t.status, t.requested_at, t.expires_at,
                    t.joined_at, t.closed_at, t.joined_health_id,
                    t.pre_consult, t.post_consult,
                    u.full_name  AS citizen_name,
                    u.username   AS citizen_username,
                    z.name       AS zone_name,
                    h.full_name  AS health_name,
                    h.username   AS health_username,
                    TIMESTAMPDIFF(SECOND, NOW(), t.expires_at) AS seconds_remaining
               FROM telemed_requests t
               LEFT JOIN users u ON u.id = t.citizen_id
               LEFT JOIN zones z ON z.id = u.zone_id
               LEFT JOIN users h ON h.id = t.joined_health_id
              ORDER BY t.requested_at DESC
              LIMIT 50"
        );
    } else {
        // health
        $stmt = $pdo->query(
            "SELECT t.id, t.citizen_id, t.room, t.status, t.requested_at, t.expires_at,
                    t.joined_at, t.closed_at,
                    t.pre_consult, t.post_consult,
                    u.full_name AS citizen_name,
                    u.username  AS citizen_username,
                    z.name      AS zone_name,
                    TIMESTAMPDIFF(SECOND, NOW(), t.expires_at) AS seconds_remaining
               FROM telemed_requests t
               LEFT JOIN users u ON u.id = t.citizen_id
               LEFT JOIN zones z ON z.id = u.zone_id
              WHERE t.status IN ('waiting','joined')
              ORDER BY t.status DESC, t.requested_at DESC
              LIMIT 50"
        );
    }
    json_response([
        'ok'        => true,
        'role'      => $me['role'],
        'requests'  => $stmt->fetchAll(),
    ]);
}

/* ---------- POST : actions ---------- */
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$in = read_json_input();
$action = (string)($in['action'] ?? '');

if ($action === 'create') {
    if ($me['role'] !== 'citizen') {
        json_response(['ok' => false, 'error' => 'citizen_only'], 403);
    }

    // If an active request already exists for this citizen, reuse it.
    $exist = $pdo->prepare(
        "SELECT id, room, status, expires_at, pre_consult,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS seconds_remaining
           FROM telemed_requests
          WHERE citizen_id = ? AND status IN ('waiting','joined')
          ORDER BY id DESC LIMIT 1"
    );
    $exist->execute([(int)$me['id']]);
    $cur = $exist->fetch();
    if ($cur) {
        json_response(['ok' => true, 'request' => $cur, 'reused' => true]);
    }

    // Optional pre-consultation checklist payload (vitals + symptoms + notes).
    // We accept loose input and sanitise before storing.
    $pre = $in['pre_consult'] ?? null;
    $preJson = null;
    if (is_array($pre)) {
        $preClean = [
            'temperature' => isset($pre['temperature']) ? (float)$pre['temperature'] : null,
            'pulse'       => isset($pre['pulse'])       ? (int)$pre['pulse']         : null,
            'oxygen_sat'  => isset($pre['oxygen_sat'])  ? (int)$pre['oxygen_sat']    : null,
            'symptoms'    => isset($pre['symptoms'])    ? mb_substr((string)$pre['symptoms'], 0, 500) : '',
            'notes'       => isset($pre['notes'])       ? mb_substr((string)$pre['notes'],    0, 1000) : '',
            'photo_url'   => isset($pre['photo_url'])   ? mb_substr((string)$pre['photo_url'], 0, 500) : null,
        ];
        $preJson = json_encode($preClean, JSON_UNESCAPED_UNICODE);
    }

    $room = 'NafassGabes-' . (int)$me['id'] . '-'
          . substr(hash('sha256', TELEMED_SALT_V2 . '|' . (int)$me['id'] . '|' . microtime(true)), 0, 12);

    $ins = $pdo->prepare(
        "INSERT INTO telemed_requests (citizen_id, room, status, expires_at, pre_consult)
         VALUES (?, ?, 'waiting', NOW() + INTERVAL ? MINUTE, ?)"
    );
    $ins->execute([(int)$me['id'], $room, TELEMED_TTL_MIN, $preJson]);
    $reqId = (int)$pdo->lastInsertId();

    // Targeted notification for health staff (joinable) and admins (informational)
    $title       = 'Telemedicine request';
    $msgHealth   = sprintf(
        '%s is requesting a medical consultation. Room: %s — available for %d min.',
        $me['full_name'] ?: $me['username'],
        $room,
        TELEMED_TTL_MIN
    );
    $msgAdmin    = sprintf(
        '%s requested a consultation (room %s, expires in %d min). Monitoring only.',
        $me['full_name'] ?: $me['username'],
        $room,
        TELEMED_TTL_MIN
    );
    try {
        $note = $pdo->prepare(
            "INSERT INTO notifications (target_role, title, message, level, priority)
             VALUES (?, ?, ?, 'warning', 8)"
        );
        $note->execute(['health', $title, $msgHealth]);
        $note->execute(['admin',  $title, $msgAdmin]);
    } catch (Throwable $e) {
        error_log('[telemed-request notify] ' . $e->getMessage());
    }

    // Return the freshly-created row (with seconds_remaining computed on the server).
    $sel = $pdo->prepare(
        "SELECT id, citizen_id, room, status, requested_at, expires_at, pre_consult,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS seconds_remaining
           FROM telemed_requests WHERE id = ?"
    );
    $sel->execute([$reqId]);
    json_response(['ok' => true, 'request' => $sel->fetch()]);
}

if ($action === 'finalize') {
    // Health staff records a post-consultation summary (diagnosis, recommendations,
    // prescription, follow-up). Citizen will be able to read this in their diary.
    if ($me['role'] !== 'health') {
        json_response(['ok' => false, 'error' => 'health_only'], 403);
    }
    $id   = isset($in['id']) ? (int)$in['id'] : 0;
    $post = $in['post_consult'] ?? null;
    if ($id <= 0) json_response(['ok' => false, 'error' => 'id_required'], 400);
    if (!is_array($post)) json_response(['ok' => false, 'error' => 'post_consult_required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM telemed_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) json_response(['ok' => false, 'error' => 'not_found'], 404);

    $clean = [
        'diagnosis'       => mb_substr((string)($post['diagnosis']       ?? ''), 0, 500),
        'recommendations' => mb_substr((string)($post['recommendations'] ?? ''), 0, 1500),
        'prescription'    => mb_substr((string)($post['prescription']    ?? ''), 0, 1500),
        'follow_up_days'  => isset($post['follow_up_days']) ? (int)$post['follow_up_days'] : null,
        'doctor_name'     => $me['full_name'] ?: $me['username'],
        'finalized_at'    => date('Y-m-d H:i:s'),
    ];

    $upd = $pdo->prepare(
        "UPDATE telemed_requests
            SET post_consult = ?,
                status = CASE WHEN status IN ('waiting','joined') THEN 'closed' ELSE status END,
                closed_at = COALESCE(closed_at, NOW())
          WHERE id = ?"
    );
    $upd->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $id]);

    // Notify the citizen so they see their consultation summary in real time.
    try {
        $note = $pdo->prepare(
            "INSERT INTO notifications (target_user_id, title, message, level, priority)
             VALUES (?, ?, ?, 'info', 5)"
        );
        $note->execute([
            (int)$req['citizen_id'],
            'Consultation summary available',
            'Your doctor has saved a summary of the consultation. Open the Health Diary to read it.',
        ]);
    } catch (Throwable $e) {
        error_log('[telemed finalize notify] ' . $e->getMessage());
    }

    json_response(['ok' => true, 'finalized' => true]);
}

if ($action === 'join') {
    // Only health staff (Sanitaire) may actually join a consultation room.
    // Admins can monitor but never enter the call.
    if ($me['role'] !== 'health') {
        json_response(['ok' => false, 'error' => 'health_only'], 403);
    }
    $id = isset($in['id']) ? (int)$in['id'] : 0;
    if ($id <= 0) json_response(['ok' => false, 'error' => 'id_required'], 400);

    $stmt = $pdo->prepare(
        "SELECT * FROM telemed_requests WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) json_response(['ok' => false, 'error' => 'not_found'], 404);
    if ($req['status'] === 'expired' || $req['status'] === 'closed') {
        json_response(['ok' => false, 'error' => 'request_' . $req['status']], 410);
    }

    $upd = $pdo->prepare(
        "UPDATE telemed_requests
            SET status = 'joined', joined_health_id = ?, joined_at = NOW()
          WHERE id = ?"
    );
    $upd->execute([(int)$me['id'], $id]);

    json_response([
        'ok'   => true,
        'room' => $req['room'],
        'id'   => $id,
    ]);
}

if ($action === 'close') {
    $id = isset($in['id']) ? (int)$in['id'] : 0;
    if ($id <= 0) json_response(['ok' => false, 'error' => 'id_required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM telemed_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $req = $stmt->fetch();
    if (!$req) json_response(['ok' => false, 'error' => 'not_found'], 404);

    // Citoyen ne peut fermer que sa propre demande
    if ($me['role'] === 'citizen' && (int)$req['citizen_id'] !== (int)$me['id']) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }

    $pdo->prepare(
        "UPDATE telemed_requests
            SET status = 'closed', closed_at = NOW()
          WHERE id = ? AND status IN ('waiting','joined')"
    )->execute([$id]);

    json_response(['ok' => true, 'closed' => true]);
}

json_response(['ok' => false, 'error' => 'unknown_action'], 400);
