<?php
/**
 * Direct messages API — private 1:1 chat.
 * Messaging is allowed ONLY between users with a follow relationship in either
 * direction (I follow them, they follow me, or mutual friends).
 *
 *   conversations (GET)            -> chats list with last message + unread count
 *   thread        (GET) ?user_id=  -> messages with a user (marks incoming read)
 *   unread        (GET)            -> { count } total unread (for the badge)
 *   send          (POST/json) { user_id, body, csrf } -> insert a message
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'Authentication required'], 401);

$pdo    = db();
$uid    = (int)$me['id'];
$action = $_GET['action'] ?? 'conversations';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* Lazy table creation — no separate migration required. */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `direct_messages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `body` TEXT NOT NULL,
        `read_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_dm_pair` (`sender_id`,`receiver_id`,`id`),
        INDEX `idx_dm_recv` (`receiver_id`,`read_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) { /* ignore */ }

/* Can $me message $other? Requires a follow link either way. */
function _dm_can(PDO $pdo, int $me, int $other): bool {
    if ($other <= 0 || $other === $me) return false;
    $s = $pdo->prepare('SELECT 1 FROM user_follows WHERE (follower_id=? AND following_id=?) OR (follower_id=? AND following_id=?) LIMIT 1');
    $s->execute([$me, $other, $other, $me]);
    return (bool)$s->fetchColumn();
}

function _dm_user(PDO $pdo, int $id): array {
    $s = $pdo->prepare('SELECT id, full_name, username, avatar_path, role FROM users WHERE id=? LIMIT 1');
    $s->execute([$id]);
    $u = $s->fetch();
    if (!$u) return ['id' => $id, 'full_name' => 'User', 'username' => '', 'avatar_path' => null, 'role' => ''];
    $u['id'] = (int)$u['id'];
    return $u;
}

/* ---- unread badge count ---- */
if ($action === 'unread' && !$isPost) {
    $c = (int)$pdo->query('SELECT COUNT(*) FROM direct_messages WHERE receiver_id=' . $uid . ' AND read_at IS NULL')->fetchColumn();
    json_response(['ok' => true, 'count' => $c]);
}

/* ---- conversations list ---- */
if ($action === 'conversations' && !$isPost) {
    $st = $pdo->prepare(
        'SELECT partner_id, MAX(id) AS last_id FROM (
            SELECT IF(sender_id=?, receiver_id, sender_id) AS partner_id, id
            FROM direct_messages WHERE sender_id=? OR receiver_id=?
         ) t GROUP BY partner_id ORDER BY last_id DESC LIMIT 100');
    $st->execute([$uid, $uid, $uid]);
    $rows = $st->fetchAll();
    $out = [];
    $msgStmt = $pdo->prepare('SELECT sender_id, body, created_at FROM direct_messages WHERE id=?');
    $unStmt  = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE sender_id=? AND receiver_id=? AND read_at IS NULL');
    foreach ($rows as $r) {
        $pid = (int)$r['partner_id'];
        $msgStmt->execute([(int)$r['last_id']]);
        $lm = $msgStmt->fetch();
        $unStmt->execute([$pid, $uid]);
        $out[] = [
            'user'         => _dm_user($pdo, $pid),
            'last_body'    => $lm ? $lm['body'] : '',
            'last_from_me' => $lm ? ((int)$lm['sender_id'] === $uid) : false,
            'last_at'      => $lm ? $lm['created_at'] : null,
            'unread'       => (int)$unStmt->fetchColumn(),
        ];
    }
    json_response(['ok' => true, 'conversations' => $out]);
}

/* ---- one thread ---- */
if ($action === 'thread' && !$isPost) {
    $other = (int)($_GET['user_id'] ?? 0);
    if ($other <= 0) json_response(['ok' => false, 'error' => 'user_id required'], 400);
    $after = (int)($_GET['after'] ?? 0);
    $sql = 'SELECT id, sender_id, receiver_id, body, read_at, created_at FROM direct_messages
            WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))';
    $params = [$uid, $other, $other, $uid];
    if ($after > 0) { $sql .= ' AND id>?'; $params[] = $after; }
    $sql .= ' ORDER BY id ASC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $msgs = $st->fetchAll();
    foreach ($msgs as &$m) { $m['id'] = (int)$m['id']; $m['mine'] = ((int)$m['sender_id'] === $uid); }
    unset($m);
    $pdo->prepare('UPDATE direct_messages SET read_at=NOW() WHERE receiver_id=? AND sender_id=? AND read_at IS NULL')->execute([$uid, $other]);
    json_response(['ok' => true, 'messages' => $msgs, 'user' => _dm_user($pdo, $other), 'can_send' => _dm_can($pdo, $uid, $other)]);
}

/* ---- mutations: POST + CSRF ---- */
if (!$isPost) json_response(['ok' => false, 'error' => 'POST required'], 405);
$in = [];
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) { $in = read_json_input(); }
$csrf = $in['csrf'] ?? ($_POST['csrf'] ?? null);
if (!csrf_check($csrf)) json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);

if ($action === 'send') {
    $other = (int)($in['user_id'] ?? 0);
    $body  = trim((string)($in['body'] ?? ''));
    if ($other <= 0) json_response(['ok' => false, 'error' => 'user_id required'], 400);
    if ($body === '') json_response(['ok' => false, 'error' => 'Message is empty.'], 400);
    if (mb_strlen($body) > 4000) json_response(['ok' => false, 'error' => 'Message too long (max 4000).'], 400);
    if (!_dm_can($pdo, $uid, $other)) json_response(['ok' => false, 'error' => 'You can only message friends, followers or people you follow.'], 403);
    $pdo->prepare('INSERT INTO direct_messages (sender_id, receiver_id, body, created_at) VALUES (?,?,?,NOW())')->execute([$uid, $other, $body]);
    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'created_at' => date('Y-m-d H:i:s')]);
}

json_response(['ok' => false, 'error' => 'Unknown action'], 400);
