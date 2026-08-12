<?php
/**
 * Seed (or reset) the single Super Admin account.
 * Usage:
 *   CLI    : php backend/scripts/seed_super_admin.php
 *   Browser: http://localhost/gabes-tatenafas/backend/scripts/seed_super_admin.php
 *            (browser access is allowed only from localhost for safety)
 *
 * Credentials are taken from backend/config/auth_config.php
 * (SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD). Change them, then re-run to reset.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../lib/rbac.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Forbidden: run this seeder from localhost or the CLI only.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo  = db();
    $hash = password_hash(SUPER_ADMIN_PASSWORD, PASSWORD_BCRYPT);
    $full = SUPER_ADMIN_NAME;
    $parts = explode(' ', trim($full), 2);
    $first = $parts[0] ?? 'Super';
    $last  = $parts[1] ?? 'Administrator';

    // Is there already a super admin?
    $existing = $pdo->query("SELECT id, email FROM users WHERE role = 'super_admin' LIMIT 1")->fetch();

    if ($existing) {
        $pdo->prepare('UPDATE users SET password_hash=?, email=?, full_name=?, first_name=?, last_name=?, phone=?, status="active", is_active=1, email_verified_at=COALESCE(email_verified_at, NOW()), password_changed_at=NOW(), updated_at=NOW() WHERE id=?')
            ->execute([$hash, SUPER_ADMIN_EMAIL, $full, $first, $last, SUPER_ADMIN_PHONE, (int)$existing['id']]);
        echo "Super Admin already existed (id={$existing['id']}). Password and profile reset.\n";
    } else {
        // Ensure username uniqueness.
        $base = 'superadmin'; $username = $base; $i = 0;
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        while (true) { $chk->execute([$username]); if (!$chk->fetchColumn()) break; $username = $base . (++$i); }
        $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, first_name, last_name, email, phone, role, status, is_active, email_verified_at, created_at, password_changed_at)
             VALUES (?,?,?,?,?,?,?, "super_admin", "active", 1, NOW(), NOW(), NOW())'
        )->execute([$username, $hash, $full, $first, $last, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PHONE]);
        echo "Super Admin created.\n";
    }
    echo "Email   : " . SUPER_ADMIN_EMAIL . "\n";
    echo "Password: " . SUPER_ADMIN_PASSWORD . "  (change it after first login)\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Seeder error: ' . $e->getMessage() . "\n";
}
