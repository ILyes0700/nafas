<?php
declare(strict_types=1);
/**
 * ONE-CLICK INSTALLER for Gabes Tatenafas - Auth & Role system.
 *
 * Open once in your browser (localhost only):
 *   http://localhost/gabes-tatenafas-v2/backend/scripts/install.php
 *
 * It safely (idempotently):
 *   1. Upgrades the users.role enum (adds super_admin).
 *   2. Adds every auth/profile column the app needs (if missing).
 *   3. Creates the auth tables (OTP, audit log, rate limits).
 *   4. Backfills existing accounts so they keep working.
 *   5. Creates / resets the single Super Admin from auth_config.php.
 * Re-running is safe.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../lib/rbac.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Forbidden: run this installer from localhost or the CLI only.');
    }
}

$log = [];
function step(array &$log, bool $ok, string $msg): void { $log[] = [$ok, $msg]; }
function col_exists(PDO $pdo, string $db, string $tbl, string $col): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $s->execute([$db, $tbl, $col]); return (bool)$s->fetchColumn();
}
function idx_exists(PDO $pdo, string $db, string $tbl, string $idx): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?');
    $s->execute([$db, $tbl, $idx]); return (bool)$s->fetchColumn();
}

$fatal = null;
try {
    $pdo = db();
    $db  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    step($log, true, "متصل بقاعدة البيانات: <b>$db</b>");

    /* 1) role enum */
    try {
        $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('citizen','health','school','admin','super_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'citizen'");
        step($log, true, 'تحديث قائمة الأدوار (إضافة super_admin)');
    } catch (Throwable $e) { step($log, false, 'role enum: ' . $e->getMessage()); }

    /* 2) columns */
    $cols = [
        'first_name'            => "`first_name` VARCHAR(80) NULL",
        'last_name'             => "`last_name` VARCHAR(80) NULL",
        'phone'                 => "`phone` VARCHAR(32) NULL",
        'age'                   => "`age` TINYINT UNSIGNED NULL",
        'status'                => "`status` ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending'",
        'email_verified_at'     => "`email_verified_at` DATETIME NULL",
        'password_changed_at'   => "`password_changed_at` DATETIME NULL",
        'created_by'            => "`created_by` INT NULL",
        'updated_at'            => "`updated_at` DATETIME NULL DEFAULT NULL",
        'last_login_at'         => "`last_login_at` DATETIME NULL",
        'failed_login_attempts' => "`failed_login_attempts` INT NOT NULL DEFAULT 0",
        'locked_until'          => "`locked_until` DATETIME NULL",
        'must_change_password'  => "`must_change_password` TINYINT(1) NOT NULL DEFAULT 0",
        'avatar_path'           => "`avatar_path` VARCHAR(255) NULL",
        'bio'                   => "`bio` TEXT NULL",
        'cover_path'            => "`cover_path` VARCHAR(255) NULL",
        'city'                  => "`city` VARCHAR(120) NULL",
        'country'               => "`country` VARCHAR(120) NULL",
        'language'              => "`language` VARCHAR(20) NULL",
    ];
    foreach ($cols as $c => $ddl) {
        try {
            if (!col_exists($pdo, $db, 'users', $c)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN $ddl");
                step($log, true, "إضافة العمود <code>$c</code>");
            } else {
                step($log, true, "العمود <code>$c</code> موجود مسبقاً");
            }
        } catch (Throwable $e) { step($log, false, "$c: " . $e->getMessage()); }
    }

    /* 3) indexes (email unique may fail on duplicates - non-fatal) */
    foreach ([
        'uniq_users_email' => 'UNIQUE INDEX `uniq_users_email` (`email`)',
        'idx_users_status' => 'INDEX `idx_users_status` (`status`)',
        'idx_users_role'   => 'INDEX `idx_users_role` (`role`)',
    ] as $idx => $ddl) {
        try {
            if (!idx_exists($pdo, $db, 'users', $idx)) {
                $pdo->exec("ALTER TABLE `users` ADD $ddl");
                step($log, true, "إضافة الفهرس <code>$idx</code>");
            }
        } catch (Throwable $e) { step($log, false, "$idx: " . $e->getMessage() . ' (تجاهله إن كان بسبب بريد مكرر)'); }
    }

    /* 4) tables */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_files` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `kind` VARCHAR(16) NOT NULL DEFAULT 'file',
            `original_name` VARCHAR(190) NOT NULL,
            `stored_path` VARCHAR(255) NOT NULL,
            `mime` VARCHAR(120) NULL,
            `size` BIGINT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_uf_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        step($log, true, "جدول <code>user_files</code> جاهز");
    } catch (Throwable $e) { step($log, false, "user_files: " . $e->getMessage()); }

    /* 4b) social wall tables: posts + reactions + comments */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `feed_posts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `body` TEXT NULL,
            `attach_path` VARCHAR(255) NULL,
            `attach_kind` VARCHAR(16) NULL,
            `attach_name` VARCHAR(190) NULL,
            `attach_size` BIGINT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_fp_user` (`user_id`),
            KEY `idx_fp_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `post_reactions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `user_id` INT NOT NULL,
            `emoji` VARCHAR(16) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_pr` (`post_id`,`user_id`),
            KEY `idx_pr_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `post_comments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `user_id` INT NOT NULL,
            `body` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pc_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        step($log, true, "جداول الحائط الاجتماعي (feed_posts, post_reactions, post_comments) جاهزة");
    } catch (Throwable $e) { step($log, false, "feed tables: " . $e->getMessage()); }

    /* 4c) social graph: follows + saved posts + comment threading */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_follows` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `follower_id` INT NOT NULL,
            `following_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_follow` (`follower_id`,`following_id`),
            KEY `idx_following` (`following_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `post_saves` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_save` (`user_id`,`post_id`),
            KEY `idx_ps_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if (!col_exists($pdo, $db, 'post_comments', 'parent_id')) $pdo->exec("ALTER TABLE `post_comments` ADD COLUMN `parent_id` BIGINT UNSIGNED NULL AFTER `user_id`");
        if (!col_exists($pdo, $db, 'post_comments', 'edited_at')) $pdo->exec("ALTER TABLE `post_comments` ADD COLUMN `edited_at` DATETIME NULL");
        step($log, true, "جداول التواصل (المتابعة، الحفظ، الردود) جاهزة");
    } catch (Throwable $e) { step($log, false, "social graph: " . $e->getMessage()); }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_otps` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `purpose` ENUM('email_verify','password_reset') NOT NULL,
            `code_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `consumed_at` DATETIME NULL,
            `attempts` INT NOT NULL DEFAULT 0,
            `max_attempts` INT NOT NULL DEFAULT 5,
            `created_ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), KEY `idx_otp_user_purpose` (`user_id`,`purpose`), KEY `idx_otp_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_audit_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NULL, `email` VARCHAR(190) NULL, `event` VARCHAR(64) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'info', `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL, `meta` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), KEY `idx_audit_user` (`user_id`), KEY `idx_audit_event` (`event`), KEY `idx_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `scope_key` VARCHAR(190) NOT NULL, `action_type` VARCHAR(64) NOT NULL,
            `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), KEY `idx_rl_scope` (`scope_key`,`action_type`,`occurred_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        step($log, true, 'إنشاء جداول المصادقة (OTP / سجل التدقيق / حدود المعدل)');
    } catch (Throwable $e) { step($log, false, 'tables: ' . $e->getMessage()); }

    /* 5) backfill existing accounts */
    try {
        $pdo->exec("UPDATE `users` SET `status`='active', `email_verified_at`=COALESCE(`email_verified_at`, NOW()) WHERE `is_active`=1 AND (`status` IS NULL OR `status`='pending')");
        $pdo->exec("UPDATE `users` SET `first_name`=COALESCE(`first_name`, NULLIF(SUBSTRING_INDEX(`full_name`,' ',1),'')), `last_name`=COALESCE(`last_name`, NULLIF(TRIM(SUBSTRING(`full_name`, LOCATE(' ', CONCAT(`full_name`,' ')))),'')) WHERE `full_name` IS NOT NULL AND `first_name` IS NULL");
        step($log, true, 'ترحيل الحسابات الحالية (تفعيلها واعتبارها موثّقة)');
    } catch (Throwable $e) { step($log, false, 'backfill: ' . $e->getMessage()); }

    /* 6) seed / reset super admin */
    try {
        $hash  = password_hash(SUPER_ADMIN_PASSWORD, PASSWORD_BCRYPT);
        $full  = SUPER_ADMIN_NAME;
        $parts = explode(' ', trim($full), 2);
        $first = $parts[0] ?? 'Super';
        $last  = $parts[1] ?? 'Administrator';
        $existing = $pdo->query("SELECT id FROM users WHERE role='super_admin' LIMIT 1")->fetch();
        if ($existing) {
            $pdo->prepare('UPDATE users SET password_hash=?, email=?, full_name=?, first_name=?, last_name=?, phone=?, status="active", is_active=1, email_verified_at=COALESCE(email_verified_at, NOW()), password_changed_at=NOW(), updated_at=NOW() WHERE id=?')
                ->execute([$hash, SUPER_ADMIN_EMAIL, $full, $first, $last, SUPER_ADMIN_PHONE, (int)$existing['id']]);
            step($log, true, 'تحديث حساب Super Admin (تمت إعادة ضبط كلمة المرور والملف)');
        } else {
            $base = 'superadmin'; $username = $base; $i = 0;
            $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
            while (true) { $chk->execute([$username]); if (!$chk->fetchColumn()) break; $username = $base . (++$i); }
            $pdo->prepare('INSERT INTO users (username, password_hash, full_name, first_name, last_name, email, phone, role, status, is_active, email_verified_at, created_at, password_changed_at) VALUES (?,?,?,?,?,?,?, "super_admin", "active", 1, NOW(), NOW(), NOW())')
                ->execute([$username, $hash, $full, $first, $last, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PHONE]);
            step($log, true, 'إنشاء حساب Super Admin');
        }
    } catch (Throwable $e) { step($log, false, 'super admin: ' . $e->getMessage()); }

} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

if ($isCli) {
    foreach ($log as [$ok, $msg]) { echo ($ok ? '[OK]  ' : '[ERR] ') . strip_tags($msg) . "\n"; }
    if ($fatal) echo "FATAL: $fatal\n";
    echo "\nEmail   : " . SUPER_ADMIN_EMAIL . "\nPassword: " . SUPER_ADMIN_PASSWORD . "\n";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$rows = '';
foreach ($log as [$ok, $msg]) {
    $ic = $ok ? '&#10003;' : '&#10007;';
    $cl = $ok ? '#16a34a' : '#dc2626';
    $rows .= '<li style="padding:8px 0;border-bottom:1px solid #f0f0f0;color:#374151;font-size:14px"><span style="color:' . $cl . ';font-weight:800;margin-left:8px">' . $ic . '</span> ' . $msg . '</li>';
}
$loginUrl = '../../frontend/login.php';
$email = htmlspecialchars(SUPER_ADMIN_EMAIL);
?><!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت النظام</title></head>
<body style="margin:0;background:#eef3fa;font-family:Segoe UI,Tahoma,Arial,sans-serif">
<div style="max-width:560px;margin:40px auto;background:#fff;border:1px solid #e6e3da;border-radius:16px;overflow:hidden">
  <div style="background:linear-gradient(135deg,#0d3b66,#1d4e89);padding:24px 28px;color:#fff">
    <div style="font-size:22px;font-weight:800">&#127811; <?= htmlspecialchars(APP_NAME) ?></div>
    <div style="color:#cfe0f5;font-size:13px;margin-top:4px">تهيئة نظام الدخول وإدارة المستخدمين</div>
  </div>
  <div style="padding:22px 28px">
    <?php if ($fatal): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:10px;font-size:14px">خطأ في الاتصال بقاعدة البيانات:<br><b><?= htmlspecialchars($fatal) ?></b><br>تأكّد من تشغيل MySQL وإنشاء قاعدة <code>gabes_tatenafas</code>.</div>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0 0 18px"><?= $rows ?></ul>
      <div style="background:#dcfce7;color:#166534;padding:14px;border-radius:10px;font-size:14px;line-height:1.9">
        <b>&#10003; اكتمل التثبيت بنجاح!</b><br>
        بريد الدخول: <b dir="ltr"><?= $email ?></b><br>
        كلمة المرور: كما في <code>auth_config.php</code>
      </div>
      <a href="<?= $loginUrl ?>" style="display:inline-block;margin-top:18px;background:#0d3b66;color:#fff;text-decoration:none;padding:12px 26px;border-radius:10px;font-weight:700">الذهاب لتسجيل الدخول &#8592;</a>
    <?php endif; ?>
  </div>
</div>
</body></html>
