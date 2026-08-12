-- =====================================================================
-- Gabes Tatenafas - Authentication & Role Management Upgrade
-- Idempotent migration. Safe to run multiple times.
-- Tested shape: MySQL 5.7 / 8 / 9 and MariaDB 10.4+.
-- SCOPE: authentication, authorization, RBAC, email verification,
--        password reset. Does NOT touch unrelated business tables.
-- =====================================================================
-- If needed, uncomment and adjust:
-- USE `gabes_tatenafas`;

-- ---------------------------------------------------------------------
-- 1) Extend the role enum with the new super_admin tier.
--    (Doctor is represented by the existing 'health' role.)
-- ---------------------------------------------------------------------
ALTER TABLE `users`
  MODIFY `role` ENUM('citizen','health','school','admin','super_admin')
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'citizen';

-- ---------------------------------------------------------------------
-- 2) Helper procedures for idempotent column / index creation.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `gt_add_column`;
DROP PROCEDURE IF EXISTS `gt_add_index`;
DELIMITER $$
CREATE PROCEDURE `gt_add_column`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
CREATE PROCEDURE `gt_add_index`(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 3) Add authentication / profile / audit columns to `users`.
-- ---------------------------------------------------------------------
CALL gt_add_column('users','first_name',"`first_name` VARCHAR(80) NULL AFTER `full_name`");
CALL gt_add_column('users','last_name',"`last_name` VARCHAR(80) NULL AFTER `first_name`");
CALL gt_add_column('users','phone',"`phone` VARCHAR(32) NULL AFTER `email`");
CALL gt_add_column('users','age',"`age` TINYINT UNSIGNED NULL AFTER `phone`");
CALL gt_add_column('users','status',"`status` ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending' AFTER `role`");
CALL gt_add_column('users','email_verified_at',"`email_verified_at` DATETIME NULL AFTER `status`");
CALL gt_add_column('users','password_changed_at',"`password_changed_at` DATETIME NULL AFTER `email_verified_at`");
CALL gt_add_column('users','created_by',"`created_by` INT NULL AFTER `password_changed_at`");
CALL gt_add_column('users','updated_at',"`updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`");
CALL gt_add_column('users','last_login_at',"`last_login_at` DATETIME NULL AFTER `updated_at`");
CALL gt_add_column('users','failed_login_attempts',"`failed_login_attempts` INT NOT NULL DEFAULT 0 AFTER `last_login_at`");
CALL gt_add_column('users','locked_until',"`locked_until` DATETIME NULL AFTER `failed_login_attempts`");

-- ---------------------------------------------------------------------
-- 4) Indexes / constraints.
--    NOTE: uniq_users_email requires existing emails to be unique & non-duplicated.
--    Clean duplicates first if this fails.
-- ---------------------------------------------------------------------
CALL gt_add_index('users','uniq_users_email','UNIQUE INDEX `uniq_users_email` (`email`)');
CALL gt_add_index('users','idx_users_status','INDEX `idx_users_status` (`status`)');
CALL gt_add_index('users','idx_users_role','INDEX `idx_users_role` (`role`)');

-- ---------------------------------------------------------------------
-- 5) One-Time-Password table (email verification + password reset).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_otps` (
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
  PRIMARY KEY (`id`),
  KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 6) Authentication audit log (security event logging).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `email` VARCHAR(190) NULL,
  `event` VARCHAR(64) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'info',
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `meta` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_event` (`event`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 7) Rate-limit table (reused by backend/lib/rate_limit.php). Create if absent.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_key` VARCHAR(190) NOT NULL,
  `action_type` VARCHAR(64) NOT NULL,
  `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rl_scope` (`scope_key`,`action_type`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 8) Migrate existing accounts so they keep working after the upgrade.
--    Previously-active accounts become 'active' and are treated as verified.
-- ---------------------------------------------------------------------
UPDATE `users`
   SET `status` = 'active',
       `email_verified_at` = COALESCE(`email_verified_at`, NOW())
 WHERE `is_active` = 1 AND (`status` IS NULL OR `status` = 'pending');

-- Backfill first/last name from full_name where possible.
UPDATE `users`
   SET `first_name` = COALESCE(`first_name`, NULLIF(SUBSTRING_INDEX(`full_name`,' ',1),'')),
       `last_name`  = COALESCE(`last_name`, NULLIF(TRIM(SUBSTRING(`full_name`, LOCATE(' ', CONCAT(`full_name`,' ')))),''))
 WHERE `full_name` IS NOT NULL AND `first_name` IS NULL;

-- ---------------------------------------------------------------------
-- 9) Clean up helper procedures.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `gt_add_column`;
DROP PROCEDURE IF EXISTS `gt_add_index`;

-- NOTE: The single Super Admin account is created by running:
--   php backend/scripts/seed_super_admin.php
-- (uses PHP password_hash so no plaintext/known hash ships in this file).
