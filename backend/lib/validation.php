<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/helpers.php'; // for mb_* polyfills
/**
 * Input validation helpers for authentication flows.
 * Each returns bool, or (for password) a problem string / null.
 */

function v_is_email(string $e): bool {
    $e = trim($e);
    return $e !== '' && strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function v_is_phone(string $p): bool {
    $p = trim($p);
    if (!preg_match('/^\+?[0-9 ()\-]{6,24}$/', $p)) return false;
    $digits = preg_replace('/\D/', '', $p);
    return strlen($digits) >= 8 && strlen($digits) <= 15;
}

/** Returns a human message describing the first password problem, or null if OK. */
function v_password_problem(string $pwd): ?string {
    if (strlen($pwd) < AUTH_PWD_MIN_LEN) return 'Password must be at least ' . AUTH_PWD_MIN_LEN . ' characters.';
    if (!preg_match('/[A-Z]/', $pwd)) return 'Password must contain an uppercase letter.';
    if (!preg_match('/[a-z]/', $pwd)) return 'Password must contain a lowercase letter.';
    if (!preg_match('/[0-9]/', $pwd)) return 'Password must contain a digit.';
    return null;
}

function v_is_name(string $n): bool {
    $n = trim($n);
    return mb_strlen($n) >= 2 && mb_strlen($n) <= 80;
}

function v_is_age($a): bool {
    if (!is_numeric($a)) return false;
    $a = (int)$a;
    return $a >= 1 && $a <= 120;
}
