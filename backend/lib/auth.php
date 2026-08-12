<?php
declare(strict_types=1);
/**
 * Gabes Tatenafas - Authentication & Session core.
 *
 * Session-based authentication (matches existing project architecture).
 * Adds: email-based registration with OTP verification, secure login,
 * password reset, CSRF protection, brute-force lockout, rate limiting,
 * RBAC and security audit logging.
 *
 * Backward compatible: auth_user(), auth_require(), auth_logout(),
 * role_allowed_routes() keep their existing signatures.
 *
 * v4.0 (migration Open-Meteo) : la route SPA 'cgan' est supprimee du jeu de
 * routes admin (module CGAN retire du projet) et remplacee par 'bilstm-ae'
 * (nouveau modele BiLSTM + Autoencoder).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/rate_limit.php';

/* ===================================================================
 * Session helpers
 * ================================================================= */
function auth_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Harden session cookies before starting.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
        }
        session_start();
    }
}

function _auth_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/* ===================================================================
 * CSRF protection
 * ================================================================= */
function csrf_token(): string
{
    auth_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    auth_start();
    return !empty($_SESSION['csrf']) && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

/* ===================================================================
 * Audit logging
 * ================================================================= */
function audit_log(string $event, ?int $userId = null, ?string $email = null, string $status = 'info', array $meta = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO auth_audit_log (user_id, email, event, status, ip, user_agent, meta)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $userId,
            $email,
            $event,
            $status,
            _auth_client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[audit_log] ' . $e->getMessage());
    }
}

/* ===================================================================
 * Current user / authorization guards
 * ================================================================= */
function auth_user(): ?array
{
    auth_start();
    if (empty($_SESSION['uid'])) return null;
    /* -----------------------------------------------------------------
     * SUPER ADMIN = ACCES TOTAL A TOUTES LES PAGES.
     * De nombreuses pages/API verifient role === 'admin'. Le Super Admin
     * est donc expose comme 'admin' a tous ces controles (=> il voit tout),
     * tandis que son vrai role reste dans 'real_role' pour conserver ses
     * super-pouvoirs (creation/suppression d'admins via rbac_can).
     * --------------------------------------------------------------- */
    $realRole = $_SESSION['role'] ?? 'citizen';
    $effRole  = ($realRole === 'super_admin') ? 'admin' : $realRole;
    return [
        'id'          => (int)$_SESSION['uid'],
        'username'    => $_SESSION['username']   ?? '',
        'full_name'   => $_SESSION['full_name']  ?? '',
        'first_name'  => $_SESSION['first_name'] ?? '',
        'last_name'   => $_SESSION['last_name']  ?? '',
        'email'       => $_SESSION['email']      ?? '',
        'role'          => $effRole,                       // role effectif (guards)
        'real_role'     => $realRole,                      // vrai role (capacites)
        'is_super_admin'=> ($realRole === 'super_admin'),
        'status'      => $_SESSION['status']     ?? 'active',
        'zone_id'     => $_SESSION['zone_id']    ?? null,
        'must_change_password' => (int)($_SESSION['must_change_password'] ?? 0),
        'avatar_path' => $_SESSION['avatar_path'] ?? null,
    ];
}

function _auth_set_session(array $row): void
{
    auth_start();
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['uid']        = (int)$row['id'];
    $_SESSION['username']   = $row['username'] ?? '';
    $_SESSION['full_name']  = $row['full_name'] ?? '';
    $_SESSION['first_name'] = $row['first_name'] ?? '';
    $_SESSION['last_name']  = $row['last_name'] ?? '';
    $_SESSION['email']      = $row['email'] ?? '';
    $_SESSION['role']       = $row['role'] ?? 'citizen';
    $_SESSION['status']     = $row['status'] ?? 'active';
    $_SESSION['zone_id']    = $row['zone_id'] ?? null;
    $_SESSION['must_change_password'] = (int)($row['must_change_password'] ?? 0);
    $_SESSION['avatar_path'] = $row['avatar_path'] ?? null;
}

/**
 * Notify the internal app inbox about an account event (create / role change /
 * delete / new registration). Never throws; failures are logged only.
 */
function _notify_account_event(string $title, array $rows): void
{
    if (!defined('APP_NOTIFY_EMAIL') || !APP_NOTIFY_EMAIL) return;
    try {
        list($subject, $html) = mailer_event_notify_email($title, $rows);
        mailer_send(APP_NOTIFY_EMAIL, $subject, $html);
    } catch (Throwable $e) {
        error_log('[notify_account_event] ' . $e->getMessage());
    }
}

/**
 * Change the currently logged-in user's own password (used by the forced
 * first-login password change). Clears the must_change_password flag.
 */
function auth_change_own_password(string $newPwd, string $confirm): array
{
    $u = auth_user();
    if (!$u) return ['ok' => false, 'error' => 'Authentication required.'];
    $problem = v_password_problem($newPwd);
    if ($problem) return ['ok' => false, 'error' => $problem];
    if ($newPwd !== $confirm) return ['ok' => false, 'error' => 'Passwords do not match.'];
    $pdo = db();
    $hash = password_hash($newPwd, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash=?, must_change_password=0, password_changed_at=NOW(), updated_at=NOW() WHERE id=?')
        ->execute([$hash, (int)$u['id']]);
    auth_start();
    $_SESSION['must_change_password'] = 0;
    audit_log('password.changed_first_login', (int)$u['id'], $u['email'] ?? null, 'info');
    return ['ok' => true];
}

/** Page guard: require a logged-in user, optionally with one of the given roles (CSV). */
function auth_require(?string $minRoleAnyOf = null): array
{
    $u = auth_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    if ($minRoleAnyOf !== null) {
        $allowed = array_map('trim', explode(',', $minRoleAnyOf));
        if (!in_array($u['role'], $allowed, true)) {
            http_response_code(403);
            echo 'Access denied for role: ' . htmlspecialchars($u['role']);
            exit;
        }
    }
    return $u;
}

/** API guard: require a capability; emits JSON 401/403 and exits on failure. */
function auth_require_capability(string $capability): array
{
    $u = auth_user();
    if (!$u) {
        if (function_exists('json_response')) json_response(['ok' => false, 'error' => 'Authentication required'], 401);
        http_response_code(401); exit;
    }
    if (!rbac_can($u['real_role'] ?? $u['role'], $capability)) {
        audit_log('authz.denied', $u['id'], $u['email'], 'warning', ['capability' => $capability]);
        if (function_exists('json_response')) json_response(['ok' => false, 'error' => 'Access denied'], 403);
        http_response_code(403); exit;
    }
    return $u;
}

/* ===================================================================
 * OTP generation / verification
 * ================================================================= */
function _auth_gen_otp(): string
{
    $max = (10 ** AUTH_OTP_LENGTH) - 1;
    return str_pad((string)random_int(0, $max), AUTH_OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Issue a new OTP for a user + purpose. Invalidates previous unconsumed codes.
 * Rate limited. Sends the code by email. Returns ['ok'=>bool,'error'=>?string].
 */
function auth_issue_otp(int $userId, string $purpose, string $email, string $purposeLabel): array
{
    $pdo = db();
    $rlAction = 'otp_send_' . $purpose;
    $scope = 'uid:' . $userId . '|' . $rlAction;
    if (!rate_limit_check($pdo, $scope, $rlAction, AUTH_RL_OTP_SEND_MAX, AUTH_RL_OTP_SEND_WINDOW)) {
        audit_log('otp.send_ratelimited', $userId, $email, 'warning', ['purpose' => $purpose]);
        return ['ok' => false, 'error' => 'Too many code requests. Please wait and try again later.'];
    }

    // Resend cooldown: block if a code was created very recently.
    $recent = $pdo->prepare(
        'SELECT created_at FROM auth_otps WHERE user_id=? AND purpose=? ORDER BY id DESC LIMIT 1'
    );
    $recent->execute([$userId, $purpose]);
    $last = $recent->fetchColumn();
    if ($last && (time() - strtotime((string)$last)) < AUTH_OTP_RESEND_COOLDOWN) {
        return ['ok' => false, 'error' => 'Please wait a moment before requesting another code.'];
    }

    // Invalidate previous unconsumed codes for this purpose.
    $pdo->prepare('UPDATE auth_otps SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL')
        ->execute([$userId, $purpose]);

    $code = _auth_gen_otp();
    if (defined('AUTH_DEV_SHOW_OTP') && AUTH_DEV_SHOW_OTP) { auth_start(); $_SESSION['dev_last_otp'] = $code; $_SESSION['dev_last_otp_purpose'] = $purpose; }
    $hash = password_hash($code, PASSWORD_BCRYPT);
    $expires = date('Y-m-d H:i:s', time() + AUTH_OTP_TTL_SECONDS);
    $pdo->prepare(
        'INSERT INTO auth_otps (user_id, purpose, code_hash, expires_at, max_attempts, created_ip)
         VALUES (?,?,?,?,?,?)'
    )->execute([$userId, $purpose, $hash, $expires, AUTH_OTP_MAX_ATTEMPTS, _auth_client_ip()]);

    list($subject, $html) = mailer_otp_email($code, $purposeLabel);
    $sent = mailer_send($email, $subject, $html);
    audit_log('otp.sent', $userId, $email, $sent ? 'info' : 'error', ['purpose' => $purpose, 'transport' => MAIL_MODE]);
    if (!$sent) return ['ok' => false, 'error' => 'Could not send the verification email. Please try again later.'];
    return ['ok' => true, 'error' => null];
}

/**
 * Verify an OTP code. On success marks it consumed (single-use).
 * Returns ['ok'=>bool,'error'=>?string].
 */
function auth_verify_otp(int $userId, string $purpose, string $code): array
{
    $pdo = db();
    $rlAction = 'otp_verify_' . $purpose;
    $scope = 'uid:' . $userId . '|' . $rlAction;
    if (!rate_limit_check($pdo, $scope, $rlAction, AUTH_RL_OTP_VERIFY_MAX, AUTH_RL_OTP_VERIFY_WINDOW)) {
        return ['ok' => false, 'error' => 'Too many attempts. Please wait and try again later.'];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM auth_otps WHERE user_id=? AND purpose=? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $purpose]);
    $otp = $stmt->fetch();
    if (!$otp) return ['ok' => false, 'error' => 'No active code. Please request a new one.'];

    if (strtotime((string)$otp['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'This code has expired. Please request a new one.'];
    }
    if ((int)$otp['attempts'] >= (int)$otp['max_attempts']) {
        $pdo->prepare('UPDATE auth_otps SET consumed_at=NOW() WHERE id=?')->execute([$otp['id']]);
        return ['ok' => false, 'error' => 'Too many wrong attempts. Please request a new code.'];
    }

    if (!password_verify(trim($code), $otp['code_hash'])) {
        $pdo->prepare('UPDATE auth_otps SET attempts = attempts + 1 WHERE id=?')->execute([$otp['id']]);
        audit_log('otp.wrong', $userId, null, 'warning', ['purpose' => $purpose]);
        $left = (int)$otp['max_attempts'] - ((int)$otp['attempts'] + 1);
        return ['ok' => false, 'error' => 'Incorrect code.' . ($left > 0 ? " $left attempt(s) left." : '')];
    }

    // Success -> consume (single-use).
    $pdo->prepare('UPDATE auth_otps SET consumed_at=NOW() WHERE id=?')->execute([$otp['id']]);
    return ['ok' => true, 'error' => null];
}

/* ===================================================================
 * Registration (creates an inactive, pending account + email OTP)
 * ================================================================= */
function auth_register(array $in): array
{
    $pdo = db();

    // Rate limit by IP.
    $scope = 'ip:' . _auth_client_ip() . '|register';
    if (!rate_limit_check($pdo, $scope, 'register', AUTH_RL_REGISTER_MAX, AUTH_RL_REGISTER_WINDOW)) {
        return ['ok' => false, 'error' => 'Too many registration attempts. Please try again later.'];
    }

    $first = trim((string)($in['first_name'] ?? ''));
    $last  = trim((string)($in['last_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $age   = $in['age'] ?? '';
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $pwd   = (string)($in['password'] ?? '');
    $pwd2  = (string)($in['confirm_password'] ?? '');

    // Field validation.
    if (!v_is_name($first)) return ['ok' => false, 'error' => 'Please enter a valid first name.'];
    if (!v_is_name($last))  return ['ok' => false, 'error' => 'Please enter a valid last name.'];
    if (!v_is_phone($phone)) return ['ok' => false, 'error' => 'Please enter a valid phone number.'];
    if (!v_is_age($age))    return ['ok' => false, 'error' => 'Please enter a valid age (1-120).'];
    if (!v_is_email($email)) return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    $pwdProblem = v_password_problem($pwd);
    if ($pwdProblem) return ['ok' => false, 'error' => $pwdProblem];
    if ($pwd !== $pwd2) return ['ok' => false, 'error' => 'Passwords do not match.'];

    // Uniqueness / duplicate prevention.
    $exists = $pdo->prepare('SELECT id, status FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    $existing = $exists->fetch();
    if ($existing) {
        // If a pending (never-verified) account exists, reuse it and resend a code.
        if (($existing['status'] ?? '') === 'pending') {
            $uid = (int)$existing['id'];
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $pdo->prepare(
                'UPDATE users SET password_hash=?, first_name=?, last_name=?, phone=?, age=?, full_name=?, updated_at=NOW() WHERE id=?'
            )->execute([$hash, $first, $last, $phone, (int)$age, trim($first . ' ' . $last), $uid]);
            $issue = auth_issue_otp($uid, 'email_verify', $email, 'email verification');
            if (!$issue['ok']) return $issue;
            audit_log('register.pending_resend', $uid, $email, 'info');
            return ['ok' => true, 'user_id' => $uid, 'email' => $email];
        }
        return ['ok' => false, 'error' => 'An account with this email already exists.'];
    }

    // Derive a unique username (kept for backward-compatibility with legacy joins).
    $base = preg_replace('/[^a-z0-9]+/', '', strtolower(explode('@', $email)[0])) ?: 'user';
    $username = $base;
    $i = 0;
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    while (true) {
        $chk->execute([$username]);
        if (!$chk->fetchColumn()) break;
        $username = $base . (++$i);
    }

    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    $full = trim($first . ' ' . $last);
    $ins = $pdo->prepare(
        'INSERT INTO users (username, password_hash, full_name, first_name, last_name, email, phone, age, role, status, is_active, created_at, password_changed_at)
         VALUES (?,?,?,?,?,?,?,?, "citizen", "pending", 0, NOW(), NOW())'
    );
    $ins->execute([$username, $hash, $full, $first, $last, $email, $phone, (int)$age]);
    $uid = (int)$pdo->lastInsertId();

    $issue = auth_issue_otp($uid, 'email_verify', $email, 'email verification');
    if (!$issue['ok']) {
        audit_log('register.otp_failed', $uid, $email, 'error');
        return $issue;
    }
    audit_log('register.created', $uid, $email, 'info');
    _notify_account_event('New account registered', [
        'Name'  => $full,
        'Email' => $email,
        'Role'  => 'citizen',
        'Via'   => 'Self-registration',
    ]);
    return ['ok' => true, 'user_id' => $uid, 'email' => $email];
}

/** Verify the email OTP, activate the account, and auto-login. */
function auth_verify_email(int $userId, string $code): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'Account not found.'];
    if (($row['status'] ?? '') === 'active' && !empty($row['email_verified_at'])) {
        return ['ok' => false, 'error' => 'This account is already verified. Please log in.'];
    }
    $res = auth_verify_otp($userId, 'email_verify', $code);
    if (!$res['ok']) return $res;

    $pdo->prepare('UPDATE users SET status="active", is_active=1, email_verified_at=NOW(), updated_at=NOW() WHERE id=?')
        ->execute([$userId]);
    $row['status'] = 'active';
    _auth_set_session($row);
    audit_log('email.verified', $userId, $row['email'] ?? null, 'info');
    return ['ok' => true, 'user' => auth_user()];
}

function auth_resend_email_otp(int $userId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'Account not found.'];
    if (($row['status'] ?? '') !== 'pending') return ['ok' => false, 'error' => 'This account is already verified.'];
    return auth_issue_otp((int)$row['id'], 'email_verify', $row['email'], 'email verification');
}

/* ===================================================================
 * Login (email + password only; verified + active accounts only)
 * ================================================================= */
function auth_login(string $email, string $password): ?array
{
    $pdo = db();
    $email = strtolower(trim($email));

    // Rate limit login attempts per email + IP.
    $scope = 'ip:' . _auth_client_ip() . '|login';
    if (!rate_limit_check($pdo, $scope, 'login', AUTH_RL_LOGIN_MAX, AUTH_RL_LOGIN_WINDOW)) {
        audit_log('login.ratelimited', null, $email, 'warning');
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) { audit_log('login.unknown_email', null, $email, 'warning'); return null; }

    // Account lock (brute-force protection).
    if (!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time()) {
        audit_log('login.locked', (int)$row['id'], $email, 'warning');
        return null;
    }

    if (!password_verify($password, $row['password_hash'])) {
        $fails = (int)($row['failed_login_attempts'] ?? 0) + 1;
        if ($fails >= AUTH_MAX_FAILED_LOGINS) {
            $lock = date('Y-m-d H:i:s', time() + AUTH_LOCKOUT_SECONDS);
            $pdo->prepare('UPDATE users SET failed_login_attempts=?, locked_until=? WHERE id=?')
                ->execute([$fails, $lock, $row['id']]);
            audit_log('login.locked_now', (int)$row['id'], $email, 'warning', ['fails' => $fails]);
        } else {
            $pdo->prepare('UPDATE users SET failed_login_attempts=? WHERE id=?')->execute([$fails, $row['id']]);
            audit_log('login.bad_password', (int)$row['id'], $email, 'warning', ['fails' => $fails]);
        }
        return null;
    }

    // Must be verified + active.
    if (empty($row['email_verified_at'])) { audit_log('login.unverified', (int)$row['id'], $email, 'warning'); return ['error' => 'unverified', 'user_id' => (int)$row['id']]; }
    if (($row['status'] ?? '') !== 'active') { audit_log('login.inactive', (int)$row['id'], $email, 'warning'); return ['error' => 'inactive']; }

    // Success: reset counters + refresh session.
    $pdo->prepare('UPDATE users SET failed_login_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')
        ->execute([$row['id']]);
    _auth_set_session($row);
    audit_log('login.success', (int)$row['id'], $email, 'info');
    return auth_user();
}

/* ===================================================================
 * Password reset (forgot password) via OTP
 * ================================================================= */
function auth_request_password_reset(string $email): array
{
    $pdo = db();
    $email = strtolower(trim($email));
    $scope = 'ip:' . _auth_client_ip() . '|pwreset';
    if (!rate_limit_check($pdo, $scope, 'pwreset', AUTH_RL_RESET_MAX, AUTH_RL_RESET_WINDOW)) {
        return ['ok' => true]; // do not reveal; silently accept
    }
    $stmt = $pdo->prepare('SELECT id, email, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    // Always return ok to avoid user enumeration.
    if ($row && ($row['status'] ?? '') !== 'suspended') {
        auth_issue_otp((int)$row['id'], 'password_reset', $row['email'], 'password reset');
        audit_log('pwreset.requested', (int)$row['id'], $email, 'info');
    } else {
        audit_log('pwreset.unknown', null, $email, 'warning');
    }
    return ['ok' => true];
}

/** Verify the reset OTP. On success, set a short-lived session grant. */
function auth_verify_reset(string $email, string $code): array
{
    $pdo = db();
    $email = strtolower(trim($email));
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'Invalid code.'];
    $res = auth_verify_otp((int)$row['id'], 'password_reset', $code);
    if (!$res['ok']) return $res;
    auth_start();
    $_SESSION['pwd_reset_grant'] = ['uid' => (int)$row['id'], 'exp' => time() + 600];
    return ['ok' => true];
}

/** Set a new password after a verified reset grant. */
function auth_reset_password(string $newPwd, string $confirm): array
{
    auth_start();
    $grant = $_SESSION['pwd_reset_grant'] ?? null;
    if (!$grant || ($grant['exp'] ?? 0) < time()) {
        return ['ok' => false, 'error' => 'Your reset session expired. Please start again.'];
    }
    $problem = v_password_problem($newPwd);
    if ($problem) return ['ok' => false, 'error' => $problem];
    if ($newPwd !== $confirm) return ['ok' => false, 'error' => 'Passwords do not match.'];

    $pdo = db();
    $hash = password_hash($newPwd, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash=?, password_changed_at=NOW(), failed_login_attempts=0, locked_until=NULL, updated_at=NOW() WHERE id=?')
        ->execute([$hash, (int)$grant['uid']]);
    // Invalidate any remaining reset codes.
    $pdo->prepare('UPDATE auth_otps SET consumed_at=NOW() WHERE user_id=? AND purpose="password_reset" AND consumed_at IS NULL')
        ->execute([(int)$grant['uid']]);
    unset($_SESSION['pwd_reset_grant']);
    audit_log('pwreset.completed', (int)$grant['uid'], null, 'info');
    return ['ok' => true];
}

/* ===================================================================
 * Logout
 * ================================================================= */
function auth_logout(): void
{
    auth_start();
    $uid = $_SESSION['uid'] ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    if ($uid) audit_log('logout', (int)$uid, null, 'info');
}

/* ===================================================================
 * Legacy shim - kept so old references do not fatal. Registration now
 * goes through auth_register() + email verification.
 * ================================================================= */
function auth_register_citizen(string $username, string $password, string $fullName = '', string $email = ''): array
{
    return ['ok' => false, 'error' => 'Registration now requires email verification. Please use the registration page.'];
}

/* ===================================================================
 * SPA routing permissions per role (used by index.php sidebar/router).
 * super_admin inherits the full admin route set + admin management.
 *
 * v4.0 : 'cgan' SUPPRIME (module Conditional GAN retire du projet).
 *        'bilstm-ae' AJOUTE (nouveau modele BiLSTM + Autoencoder).
 * ================================================================= */
function role_allowed_routes(string $role): array
{
    $adminRoutes = [
        'dashboard','community','admin','users','map','api-data','alerts','reports','citizen-reports',
        'symptoms','diary','correlation','weekly','forecast','deep-learning','bilstm-ae','anomaly','comparison','fuzzy-type2','forecast-ml','granger','health-impact','drift','spatial','ensemble','smart-alerts','federated','comparative-literature','upgrade-dashboard','model-registry','digital-twin','ai-dashboard','chatbot','school',
        'zones','learn','settings','help','profile',
    ];
    switch ($role) {
        case 'citizen':
            return [
                'dashboard','community','map','api-data','alerts','citizen-reports','symptoms',
                'diary','chatbot','learn','settings','help','profile',
            ];
        case 'health':
            return [
                'dashboard','community','map','api-data','alerts','reports','symptoms','correlation',
                'weekly','chatbot','ai-dashboard','zones','learn','settings','help','profile',
            ];
        case 'school':
            return [
                'dashboard','community','map','api-data','alerts','school','chatbot','learn','settings','help','profile',
            ];
        case 'admin':
            return $adminRoutes;
        case 'super_admin':
            return $adminRoutes; // full access
        default:
            return ['dashboard','community','help','profile'];
    }
}