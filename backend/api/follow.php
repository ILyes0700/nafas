<?php
/**
 * Follow / unfollow API. Every authenticated user can follow any other user.
 *   status  (GET)        ?user_id=  -> { following, followers, following_count }
 *   toggle  (POST/json)  { user_id } -> { following }
 *   follow  (POST/json)  { user_id }
 *   unfollow(POST/json)  { user_id }
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'Authentication required'], 401);

$pdo    = db();
$uid    = (int)$me['id'];
$action = $_GET['action'] ?? 'toggle';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

function _fl_counts(PDO $pdo, int $target): array {
    $followers = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE following_id=' . $target)->fetchColumn();
    $following = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE follower_id=' . $target)->fetchColumn();
    return ['followers' => $followers, 'following_count' => $following];
}

/* Run a people-list query and annotate each row with the viewer's follow state. */
function _fl_people(PDO $pdo, string $sql, array $params, int $viewer): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    $chk = $pdo->prepare('SELECT 1 FROM user_follows WHERE follower_id=? AND following_id=? LIMIT 1');
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $chk->execute([$viewer, $r['id']]);
        $r['is_following'] = (bool)$chk->fetchColumn();
        $r['is_me'] = ($r['id'] === $viewer);
    }
    return $rows;
}

if ($action === 'status' && !$isPost) {
    $target = (int)($_GET['user_id'] ?? 0);
    if ($target <= 0) json_response(['ok' => false, 'error' => 'user_id required'], 400);
    $s = $pdo->prepare('SELECT 1 FROM user_follows WHERE follower_id=? AND following_id=? LIMIT 1');
    $s->execute([$uid, $target]);
    json_response(['ok' => true, 'following' => (bool)$s->fetchColumn()] + _fl_counts($pdo, $target));
}

/* People lists for the profile "Friends" zone (followers / following / mutual). */
if ($action === 'list' && !$isPost) {
    $target = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $uid;
    if ($target <= 0) $target = $uid;
    $cols = 'u.id, u.full_name, u.username, u.avatar_path, u.role';
    $followers = _fl_people($pdo,
        "SELECT $cols FROM user_follows f JOIN users u ON u.id=f.follower_id
         WHERE f.following_id=? ORDER BY f.created_at DESC LIMIT 300", [$target], $uid);
    $following = _fl_people($pdo,
        "SELECT $cols FROM user_follows f JOIN users u ON u.id=f.following_id
         WHERE f.follower_id=? ORDER BY f.created_at DESC LIMIT 300", [$target], $uid);
    $friends = _fl_people($pdo,
        "SELECT $cols FROM user_follows a
         JOIN user_follows b ON a.following_id=b.follower_id AND a.follower_id=b.following_id
         JOIN users u ON u.id=a.following_id
         WHERE a.follower_id=? ORDER BY u.full_name ASC LIMIT 300", [$target], $uid);
    json_response(['ok' => true,
        'friends' => $friends, 'followers' => $followers, 'following' => $following,
        'counts' => ['friends' => count($friends), 'followers' => count($followers), 'following' => count($following)],
    ]);
}

/* People search for the global search bar (find anyone by name / @username). */
if ($action === 'search' && !$isPost) {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') json_response(['ok' => true, 'people' => []]);
    $like = '%' . $q . '%';
    $cols = 'u.id, u.full_name, u.username, u.avatar_path, u.role';
    $people = _fl_people($pdo,
        "SELECT $cols FROM users u
         WHERE (u.full_name LIKE ? OR u.username LIKE ?) AND u.id <> ?
         ORDER BY (CASE WHEN u.full_name = ? OR u.username = ? THEN 0 ELSE 1 END) ASC, u.full_name ASC
         LIMIT 20",
        [$like, $like, $uid, $q, $q], $uid);
    json_response(['ok' => true, 'people' => $people]);
}

if (!$isPost) json_response(['ok' => false, 'error' => 'POST required'], 405);
$in = [];
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) { $in = read_json_input(); }
$csrf = $in['csrf'] ?? ($_POST['csrf'] ?? null);
if (!csrf_check($csrf)) json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);

$target = (int)($in['user_id'] ?? 0);
if ($target <= 0) json_response(['ok' => false, 'error' => 'user_id required'], 400);
if ($target === $uid) json_response(['ok' => false, 'error' => "You can't follow yourself."], 400);

$chk = $pdo->prepare('SELECT 1 FROM users WHERE id=? LIMIT 1'); $chk->execute([$target]);
if (!$chk->fetchColumn()) json_response(['ok' => false, 'error' => 'User not found.'], 404);

$ex = $pdo->prepare('SELECT 1 FROM user_follows WHERE follower_id=? AND following_id=? LIMIT 1');
$ex->execute([$uid, $target]);
$already = (bool)$ex->fetchColumn();

$want = $action === 'follow' ? true : ($action === 'unfollow' ? false : !$already);
if ($want && !$already) {
    $pdo->prepare('INSERT IGNORE INTO user_follows (follower_id, following_id, created_at) VALUES (?,?,NOW())')->execute([$uid, $target]);
} elseif (!$want && $already) {
    $pdo->prepare('DELETE FROM user_follows WHERE follower_id=? AND following_id=?')->execute([$uid, $target]);
}
json_response(['ok' => true, 'following' => $want] + _fl_counts($pdo, $target));
