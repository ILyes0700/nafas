<?php
/**
 * Profile API — available to every authenticated user (own profile only).
 * Actions: me (GET), update, avatar (multipart), upload (multipart), delete-file.
 * Files are stored under <project-root>/uploads and served as static assets.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'Authentication required'], 401);

$pdo    = db();
$uid    = (int)$me['id'];
$action = $_GET['action'] ?? 'me';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

$UP_ROOT = __DIR__ . '/../../uploads';        // <project-root>/uploads
$AV_DIR  = $UP_ROOT . '/avatars';
$F_DIR   = $UP_ROOT . '/files/' . $uid;

function _pf_ensure(string $d): void { if (!is_dir($d)) @mkdir($d, 0775, true); }
function _pf_ext(string $name): string { $e = strtolower(pathinfo($name, PATHINFO_EXTENSION)); return preg_replace('/[^a-z0-9]+/', '', $e); }
function _pf_safe(string $name): string { $n = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name); return substr($n, 0, 120); }

/* ---------- Read own profile + files ---------- */
if ($action === 'me') {
    try {
        $s = $pdo->prepare('SELECT id, username, full_name, first_name, last_name, email, phone, age, role, status, bio, avatar_path, cover_path, city, country, language, email_verified_at, last_login_at, created_at FROM users WHERE id=? LIMIT 1');
        $s->execute([$uid]);
        $u = $s->fetch();
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'DB not ready: ' . $e->getMessage() . ' — Open backend/scripts/install.php once.'], 200);
    }
    $files = [];
    try {
        $fs = $pdo->prepare('SELECT id, kind, original_name, stored_path, mime, size, created_at FROM user_files WHERE user_id=? ORDER BY id DESC');
        $fs->execute([$uid]);
        $files = $fs->fetchAll();
    } catch (Throwable $e) { /* table may not exist yet */ }
    $stats = ['posts' => 0, 'followers' => 0, 'following' => 0];
    try { $stats['posts']     = (int)$pdo->query('SELECT COUNT(*) FROM feed_posts WHERE user_id=' . $uid)->fetchColumn(); } catch (Throwable $e) {}
    try { $stats['followers'] = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE following_id=' . $uid)->fetchColumn(); } catch (Throwable $e) {}
    try { $stats['following'] = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE follower_id=' . $uid)->fetchColumn(); } catch (Throwable $e) {}
    json_response(['ok' => true, 'profile' => $u, 'files' => $files, 'stats' => $stats, 'is_me' => true, 'is_following' => false]);
}

/* ---------- Read ANOTHER user's PUBLIC profile (independent page per user) ---------- */
if ($action === 'view') {
    $target = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($target <= 0) json_response(['ok' => false, 'error' => 'Missing user_id'], 400);
    try {
        // Public fields only — private data (email, phone, age) is never exposed for other users.
        $s = $pdo->prepare('SELECT id, username, full_name, first_name, last_name, role, bio, avatar_path, cover_path, city, country, language, created_at FROM users WHERE id=? LIMIT 1');
        $s->execute([$target]);
        $u = $s->fetch();
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'DB not ready: ' . $e->getMessage() . ' \u2014 Open backend/scripts/install.php once.'], 200);
    }
    if (!$u) json_response(['ok' => false, 'error' => 'User not found.'], 404);
    $stats = ['posts' => 0, 'followers' => 0, 'following' => 0];
    try { $stats['posts']     = (int)$pdo->query('SELECT COUNT(*) FROM feed_posts WHERE user_id=' . $target)->fetchColumn(); } catch (Throwable $e) {}
    try { $stats['followers'] = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE following_id=' . $target)->fetchColumn(); } catch (Throwable $e) {}
    try { $stats['following'] = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE follower_id=' . $target)->fetchColumn(); } catch (Throwable $e) {}
    $isFollowing = false;
    try {
        $fs = $pdo->prepare('SELECT 1 FROM user_follows WHERE follower_id=? AND following_id=? LIMIT 1');
        $fs->execute([$uid, $target]);
        $isFollowing = (bool)$fs->fetchColumn();
    } catch (Throwable $e) {}
    json_response(['ok' => true, 'profile' => $u, 'files' => [], 'stats' => $stats, 'is_me' => ($target === $uid), 'is_following' => $isFollowing]);
}

/* ---------- Mutations require POST + CSRF ---------- */
if (!$isPost) json_response(['ok' => false, 'error' => 'POST required'], 405);
$in = [];
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) { $in = read_json_input(); }
$csrf = $in['csrf'] ?? ($_POST['csrf'] ?? null);
if (!csrf_check($csrf)) json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);

switch ($action) {
    case 'update': {
        $first = trim((string)($in['first_name'] ?? ''));
        $last  = trim((string)($in['last_name'] ?? ''));
        $phone = trim((string)($in['phone'] ?? ''));
        $age   = $in['age'] ?? null;
        $bio   = trim((string)($in['bio'] ?? ''));
        $city    = trim((string)($in['city'] ?? ''));
        $country = trim((string)($in['country'] ?? ''));
        $lang    = trim((string)($in['language'] ?? ''));
        if (!v_is_name($first) || !v_is_name($last)) json_response(['ok'=>false,'error'=>'Valid first and last name required.'], 400);
        if ($phone !== '' && !v_is_phone($phone)) json_response(['ok'=>false,'error'=>'Invalid phone number.'], 400);
        if ($age !== null && $age !== '' && !v_is_age($age)) json_response(['ok'=>false,'error'=>'Invalid age (1-120).'], 400);
        if (mb_strlen($bio) > 2000) json_response(['ok'=>false,'error'=>'Bio is too long (max 2000).'], 400);
        if (mb_strlen($city) > 120 || mb_strlen($country) > 120) json_response(['ok'=>false,'error'=>'City/country too long.'], 400);
        if (mb_strlen($lang) > 20) json_response(['ok'=>false,'error'=>'Invalid language.'], 400);
        $full = trim($first . ' ' . $last);
        $pdo->prepare('UPDATE users SET first_name=?, last_name=?, full_name=?, phone=?, age=?, bio=?, city=?, country=?, language=?, updated_at=NOW() WHERE id=?')
            ->execute([$first, $last, $full, ($phone ?: null), (($age === '' || $age === null) ? null : (int)$age), ($bio !== '' ? $bio : null), ($city ?: null), ($country ?: null), ($lang ?: null), $uid]);
        auth_start();
        $_SESSION['first_name'] = $first; $_SESSION['last_name'] = $last; $_SESSION['full_name'] = $full;
        json_response(['ok' => true]);
        break;
    }
    case 'avatar': {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'No image uploaded.'], 400);
        $f = $_FILES['file'];
        if ($f['size'] > 5 * 1024 * 1024) json_response(['ok'=>false,'error'=>'Image too large (max 5 MB).'], 400);
        $ext = _pf_ext($f['name']);
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) json_response(['ok'=>false,'error'=>'Allowed image types: jpg, png, gif, webp.'], 400);
        _pf_ensure($AV_DIR);
        $fname = $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $AV_DIR . '/' . $fname)) json_response(['ok'=>false,'error'=>'Could not save image.'], 500);
        $rel = 'uploads/avatars/' . $fname;
        try { $old = $pdo->query('SELECT avatar_path FROM users WHERE id=' . $uid)->fetchColumn(); if ($old && strpos($old, 'uploads/') === 0) @unlink($UP_ROOT . '/' . substr($old, strlen('uploads/'))); } catch (Throwable $e) {}
        $pdo->prepare('UPDATE users SET avatar_path=?, updated_at=NOW() WHERE id=?')->execute([$rel, $uid]);
        auth_start(); $_SESSION['avatar_path'] = $rel;
        json_response(['ok' => true, 'avatar_path' => $rel]);
        break;
    }
    case 'cover': {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'No image uploaded.'], 400);
        $f = $_FILES['file'];
        if ($f['size'] > 8 * 1024 * 1024) json_response(['ok'=>false,'error'=>'Cover too large (max 8 MB).'], 400);
        $ext = _pf_ext($f['name']);
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) json_response(['ok'=>false,'error'=>'Allowed image types: jpg, png, gif, webp.'], 400);
        $CV_DIR = $UP_ROOT . '/covers';
        _pf_ensure($CV_DIR);
        $fname = $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $CV_DIR . '/' . $fname)) json_response(['ok'=>false,'error'=>'Could not save cover.'], 500);
        $rel = 'uploads/covers/' . $fname;
        try { $old = $pdo->query('SELECT cover_path FROM users WHERE id=' . $uid)->fetchColumn(); if ($old && strpos($old, 'uploads/') === 0) @unlink($UP_ROOT . '/' . substr($old, strlen('uploads/'))); } catch (Throwable $e) {}
        $pdo->prepare('UPDATE users SET cover_path=?, updated_at=NOW() WHERE id=?')->execute([$rel, $uid]);
        json_response(['ok' => true, 'cover_path' => $rel]);
        break;
    }
    case 'upload': {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'No file uploaded.'], 400);
        $f = $_FILES['file'];
        if ($f['size'] > 25 * 1024 * 1024) json_response(['ok'=>false,'error'=>'File too large (max 25 MB).'], 400);
        _pf_ensure($F_DIR);
        $safe = _pf_safe($f['name']) ?: 'file';
        $stored = bin2hex(random_bytes(6)) . '_' . $safe;
        if (!move_uploaded_file($f['tmp_name'], $F_DIR . '/' . $stored)) json_response(['ok'=>false,'error'=>'Could not save file.'], 500);
        $rel = 'uploads/files/' . $uid . '/' . $stored;
        $ext = _pf_ext($f['name']);
        $kind = 'file';
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) $kind = 'image';
        elseif (in_array($ext, ['txt','md','doc','docx','pdf','odt','rtf'], true)) $kind = 'doc';
        try {
            $pdo->prepare('INSERT INTO user_files (user_id, kind, original_name, stored_path, mime, size, created_at) VALUES (?,?,?,?,?,?,NOW())')
                ->execute([$uid, $kind, substr($f['name'], 0, 190), $rel, substr((string)($f['type'] ?? ''), 0, 120), (int)$f['size']]);
        } catch (Throwable $e) {
            json_response(['ok'=>false,'error'=>'DB not ready: ' . $e->getMessage() . ' — Open backend/scripts/install.php once.'], 200);
        }
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'stored_path' => $rel, 'kind' => $kind]);
        break;
    }
    case 'delete-file': {
        $fid = (int)($in['file_id'] ?? 0);
        $s = $pdo->prepare('SELECT stored_path FROM user_files WHERE id=? AND user_id=? LIMIT 1');
        $s->execute([$fid, $uid]);
        $row = $s->fetch();
        if (!$row) json_response(['ok'=>false,'error'=>'File not found.'], 404);
        if (strpos((string)$row['stored_path'], 'uploads/') === 0) @unlink($UP_ROOT . '/' . substr((string)$row['stored_path'], strlen('uploads/')));
        $pdo->prepare('DELETE FROM user_files WHERE id=? AND user_id=?')->execute([$fid, $uid]);
        json_response(['ok' => true]);
        break;
    }
    default:
        json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
