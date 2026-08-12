<?php
/**
 * User & Role management API (RBAC protected).
 *
 * Super Admin only : admin.create, admin.delete, admin.suspend, admin.restore
 * Admin or Super   : users.view, users.suspend, users.restore, users.set_role (doctor/school/citizen)
 *
 * All state-changing actions require POST + CSRF. Every action is audited.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$action = $_GET['action'] ?? 'list';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$pdo    = db();

/* ---------- Read: list users ---------- */
if ($action === 'list') {
    $me = auth_require_capability('users.view');
    $role   = trim((string)($_GET['role'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $q      = trim((string)($_GET['q'] ?? ''));
    $sql = 'SELECT id, username, full_name, first_name, last_name, email, phone, age, role, status,
                   email_verified_at, last_login_at, created_at, created_by
            FROM users WHERE 1=1';
    $args = [];
    if ($role !== '' && in_array($role, rbac_all_roles(), true)) { $sql .= ' AND role = ?'; $args[] = $role; }
    if (in_array($status, ['pending','active','suspended'], true)) { $sql .= ' AND status = ?'; $args[] = $status; }
    if ($q !== '') { $sql .= ' AND (email LIKE ? OR full_name LIKE ? OR phone LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like, $like); }
    $sql .= ' ORDER BY created_at DESC LIMIT 500';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'DB not ready: ' . $e->getMessage() . ' — Open backend/scripts/install.php once to finish setup.'], 200);
    }
}

/* ---------- All mutations require POST + CSRF ---------- */
if (!$isPost) json_response(['ok' => false, 'error' => 'POST required'], 405);
$in = read_json_input();
if (!csrf_check($in['csrf'] ?? null)) json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);

/** Generate a strong temporary password that satisfies the password policy. */
function _gen_temp_password(int $len = 12): string {
    $U = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; $L = 'abcdefghijkmnpqrstuvwxyz'; $D = '23456789'; $S = '!@#$%*?';
    $all = $U . $L . $D . $S;
    $p  = $U[random_int(0, strlen($U) - 1)] . $L[random_int(0, strlen($L) - 1)] . $D[random_int(0, strlen($D) - 1)] . $S[random_int(0, strlen($S) - 1)];
    for ($i = strlen($p); $i < $len; $i++) $p .= $all[random_int(0, strlen($all) - 1)];
    return str_shuffle($p);
}

/** Fetch a target user row or emit 404. */
function _target_user(PDO $pdo, int $id): array {
    $s = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) json_response(['ok' => false, 'error' => 'User not found'], 404);
    return $row;
}

switch ($action) {
    /* ===== Super Admin: create an Admin account ===== */
    case 'create-admin': {
        $me = auth_require_capability('admin.create');
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $first = trim((string)($in['first_name'] ?? ''));
        $last  = trim((string)($in['last_name'] ?? ''));
        $phone = trim((string)($in['phone'] ?? ''));
        // NOTE: the Super Admin only provides name + email. A strong password is
        // generated automatically and emailed to the new admin.
        if (!v_is_name($first) || !v_is_name($last)) json_response(['ok'=>false,'error'=>'Valid first and last name required.'], 400);
        if (!v_is_email($email)) json_response(['ok'=>false,'error'=>'Valid email required.'], 400);
        if ($phone !== '' && !v_is_phone($phone)) json_response(['ok'=>false,'error'=>'Invalid phone number.'], 400);
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) json_response(['ok'=>false,'error'=>'Email already in use.'], 409);

        $tempPwd = _gen_temp_password();

        $base = preg_replace('/[^a-z0-9]+/', '', strtolower(explode('@', $email)[0])) ?: 'admin';
        $username = $base; $i = 0; $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        while (true) { $chk->execute([$username]); if (!$chk->fetchColumn()) break; $username = $base . (++$i); }

        $hash = password_hash($tempPwd, PASSWORD_BCRYPT);
        $full = trim($first . ' ' . $last);
        $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, first_name, last_name, email, phone, role, status, is_active, email_verified_at, must_change_password, created_by, created_at, password_changed_at)
             VALUES (?,?,?,?,?,?,?, "admin", "active", 1, NOW(), 1, ?, NOW(), NOW())'
        )->execute([$username, $hash, $full, $first, $last, $email, ($phone ?: null), $me['id']]);
        $newId = (int)$pdo->lastInsertId();

        // Email the new admin their login email + temporary password.
        $mailed = false;
        try {
            list($subject, $html) = mailer_admin_welcome_email($email, $tempPwd, $full);
            $mailed = mailer_send($email, $subject, $html);
        } catch (Throwable $e) { error_log('[create-admin] mail: ' . $e->getMessage()); }

        audit_log('admin.created', $me['id'], $me['email'], 'info', ['new_admin_id' => $newId, 'email' => $email, 'mailed' => $mailed]);
        _notify_account_event('New Admin account created', [
            'Name'       => $full,
            'Email'      => $email,
            'Role'       => 'Admin',
            'Created by' => $me['email'],
        ]);
        json_response(['ok' => true, 'id' => $newId, 'mailed' => $mailed]);
        break;
    }

    /* ===== Super Admin: suspend / restore / delete an Admin ===== */
    case 'suspend-admin':
    case 'restore-admin':
    case 'delete-admin': {
        $cap = ['suspend-admin'=>'admin.suspend','restore-admin'=>'admin.restore','delete-admin'=>'admin.delete'][$action];
        $me  = auth_require_capability($cap);
        $target = _target_user($pdo, (int)($in['user_id'] ?? 0));
        if (($target['role'] ?? '') === ROLE_SUPER_ADMIN) json_response(['ok'=>false,'error'=>'The Super Admin account cannot be modified.'], 403);
        if (($target['role'] ?? '') !== ROLE_ADMIN) json_response(['ok'=>false,'error'=>'Target is not an Admin.'], 400);
        if ($action === 'delete-admin') {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$target['id']]);
            audit_log('admin.deleted', $me['id'], $me['email'], 'warning', ['deleted_id' => (int)$target['id']]);
            _notify_account_event('Admin account deleted', [
                'Deleted ID' => (int)$target['id'],
                'Email'      => $target['email'] ?? '',
                'Name'       => $target['full_name'] ?? '',
                'Deleted by' => $me['email'],
            ]);
        } else {
            $newStatus = ($action === 'suspend-admin') ? 'suspended' : 'active';
            $pdo->prepare('UPDATE users SET status=?, is_active=?, updated_at=NOW() WHERE id=?')
                ->execute([$newStatus, ($newStatus === 'active' ? 1 : 0), $target['id']]);
            audit_log('admin.' . ($action === 'suspend-admin' ? 'suspended' : 'restored'), $me['id'], $me['email'], 'info', ['target_id' => (int)$target['id']]);
        }
        json_response(['ok' => true]);
        break;
    }

    /* ===== Admin/Super: suspend / restore a normal (non-admin) user ===== */
    case 'suspend-user':
    case 'restore-user': {
        $cap = ($action === 'suspend-user') ? 'users.suspend' : 'users.restore';
        $me  = auth_require_capability($cap);
        $target = _target_user($pdo, (int)($in['user_id'] ?? 0));
        if (rbac_is_admin($target['role'] ?? '')) json_response(['ok'=>false,'error'=>'Cannot modify an admin here.'], 403);
        $newStatus = ($action === 'suspend-user') ? 'suspended' : 'active';
        $pdo->prepare('UPDATE users SET status=?, is_active=?, updated_at=NOW() WHERE id=?')
            ->execute([$newStatus, ($newStatus === 'active' ? 1 : 0), $target['id']]);
        audit_log('user.' . ($action === 'suspend-user' ? 'suspended' : 'restored'), $me['id'], $me['email'], 'info', ['target_id' => (int)$target['id']]);
        json_response(['ok' => true]);
        break;
    }

    /* ===== Admin/Super: change a user's role (grant Doctor / School / revert to Normal) ===== */
    case 'set-role': {
        $me = auth_require_capability('users.set_role');
        $target = _target_user($pdo, (int)($in['user_id'] ?? 0));
        $newRole = (string)($in['role'] ?? '');
        if (!in_array($newRole, rbac_admin_assignable_roles(), true)) {
            json_response(['ok'=>false,'error'=>'You may only assign: Doctor, School, or Normal User.'], 400);
        }
        if (rbac_is_admin($target['role'] ?? '')) json_response(['ok'=>false,'error'=>'Cannot change an admin\'s role here.'], 403);
        if (empty($target['email_verified_at'])) json_response(['ok'=>false,'error'=>'User must verify their email before role changes.'], 400);
        $pdo->prepare('UPDATE users SET role=?, updated_at=NOW() WHERE id=?')->execute([$newRole, $target['id']]);
        audit_log('user.role_changed', $me['id'], $me['email'], 'info', ['target_id' => (int)$target['id'], 'from' => $target['role'], 'to' => $newRole]);
        _notify_account_event('User role changed', [
            'Target ID' => (int)$target['id'],
            'Email'     => $target['email'] ?? '',
            'From'      => $target['role'] ?? '',
            'To'        => $newRole,
            'Changed by'=> $me['email'],
        ]);
        json_response(['ok' => true, 'role' => $newRole]);
        break;
    }

    default:
        json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
