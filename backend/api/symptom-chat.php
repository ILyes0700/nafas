<?php
/**
 * Endpoint de discussion santé ↔ citoyen autour d'un symptôme.
 *
 *  GET  ?action=threads&status=new|in_progress|resolved|all   (rôle health/admin)
 *       Liste des fils avec dernier message + status + compteur
 *  GET  ?action=thread&symptom_id=X
 *       Détail d'un symptôme + tous les messages (ordre asc)
 *  GET  ?action=my
 *       Citoyen connecté → ses fils où l'équipe santé a déjà répondu
 *  GET  ?action=unread_health
 *       Compteur de symptômes "nouveaux" non traités (badge rapide)
 *  POST action=send       body: { symptom_id, message }
 *  POST action=set_status body: { symptom_id, status }
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$pdo  = db();
$me   = auth_user();
if (!$me) {
    json_response(['ok' => false, 'error' => 'unauthenticated'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_REQUEST['action'] ?? '');
if ($method === 'POST' && empty($action)) {
    $tmp = read_json_input();
    $action = $tmp['action'] ?? '';
}

/* ---------------------------------------------------------------- helpers */

function fetch_symptom(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        "SELECT s.*, z.name AS zone_name, u.full_name AS citizen_full_name, u.username AS citizen_username
           FROM symptoms s
      LEFT JOIN zones z ON z.id = s.zone_id
      LEFT JOIN users u ON u.id = s.citizen_id
          WHERE s.id = ?"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Le citoyen connecté est-il propriétaire de ce symptôme ? */
function citizen_owns(array $me, array $sym): bool
{
    if ($me['role'] !== 'citizen') return false;
    if (!empty($sym['citizen_id']) && (int)$sym['citizen_id'] === (int)$me['id']) return true;
    // fallback : ancien enregistrement sans FK → on compare le nom
    $name = (string)($sym['citizen_name'] ?? '');
    if ($name === '') return false;
    if ($name === ($me['full_name'] ?? '')) return true;
    if ($name === ($me['username'] ?? ''))  return true;
    return false;
}

/** Compte le nbre de messages (sender_role IN ...) sur un fil */
function count_messages(PDO $pdo, int $symId, array $roles = []): int
{
    if (!$roles) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM symptom_messages WHERE symptom_id = ?");
        $st->execute([$symId]);
    } else {
        $in = implode(',', array_fill(0, count($roles), '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM symptom_messages WHERE symptom_id = ? AND sender_role IN ($in)");
        $st->execute(array_merge([$symId], $roles));
    }
    return (int)$st->fetchColumn();
}

/* -------------------------------------------------------------- dispatch */

if ($method === 'GET' && $action === 'threads') {
    if (!in_array($me['role'], ['health', 'admin'], true)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $status = $_GET['status'] ?? 'all';
    $where  = '';
    $params = [];
    if (in_array($status, ['new', 'in_progress', 'resolved'], true)) {
        $where = 'WHERE s.status = ?';
        $params[] = $status;
    }
    $sql = "
      SELECT s.id, s.zone_id, s.citizen_id, s.citizen_name, s.symptom, s.severity,
             s.notes, s.status, s.reported_at,
             z.name AS zone_name,
             (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id) AS msg_count,
             (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id AND m.sender_role IN ('health','admin')) AS health_msg_count,
             (SELECT MAX(created_at) FROM symptom_messages m WHERE m.symptom_id = s.id) AS last_msg_at,
             (SELECT message FROM symptom_messages m WHERE m.symptom_id = s.id ORDER BY id DESC LIMIT 1) AS last_msg
        FROM symptoms s
   LEFT JOIN zones z ON z.id = s.zone_id
        $where
    ORDER BY (s.status = 'new') DESC,
             COALESCE(last_msg_at, s.reported_at) DESC
        LIMIT 200
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    // compteurs pour les pills
    $counts = $pdo->query("SELECT status, COUNT(*) c FROM symptoms GROUP BY status")->fetchAll();
    $cntMap = ['new' => 0, 'in_progress' => 0, 'resolved' => 0];
    foreach ($counts as $c) $cntMap[$c['status']] = (int)$c['c'];
    $cntMap['all'] = array_sum($cntMap);

    json_response(['ok' => true, 'threads' => $rows, 'counts' => $cntMap]);
}

if ($method === 'GET' && $action === 'thread') {
    $symId = (int)($_GET['symptom_id'] ?? 0);
    if ($symId <= 0) json_response(['ok' => false, 'error' => 'bad_id'], 400);
    $sym = fetch_symptom($pdo, $symId);
    if (!$sym) json_response(['ok' => false, 'error' => 'not_found'], 404);

    $isStaff = in_array($me['role'], ['health', 'admin'], true);
    $owns    = citizen_owns($me, $sym);
    $healthCount = count_messages($pdo, $symId, ['health', 'admin']);

    if (!$isStaff) {
        // côté citoyen : autorisé seulement s'il est propriétaire ET qu'au moins
        // un message santé existe (sinon le fil n'est pas encore "ouvert").
        if (!$owns)            json_response(['ok' => false, 'error' => 'forbidden'], 403);
        if ($healthCount === 0) json_response(['ok' => false, 'error' => 'not_open'], 403);
    }

    $msgs = $pdo->prepare(
        "SELECT id, sender_id, sender_role, sender_name, message, created_at, read_at
           FROM symptom_messages WHERE symptom_id = ? ORDER BY id ASC"
    );
    $msgs->execute([$symId]);
    $messages = $msgs->fetchAll();

    // marquer comme lus : les messages "à destination de moi"
    $myRole = $isStaff ? ['citizen'] : ['health', 'admin'];
    $in = implode(',', array_fill(0, count($myRole), '?'));
    $upd = $pdo->prepare(
        "UPDATE symptom_messages SET read_at = NOW()
          WHERE symptom_id = ? AND read_at IS NULL AND sender_role IN ($in)"
    );
    $upd->execute(array_merge([$symId], $myRole));

    json_response([
        'ok'        => true,
        'symptom'   => $sym,
        'messages'  => $messages,
        'is_staff'  => $isStaff,
        'is_open'   => $healthCount > 0,
    ]);
}

if ($method === 'GET' && $action === 'my') {
    if ($me['role'] !== 'citizen') {
        json_response(['ok' => true, 'threads' => []]); // staff n'utilise pas cette vue
    }
    // Tous mes symptômes où il y a au moins 1 msg santé
    $sql = "
      SELECT s.id, s.zone_id, s.citizen_id, s.citizen_name, s.symptom, s.severity,
             s.notes, s.status, s.reported_at,
             z.name AS zone_name,
             (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id AND m.sender_role IN ('health','admin')) AS health_msg_count,
             (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id AND m.sender_role IN ('health','admin') AND m.read_at IS NULL) AS unread,
             (SELECT MAX(created_at) FROM symptom_messages m WHERE m.symptom_id = s.id) AS last_msg_at,
             (SELECT message FROM symptom_messages m WHERE m.symptom_id = s.id ORDER BY id DESC LIMIT 1) AS last_msg
        FROM symptoms s
   LEFT JOIN zones z ON z.id = s.zone_id
       WHERE (s.citizen_id = ? OR (s.citizen_id IS NULL AND s.citizen_name IN (?, ?)))
         AND (SELECT COUNT(*) FROM symptom_messages m WHERE m.symptom_id = s.id AND m.sender_role IN ('health','admin')) > 0
    ORDER BY last_msg_at DESC
       LIMIT 100
    ";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$me['id'], (string)($me['full_name'] ?? ''), (string)($me['username'] ?? '')]);
    json_response(['ok' => true, 'threads' => $st->fetchAll()]);
}

if ($method === 'GET' && $action === 'unread_health') {
    if (!in_array($me['role'], ['health', 'admin'], true)) {
        json_response(['ok' => true, 'count' => 0]);
    }
    $n = (int)$pdo->query("SELECT COUNT(*) FROM symptoms WHERE status = 'new'")->fetchColumn();
    json_response(['ok' => true, 'count' => $n]);
}

if ($method === 'POST' && $action === 'send') {
    $in = read_json_input();
    $symId   = (int)($in['symptom_id'] ?? 0);
    $message = trim((string)($in['message'] ?? ''));
    if ($symId <= 0)        json_response(['ok' => false, 'error' => 'bad_id'], 400);
    if ($message === '')    json_response(['ok' => false, 'error' => 'empty'], 400);
    if (mb_strlen($message) > 4000) $message = mb_substr($message, 0, 4000);

    $sym = fetch_symptom($pdo, $symId);
    if (!$sym) json_response(['ok' => false, 'error' => 'not_found'], 404);

    $isStaff = in_array($me['role'], ['health', 'admin'], true);
    $owns    = citizen_owns($me, $sym);

    if (!$isStaff && !$owns) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }

    if (!$isStaff) {
        // citoyen : refusé tant qu'il n'y a pas de message santé
        $hc = count_messages($pdo, $symId, ['health', 'admin']);
        if ($hc === 0) json_response(['ok' => false, 'error' => 'not_open'], 403);
    }

    $ins = $pdo->prepare(
        "INSERT INTO symptom_messages (symptom_id, sender_id, sender_role, sender_name, message)
         VALUES (?,?,?,?,?)"
    );
    $ins->execute([
        $symId,
        (int)$me['id'],
        $me['role'],
        $me['full_name'] ?: $me['username'],
        $message,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // dès le premier message santé, on bascule le symptôme en 'in_progress'
    if ($isStaff && ($sym['status'] ?? 'new') === 'new') {
        $pdo->prepare("UPDATE symptoms SET status = 'in_progress' WHERE id = ?")
            ->execute([$symId]);
    }

    $msg = $pdo->prepare("SELECT * FROM symptom_messages WHERE id = ?");
    $msg->execute([$newId]);
    $row = $msg->fetch();

    json_response(['ok' => true, 'message' => $row]);
}

if ($method === 'POST' && $action === 'set_status') {
    if (!in_array($me['role'], ['health', 'admin'], true)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $in = read_json_input();
    $symId  = (int)($in['symptom_id'] ?? 0);
    $status = (string)($in['status'] ?? '');
    if (!in_array($status, ['new', 'in_progress', 'resolved'], true)) {
        json_response(['ok' => false, 'error' => 'bad_status'], 400);
    }
    $st = $pdo->prepare("UPDATE symptoms SET status = ? WHERE id = ?");
    $st->execute([$status, $symId]);
    json_response(['ok' => true, 'symptom_id' => $symId, 'status' => $status]);
}

json_response(['ok' => false, 'error' => 'unknown_action', 'method' => $method, 'action' => $action], 400);
