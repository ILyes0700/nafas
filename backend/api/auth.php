<?php
/**
 * Authentication API (session-based).
 * Actions: me, login, logout, register, verify-email, resend-otp,
 *          forgot-password, verify-reset, reset-password, csrf.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$action = $_GET['action'] ?? 'me';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* --- Public reads --- */
if ($action === 'me') {
    json_response(['user' => auth_user()]);
}
if ($action === 'csrf') {
    json_response(['ok' => true, 'csrf' => csrf_token()]);
}
if ($action === 'logout') {
    auth_logout();
    json_response(['ok' => true]);
}

/* --- Everything below requires POST + CSRF --- */
if (!$isPost) {
    json_response(['ok' => false, 'error' => 'POST required'], 405);
}
$in = read_json_input();
if (!csrf_check($in['csrf'] ?? ($_POST['csrf'] ?? null))) {
    json_response(['ok' => false, 'error' => 'Invalid or missing CSRF token'], 419);
}

switch ($action) {
    case 'login': {
        $res = auth_login($in['email'] ?? '', $in['password'] ?? '');
        if (is_array($res) && isset($res['id'])) {
            json_response(['ok' => true, 'user' => $res]);
        }
        if (is_array($res) && ($res['error'] ?? '') === 'unverified') {
            json_response(['ok' => false, 'error' => 'Please verify your email first.', 'need_verify' => true, 'user_id' => $res['user_id'] ?? null], 403);
        }
        if (is_array($res) && ($res['error'] ?? '') === 'inactive') {
            json_response(['ok' => false, 'error' => 'Your account is not active. Contact an administrator.'], 403);
        }
        json_response(['ok' => false, 'error' => 'Invalid email or password.'], 401);
        break;
    }
    case 'register': {
        $res = auth_register($in);
        if (!$res['ok']) json_response($res, 400);
        json_response(['ok' => true, 'user_id' => $res['user_id'], 'email' => $res['email'], 'next' => 'verify-email']);
        break;
    }
    case 'verify-email': {
        $uid = (int)($in['user_id'] ?? 0);
        $res = auth_verify_email($uid, (string)($in['code'] ?? ''));
        if (!$res['ok']) json_response($res, 400);
        json_response(['ok' => true, 'user' => $res['user']]);
        break;
    }
    case 'resend-otp': {
        $uid = (int)($in['user_id'] ?? 0);
        $res = auth_resend_email_otp($uid);
        json_response($res, $res['ok'] ? 200 : 400);
        break;
    }
    case 'forgot-password': {
        $res = auth_request_password_reset((string)($in['email'] ?? ''));
        json_response(['ok' => true, 'message' => 'If that email exists, a reset code has been sent.']);
        break;
    }
    case 'verify-reset': {
        $res = auth_verify_reset((string)($in['email'] ?? ''), (string)($in['code'] ?? ''));
        json_response($res, $res['ok'] ? 200 : 400);
        break;
    }
    case 'reset-password': {
        $res = auth_reset_password((string)($in['password'] ?? ''), (string)($in['confirm_password'] ?? ''));
        json_response($res, $res['ok'] ? 200 : 400);
        break;
    }
    case 'change-own-password': {
        if (!auth_user()) json_response(['ok' => false, 'error' => 'Authentication required'], 401);
        $res = auth_change_own_password((string)($in['password'] ?? ''), (string)($in['confirm_password'] ?? ''));
        json_response($res, $res['ok'] ? 200 : 400);
        break;
    }
    default:
        json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
