<?php
/**
 * Social wall API (Facebook-style feed) - available to every authenticated user.
 * Actions:
 *   list           (GET)                 posts (paginated) + author, reactions, my reaction, nested comments, saved, follow state
 *   create         (POST multipart/json) create a post: body text and/or one media (image/video/doc/file)
 *   delete         (POST/json)           delete own post (admins can delete any)
 *   react          (POST/json)           set/toggle an emoji reaction on a post
 *   comment        (POST/json)           add a comment (optional parent_id for a reply)
 *   edit-comment   (POST/json)           edit own comment
 *   delete-comment (POST/json)           delete own comment (admins can delete any)
 *   save / unsave  (POST/json)           bookmark a post for later
 *   report         (POST/json)           flag a post (accepted, no moderation UI yet)
 * Media is stored under <project-root>/uploads/posts/<uid>/ and served statically.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mailer.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'Authentication required'], 401);

$pdo     = db();
$uid     = (int)$me['id'];
$role    = $me['role'] ?? 'citizen';
$isAdmin = in_array($role, ['admin', 'super_admin'], true);
$action  = $_GET['action'] ?? 'list';
$isPost  = ($_SERVER['REQUEST_METHOD'] === 'POST');

$UP_ROOT = __DIR__ . '/../../uploads';
$P_DIR   = $UP_ROOT . '/posts/' . $uid;

/* Lazy schema upgrades (repost + edit support) so no re-install is needed. */
try { $pdo->exec("ALTER TABLE feed_posts ADD COLUMN shared_from BIGINT UNSIGNED NULL"); } catch (Throwable $e) { /* already exists */ }
try { $pdo->exec("ALTER TABLE feed_posts ADD COLUMN edited_at DATETIME NULL"); } catch (Throwable $e) { /* already exists */ }

/* Allowed reaction emojis (whitelist): Like, Love, Haha, Wow, Sad, Angry, Applause, Fire, Celebrate, Excellent. */
$ALLOWED_EMOJI = ["\u{1F44D}", "\u{2764}\u{FE0F}", "\u{1F602}", "\u{1F62E}", "\u{1F622}", "\u{1F621}", "\u{1F44F}", "\u{1F525}", "\u{1F389}", "\u{1F4AF}"];

function _fd_ensure(string $d): void { if (!is_dir($d)) @mkdir($d, 0775, true); }
function _fd_ext(string $name): string { $e = strtolower(pathinfo($name, PATHINFO_EXTENSION)); return preg_replace('/[^a-z0-9]+/', '', $e); }
function _fd_safe(string $name): string { $n = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name); return substr($n, 0, 120); }
function _fd_author(array $r): array {
    $name = trim((string)($r['full_name'] ?? ''));
    if ($name === '') $name = trim(((string)($r['first_name'] ?? '')) . ' ' . ((string)($r['last_name'] ?? '')));
    if ($name === '') $name = (string)($r['username'] ?? 'User');
    return ['name' => $name, 'role' => $r['role'] ?? '', 'avatar' => $r['avatar_path'] ?? null, 'username' => $r['username'] ?? ''];
}
function _fd_kind(string $name): string {
    $ext = _fd_ext($name);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return 'image';
    if (in_array($ext, ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'], true)) return 'video';
    if (in_array($ext, ['txt', 'md', 'doc', 'docx', 'pdf', 'odt', 'rtf', 'ppt', 'pptx', 'xls', 'xlsx'], true)) return 'doc';
    return 'file';
}

/* Roles whose posts are emailed to every member (news / official announcements). */
$FD_BROADCAST_ROLES = ['super_admin', 'admin', 'school', 'health'];

/* Guess a MIME type from a file path (for email attachments). */
function _fd_mime_for(string $path): string {
    $e = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'm4v' => 'video/x-m4v', 'ogv' => 'video/ogg',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/plain', 'rtf' => 'application/rtf', 'odt' => 'application/vnd.oasis.opendocument.text',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    return $map[$e] ?? 'application/octet-stream';
}

/* Send a JSON response to the client now, then keep running for background work (email fan-out). */
function _fd_respond_and_continue(array $data): void {
    @ignore_user_abort(true);
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Content-Length: ' . strlen($payload));
        header('Connection: close');
    }
    echo $payload;
    @flush();
    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    @set_time_limit(0);
}

/* Email every user when a privileged role posts an update (photo/file included). Never blocks/breaks posting. */
function _fd_broadcast_post_email(PDO $pdo, array $author, string $body, ?string $attachAbs, ?string $attachKind, ?string $attachName): int {
    $roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'school' => 'School', 'health' => 'Doctor'];
    $authorName = trim((string)($author['full_name'] ?? ''));
    if ($authorName === '') $authorName = (string)($author['username'] ?? 'Staff');
    $roleLbl = $roleLabels[$author['role'] ?? ''] ?? 'Staff';
    $excerpt = trim($body);
    if ($excerpt === '' && $attachAbs) $excerpt = '(shared an attachment)';
    if (function_exists('mb_strlen') && mb_strlen($excerpt) > 600) $excerpt = mb_substr($excerpt, 0, 600) . "\xE2\x80\xA6";
    $appName = defined('APP_NAME') ? APP_NAME : 'Nafass';
    $link    = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    $esc     = static function (string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    // Include the photo/file so recipients get it in the email too.
    $attachments = []; $mediaHtml = '';
    if ($attachAbs && is_file($attachAbs)) {
        $fname = ($attachName !== null && $attachName !== '') ? $attachName : basename($attachAbs);
        $mime  = _fd_mime_for($attachAbs);
        $att   = ['path' => $attachAbs, 'name' => $fname, 'mime' => $mime];
        if ($attachKind === 'image') {
            $cid = 'postimg_' . bin2hex(random_bytes(4));
            $att['cid'] = $cid;
            $mediaHtml = '<div style="margin-top:14px"><img src="cid:' . $cid . '" alt="" style="max-width:100%;border-radius:10px;border:1px solid #e6e9ef"></div>';
        } else {
            $mediaHtml = '<p style="margin:14px 0 0;color:#555;font-size:13px">Attachment included: <b>' . $esc($fname) . '</b></p>';
        }
        $attachments[] = $att;
    }

    $subject = '[' . $appName . '] ' . $authorName . ' (' . $roleLbl . ') posted an update';
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto">'
        . '<h2 style="color:#1d4e89;margin:0 0 4px">' . $esc($appName) . '</h2>'
        . '<p style="color:#555;margin:0 0 16px">New update from <b>' . $esc($authorName) . '</b> &middot; ' . $esc($roleLbl) . '</p>'
        . '<div style="background:#f5f7fa;border:1px solid #e6e9ef;border-radius:10px;padding:16px;color:#222;line-height:1.6">' . nl2br($esc($excerpt)) . '</div>'
        . $mediaHtml
        . ($link ? '<p style="margin:20px 0 0"><a href="' . $esc($link) . '" style="background:#1d4e89;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;display:inline-block">Open ' . $esc($appName) . '</a></p>' : '')
        . '<p style="color:#999;font-size:12px;margin-top:24px">You are receiving this because you are a member of ' . $esc($appName) . '.</p>'
        . '</div>';
    $sent = 0;
    try {
        $st = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email <> ''");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $email = trim((string)$email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            try { if (mailer_send($email, $subject, $html, '', $attachments)) $sent++; } catch (Throwable $e) { /* skip one */ }
            if ($sent >= 1000) break; // safety cap
        }
    } catch (Throwable $e) { /* users query failed - ignore */ }
    return $sent;
}

/* =======================  LIST  ======================= */
if ($action === 'list') {
    $limit    = 10;
    $before   = isset($_GET['before'])  ? (int)$_GET['before']  : 0;
    $authorId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0; // filter a single user's wall
    try {
        $savedOnly = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
        $where = []; $args = [];
        if ($savedOnly)    { $where[] = 'sv.user_id = ?'; $args[] = $uid; }
        if ($authorId > 0) { $where[] = 'p.user_id = ?'; $args[] = $authorId; }
        if ($before > 0)   { $where[] = 'p.id < ?';      $args[] = $before; }
        $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $fromSql = $savedOnly
            ? 'FROM post_saves sv JOIN feed_posts p ON p.id = sv.post_id JOIN users u ON u.id = p.user_id'
            : 'FROM feed_posts p JOIN users u ON u.id = p.user_id';
        $st = $pdo->prepare('SELECT p.*, u.username, u.full_name, u.first_name, u.last_name, u.role, u.avatar_path '
                             . $fromSql
                             . $wsql . ' ORDER BY p.id DESC LIMIT ' . (int)$limit);
        $st->execute($args);
        $posts = $st->fetchAll();
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'DB not ready: ' . $e->getMessage() . ' - Open backend/scripts/install.php once.'], 200);
    }

    $ids = array_map(static fn($p) => (int)$p['id'], $posts);
    $authorIds = array_values(array_unique(array_map(static fn($p) => (int)$p['user_id'], $posts)));
    $reactions = []; $mine = []; $commentsByPost = []; $saved = []; $saveCount = []; $following = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rs = $pdo->prepare("SELECT post_id, emoji, COUNT(*) c FROM post_reactions WHERE post_id IN ($ph) GROUP BY post_id, emoji");
        $rs->execute($ids);
        foreach ($rs->fetchAll() as $row) { $reactions[(int)$row['post_id']][$row['emoji']] = (int)$row['c']; }
        $ms = $pdo->prepare("SELECT post_id, emoji FROM post_reactions WHERE user_id = ? AND post_id IN ($ph)");
        $ms->execute(array_merge([$uid], $ids));
        foreach ($ms->fetchAll() as $row) { $mine[(int)$row['post_id']] = $row['emoji']; }
        // comments (all, threaded client-side into parent/replies below)
        $cs = $pdo->prepare("SELECT c.id, c.post_id, c.parent_id, c.user_id, c.body, c.created_at, c.edited_at, u.username, u.full_name, u.first_name, u.last_name, u.role, u.avatar_path
                             FROM post_comments c JOIN users u ON u.id = c.user_id
                             WHERE c.post_id IN ($ph) ORDER BY c.id ASC");
        $cs->execute($ids);
        $flat = [];
        foreach ($cs->fetchAll() as $row) {
            $a = _fd_author($row);
            $flat[] = [
                'id' => (int)$row['id'], 'post_id' => (int)$row['post_id'], 'parent_id' => $row['parent_id'] ? (int)$row['parent_id'] : null,
                'user_id' => (int)$row['user_id'], 'body' => $row['body'], 'created_at' => $row['created_at'], 'edited' => !empty($row['edited_at']),
                'author' => $a['name'], 'avatar' => $a['avatar'], 'replies' => [],
                'can_edit' => ((int)$row['user_id'] === $uid), 'can_delete' => ($isAdmin || (int)$row['user_id'] === $uid),
            ];
        }
        // thread: attach replies to their parent
        $byId = [];
        foreach ($flat as $i => $c) { $byId[$c['id']] = &$flat[$i]; }
        foreach ($flat as $i => $c) {
            if ($c['parent_id'] && isset($byId[$c['parent_id']])) { $byId[$c['parent_id']]['replies'][] = &$flat[$i]; }
        }
        foreach ($flat as $c) { if (!$c['parent_id']) { $commentsByPost[$c['post_id']][] = $c; } }
        unset($byId);
        // saved by me + save counts
        $ss = $pdo->prepare("SELECT post_id FROM post_saves WHERE user_id = ? AND post_id IN ($ph)");
        $ss->execute(array_merge([$uid], $ids));
        foreach ($ss->fetchAll() as $row) { $saved[(int)$row['post_id']] = true; }
        $sc = $pdo->prepare("SELECT post_id, COUNT(*) c FROM post_saves WHERE post_id IN ($ph) GROUP BY post_id");
        $sc->execute($ids);
        foreach ($sc->fetchAll() as $row) { $saveCount[(int)$row['post_id']] = (int)$row['c']; }
    }
    if ($authorIds) {
        $aph = implode(',', array_fill(0, count($authorIds), '?'));
        $fs = $pdo->prepare("SELECT following_id FROM user_follows WHERE follower_id = ? AND following_id IN ($aph)");
        $fs->execute(array_merge([$uid], $authorIds));
        foreach ($fs->fetchAll() as $row) { $following[(int)$row['following_id']] = true; }
    }

    // resolve shared / reposted originals so the repost can render the source post
    $sharedMap = []; $sharedIds = [];
    foreach ($posts as $p) { if (!empty($p['shared_from'])) $sharedIds[] = (int)$p['shared_from']; }
    $sharedIds = array_values(array_unique($sharedIds));
    if ($sharedIds) {
        $sph = implode(',', array_fill(0, count($sharedIds), '?'));
        $os = $pdo->prepare('SELECT p.*, u.username, u.full_name, u.first_name, u.last_name, u.role, u.avatar_path
                             FROM feed_posts p JOIN users u ON u.id = p.user_id WHERE p.id IN (' . $sph . ')');
        $os->execute($sharedIds);
        foreach ($os->fetchAll() as $orow) {
            $oa = _fd_author($orow);
            $sharedMap[(int)$orow['id']] = [
                'id' => (int)$orow['id'], 'user_id' => (int)$orow['user_id'],
                'author' => $oa['name'], 'username' => $oa['username'], 'role' => $oa['role'], 'avatar' => $oa['avatar'],
                'body' => $orow['body'],
                'attach' => $orow['attach_path'] ? [
                    'path' => $orow['attach_path'], 'kind' => $orow['attach_kind'],
                    'name' => $orow['attach_name'], 'size' => (int)$orow['attach_size'],
                ] : null,
                'created_at' => $orow['created_at'],
            ];
        }
    }

    $out = [];
    foreach ($posts as $p) {
        $pid = (int)$p['id'];
        $a = _fd_author($p);
        $rx = $reactions[$pid] ?? [];
        $out[] = [
            'id' => $pid,
            'user_id' => (int)$p['user_id'],
            'author' => $a['name'],
            'username' => $a['username'],
            'role' => $a['role'],
            'avatar' => $a['avatar'],
            'body' => $p['body'],
            'attach' => $p['attach_path'] ? [
                'path' => $p['attach_path'], 'kind' => $p['attach_kind'],
                'name' => $p['attach_name'], 'size' => (int)$p['attach_size'],
            ] : null,
            'created_at' => $p['created_at'],
            'reactions' => (object)$rx,
            'reaction_total' => array_sum($rx),
            'my_reaction' => $mine[$pid] ?? null,
            'comments' => $commentsByPost[$pid] ?? [],
            'saved' => isset($saved[$pid]),
            'save_count' => $saveCount[$pid] ?? 0,
            'is_following_author' => isset($following[(int)$p['user_id']]),
            'edited' => !empty($p['edited_at']),
            'is_mine' => ((int)$p['user_id'] === $uid),
            'can_delete' => ($isAdmin || (int)$p['user_id'] === $uid),
            'can_edit' => ($isAdmin || (int)$p['user_id'] === $uid),
            'shared_from' => !empty($p['shared_from']) ? (int)$p['shared_from'] : null,
            'shared' => (!empty($p['shared_from']) && isset($sharedMap[(int)$p['shared_from']])) ? $sharedMap[(int)$p['shared_from']] : null,
        ];
    }
    json_response(['ok' => true, 'posts' => $out, 'emojis' => $ALLOWED_EMOJI, 'has_more' => count($posts) >= $limit]);
}

/* ===============  Reactors (who reacted): read-only  =============== */
if ($action === 'reactors') {
    $pid  = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    $rows = [];
    if ($pid > 0) {
        try {
            $st = $pdo->prepare('SELECT r.emoji, r.user_id, u.username, u.full_name, u.first_name, u.last_name, u.role, u.avatar_path
                                 FROM post_reactions r JOIN users u ON u.id = r.user_id
                                 WHERE r.post_id = ? ORDER BY r.created_at ASC');
            $st->execute([$pid]);
            foreach ($st->fetchAll() as $row) {
                $a = _fd_author($row);
                $rows[] = ['emoji' => $row['emoji'], 'user_id' => (int)$row['user_id'], 'name' => $a['name'], 'avatar' => $a['avatar'], 'role' => $a['role']];
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    json_response(['ok' => true, 'reactors' => $rows]);
}

/* ===============  Mutations: POST + CSRF  =============== */
if (!$isPost) json_response(['ok' => false, 'error' => 'POST required'], 405);
$in = [];
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) { $in = read_json_input(); }
$csrf = $in['csrf'] ?? ($_POST['csrf'] ?? null);
if (!csrf_check($csrf)) json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);

switch ($action) {
    case 'create': {
        $body = trim((string)($in['body'] ?? ($_POST['body'] ?? '')));
        if (mb_strlen($body) > 5000) json_response(['ok' => false, 'error' => 'Post is too long (max 5000).'], 400);

        $relPath = null; $kind = null; $origName = null; $size = null;
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['file'];
            if ($f['size'] > 50 * 1024 * 1024) json_response(['ok' => false, 'error' => 'Attachment too large (max 50 MB).'], 400);
            _fd_ensure($P_DIR);
            $safe   = _fd_safe($f['name']) ?: 'file';
            $stored = bin2hex(random_bytes(6)) . '_' . $safe;
            if (!move_uploaded_file($f['tmp_name'], $P_DIR . '/' . $stored)) json_response(['ok' => false, 'error' => 'Could not save attachment.'], 500);
            $relPath  = 'uploads/posts/' . $uid . '/' . $stored;
            $origName = substr($f['name'], 0, 190);
            $size     = (int)$f['size'];
            $kind     = _fd_kind($f['name']);
        }
        if ($body === '' && $relPath === null) json_response(['ok' => false, 'error' => 'Write something or attach a file.'], 400);
        try {
            $pdo->prepare('INSERT INTO feed_posts (user_id, body, attach_path, attach_kind, attach_name, attach_size, created_at) VALUES (?,?,?,?,?,?,NOW())')
                ->execute([$uid, ($body !== '' ? $body : null), $relPath, $kind, $origName, $size]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'DB not ready: ' . $e->getMessage() . ' - Open backend/scripts/install.php once.'], 200);
        }
        $newId = (int)$pdo->lastInsertId();
        // Absolute path of the attachment (used to include it in the broadcast email).
        $attachAbs = ($relPath !== null) ? ($UP_ROOT . '/' . substr($relPath, strlen('uploads/'))) : null;
        // When a privileged role posts news: reply to the browser FIRST, then email every
        // member in the background so the "Post" button never hangs or errors on a big list.
        if (in_array($role, $FD_BROADCAST_ROLES, true)) {
            _fd_respond_and_continue(['ok' => true, 'id' => $newId]);
            try { _fd_broadcast_post_email($pdo, $me, $body, $attachAbs, $kind, $origName); } catch (Throwable $e) { /* background */ }
            exit;
        }
        json_response(['ok' => true, 'id' => $newId]);
        break;
    }
    case 'delete': {
        $pid = (int)($in['post_id'] ?? 0);
        $s = $pdo->prepare('SELECT user_id, attach_path FROM feed_posts WHERE id=? LIMIT 1');
        $s->execute([$pid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        if (!$isAdmin && (int)$row['user_id'] !== $uid) json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
        if (!empty($row['attach_path']) && strpos((string)$row['attach_path'], 'uploads/') === 0) @unlink($UP_ROOT . '/' . substr((string)$row['attach_path'], strlen('uploads/')));
        // Remove any reposts that referenced this post so nothing is left behind.
        try {
            $rs = $pdo->prepare('SELECT id FROM feed_posts WHERE shared_from=?');
            $rs->execute([$pid]);
            $repostIds = array_map(static fn($r) => (int)$r['id'], $rs->fetchAll());
            foreach ($repostIds as $rid) {
                $pdo->prepare('DELETE FROM post_reactions WHERE post_id=?')->execute([$rid]);
                $pdo->prepare('DELETE FROM post_comments WHERE post_id=?')->execute([$rid]);
                $pdo->prepare('DELETE FROM post_saves WHERE post_id=?')->execute([$rid]);
            }
            if ($repostIds) $pdo->prepare('DELETE FROM feed_posts WHERE shared_from=?')->execute([$pid]);
        } catch (Throwable $e) { /* shared_from may not exist on old schemas */ }
        $pdo->prepare('DELETE FROM post_reactions WHERE post_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM post_comments WHERE post_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM post_saves WHERE post_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM feed_posts WHERE id=?')->execute([$pid]);
        json_response(['ok' => true]);
        break;
    }
    case 'edit': {
        $pid  = (int)($in['post_id'] ?? 0);
        $body = trim((string)($in['body'] ?? ''));
        if (mb_strlen($body) > 5000) json_response(['ok' => false, 'error' => 'Post is too long (max 5000).'], 400);
        $s = $pdo->prepare('SELECT user_id FROM feed_posts WHERE id=? LIMIT 1');
        $s->execute([$pid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        if (!$isAdmin && (int)$row['user_id'] !== $uid) json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
        try { $pdo->prepare('UPDATE feed_posts SET body=?, edited_at=NOW() WHERE id=?')->execute([$body, $pid]); }
        catch (Throwable $e) { $pdo->prepare('UPDATE feed_posts SET body=? WHERE id=?')->execute([$body, $pid]); }
        json_response(['ok' => true]);
        break;
    }
    case 'share': {
        $pid  = (int)($in['post_id'] ?? 0);
        $note = trim((string)($in['body'] ?? ''));
        if (mb_strlen($note) > 5000) json_response(['ok' => false, 'error' => 'Note is too long (max 5000).'], 400);
        $s = $pdo->prepare('SELECT id, user_id, shared_from FROM feed_posts WHERE id=? LIMIT 1');
        $s->execute([$pid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        // Always reference the original post (avoid chains of reposts of reposts).
        $origin = !empty($row['shared_from']) ? (int)$row['shared_from'] : (int)$row['id'];
        $pdo->prepare('INSERT INTO feed_posts (user_id, body, shared_from, created_at) VALUES (?,?,?,NOW())')->execute([$uid, $note, $origin]);
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;
    }
    case 'react': {
        $pid   = (int)($in['post_id'] ?? 0);
        $emoji = (string)($in['emoji'] ?? '');
        if (!in_array($emoji, $ALLOWED_EMOJI, true)) json_response(['ok' => false, 'error' => 'Invalid reaction.'], 400);
        $chk = $pdo->prepare('SELECT 1 FROM feed_posts WHERE id=? LIMIT 1'); $chk->execute([$pid]);
        if (!$chk->fetchColumn()) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        $ex = $pdo->prepare('SELECT emoji FROM post_reactions WHERE post_id=? AND user_id=? LIMIT 1');
        $ex->execute([$pid, $uid]);
        $cur = $ex->fetchColumn();
        if ($cur === $emoji) {
            $pdo->prepare('DELETE FROM post_reactions WHERE post_id=? AND user_id=?')->execute([$pid, $uid]);
            json_response(['ok' => true, 'my_reaction' => null]);
        } elseif ($cur !== false) {
            $pdo->prepare('UPDATE post_reactions SET emoji=?, created_at=NOW() WHERE post_id=? AND user_id=?')->execute([$emoji, $pid, $uid]);
            json_response(['ok' => true, 'my_reaction' => $emoji]);
        } else {
            $pdo->prepare('INSERT INTO post_reactions (post_id, user_id, emoji, created_at) VALUES (?,?,?,NOW())')->execute([$pid, $uid, $emoji]);
            json_response(['ok' => true, 'my_reaction' => $emoji]);
        }
        break;
    }
    case 'comment': {
        $pid    = (int)($in['post_id'] ?? 0);
        $text   = trim((string)($in['body'] ?? ''));
        $parent = (int)($in['parent_id'] ?? 0);
        if ($text === '') json_response(['ok' => false, 'error' => 'Comment is empty.'], 400);
        if (mb_strlen($text) > 2000) json_response(['ok' => false, 'error' => 'Comment too long (max 2000).'], 400);
        $chk = $pdo->prepare('SELECT 1 FROM feed_posts WHERE id=? LIMIT 1'); $chk->execute([$pid]);
        if (!$chk->fetchColumn()) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        $parentId = null;
        if ($parent > 0) {
            $pc = $pdo->prepare('SELECT post_id, parent_id FROM post_comments WHERE id=? LIMIT 1'); $pc->execute([$parent]);
            $pr = $pc->fetch();
            if ($pr && (int)$pr['post_id'] === $pid) { $parentId = $pr['parent_id'] ? (int)$pr['parent_id'] : $parent; } // one level deep
        }
        $pdo->prepare('INSERT INTO post_comments (post_id, parent_id, user_id, body, created_at) VALUES (?,?,?,?,NOW())')->execute([$pid, $parentId, $uid, $text]);
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;
    }
    case 'edit-comment': {
        $cid  = (int)($in['comment_id'] ?? 0);
        $text = trim((string)($in['body'] ?? ''));
        if ($text === '') json_response(['ok' => false, 'error' => 'Comment is empty.'], 400);
        if (mb_strlen($text) > 2000) json_response(['ok' => false, 'error' => 'Comment too long (max 2000).'], 400);
        $s = $pdo->prepare('SELECT user_id FROM post_comments WHERE id=? LIMIT 1'); $s->execute([$cid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'Comment not found.'], 404);
        if ((int)$row['user_id'] !== $uid) json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
        $pdo->prepare('UPDATE post_comments SET body=?, edited_at=NOW() WHERE id=?')->execute([$text, $cid]);
        json_response(['ok' => true]);
        break;
    }
    case 'delete-comment': {
        $cid = (int)($in['comment_id'] ?? 0);
        $s = $pdo->prepare('SELECT user_id FROM post_comments WHERE id=? LIMIT 1');
        $s->execute([$cid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'Comment not found.'], 404);
        if (!$isAdmin && (int)$row['user_id'] !== $uid) json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
        $pdo->prepare('DELETE FROM post_comments WHERE id=? OR parent_id=?')->execute([$cid, $cid]);
        json_response(['ok' => true]);
        break;
    }
    case 'save':
    case 'unsave': {
        $pid = (int)($in['post_id'] ?? 0);
        $chk = $pdo->prepare('SELECT 1 FROM feed_posts WHERE id=? LIMIT 1'); $chk->execute([$pid]);
        if (!$chk->fetchColumn()) json_response(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($action === 'save') { $pdo->prepare('INSERT IGNORE INTO post_saves (user_id, post_id, created_at) VALUES (?,?,NOW())')->execute([$uid, $pid]); }
        else { $pdo->prepare('DELETE FROM post_saves WHERE user_id=? AND post_id=?')->execute([$uid, $pid]); }
        json_response(['ok' => true, 'saved' => ($action === 'save')]);
        break;
    }
    case 'report': {
        $pid = (int)($in['post_id'] ?? 0);
        // Accepted; a moderation queue can consume this later.
        json_response(['ok' => true, 'reported' => $pid > 0]);
        break;
    }
    default:
        json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
