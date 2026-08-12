-- =====================================================================
-- Gabes Tatenafas - Feature upgrade v3
-- Profile (avatar/bio + user files) + forced password change + notifications.
-- Idempotent. Safe to run multiple times.
-- RECOMMENDED: just open backend/scripts/install.php once instead.
-- =====================================================================

DROP PROCEDURE IF EXISTS `gt3_add_column`;
DELIMITER $$
CREATE PROCEDURE `gt3_add_column`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL gt3_add_column('users','must_change_password',"`must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
CALL gt3_add_column('users','avatar_path',"`avatar_path` VARCHAR(255) NULL AFTER `must_change_password`");
CALL gt3_add_column('users','bio',"`bio` TEXT NULL AFTER `avatar_path`");

DROP PROCEDURE IF EXISTS `gt3_add_column`;

CREATE TABLE IF NOT EXISTS `user_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `kind` VARCHAR(16) NOT NULL DEFAULT 'file',
  `original_name` VARCHAR(190) NOT NULL,
  `stored_path` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(120) NULL,
  `size` BIGINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uf_user` (`user_id`),
  CONSTRAINT `fk_uf_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
