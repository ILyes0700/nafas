-- =====================================================
-- Migration 2026-05-03 — Pack complet de fonctionnalités
-- =====================================================
-- Idempotente — utilise information_schema + prepared statements
-- (compatible MySQL 5.7+, 8.x et MariaDB 10.x).
--
-- Tables ajoutées :
--   • fragile_profiles      (A6)
--   • personal_diary        (B7)
--   • rate_limits           (B9)
--   • weekly_summaries      (C6)
--   • daily_tips            (C12)
--   • pollution_forecast    (A1)
--
-- Colonnes ajoutées :
--   • reports.ai_category, ai_severity, ai_intensity, ai_fake_score, image_hash  (C1/C2/C3)
--   • symptoms.triage_text, triage_urgency, triage_at                            (C10)
--   • alerts.priority_groups                                                      (A6)
--   • notifications.target_user_id, priority                                      (A6)
-- =====================================================

USE `gabes_tatenafas`;

-- =====================================================
-- A6 — fragile_profiles
-- =====================================================
CREATE TABLE IF NOT EXISTS fragile_profiles (
  user_id           INT NOT NULL PRIMARY KEY,
  has_asthma        TINYINT(1) NOT NULL DEFAULT 0,
  has_heart_disease TINYINT(1) NOT NULL DEFAULT 0,
  has_allergy       TINYINT(1) NOT NULL DEFAULT 0,
  is_pregnant       TINYINT(1) NOT NULL DEFAULT 0,
  is_child          TINYINT(1) NOT NULL DEFAULT 0,
  is_elderly        TINYINT(1) NOT NULL DEFAULT 0,
  notes             VARCHAR(255) NULL,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fragile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- B7 — personal_diary
-- =====================================================
CREATE TABLE IF NOT EXISTS personal_diary (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  diary_date      DATE NOT NULL,
  mood            TINYINT NOT NULL DEFAULT 3,
  cough           TINYINT NOT NULL DEFAULT 0,
  breath_diff     TINYINT NOT NULL DEFAULT 0,
  eye_irritation  TINYINT NOT NULL DEFAULT 0,
  headache        TINYINT NOT NULL DEFAULT 0,
  fatigue         TINYINT NOT NULL DEFAULT 0,
  notes           VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_diary_user_date (user_id, diary_date),
  UNIQUE KEY u_diary_user_day (user_id, diary_date),
  CONSTRAINT fk_diary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- B9 — rate_limits
-- =====================================================
CREATE TABLE IF NOT EXISTS rate_limits (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  scope_key     VARCHAR(80) NOT NULL,
  action_type   VARCHAR(40) NOT NULL,
  occurred_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl_scope_time (scope_key, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- C6 — weekly_summaries
-- =====================================================
CREATE TABLE IF NOT EXISTS weekly_summaries (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  week_start    DATE NOT NULL,
  week_end      DATE NOT NULL,
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  model         VARCHAR(80) NULL,
  summary_md    MEDIUMTEXT NOT NULL,
  metrics_json  TEXT NULL,
  UNIQUE KEY u_weekly_period (week_start, week_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- C12 — daily_tips
-- =====================================================
CREATE TABLE IF NOT EXISTS daily_tips (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  tip_date     DATE NOT NULL,
  language     VARCHAR(10) NOT NULL DEFAULT 'fr',
  status_at_gen VARCHAR(20) NULL,
  tip_text     MEDIUMTEXT NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_tip_day_lang (tip_date, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- A1 — pollution_forecast
-- =====================================================
CREATE TABLE IF NOT EXISTS pollution_forecast (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  zone_id         INT NOT NULL,
  horizon_hours   TINYINT NOT NULL,
  predicted_score TINYINT NOT NULL,
  predicted_level ENUM('safe','warning','critical') NOT NULL DEFAULT 'safe',
  computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_forecast_zone_horizon (zone_id, horizon_hours, computed_at),
  CONSTRAINT fk_forecast_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Helper interne : ajouter une colonne si elle n'existe pas
-- =====================================================
DROP PROCEDURE IF EXISTS gt_add_col_if_missing;
DELIMITER $$
CREATE PROCEDURE gt_add_col_if_missing(
  IN p_table  VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = p_table
     AND COLUMN_NAME  = p_column;
  IF v_exists = 0 THEN
    SET @ddl := CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

-- =====================================================
-- C1/C2/C3 — colonnes IA enrichies sur reports
-- =====================================================
CALL gt_add_col_if_missing('reports', 'ai_category',   '`ai_category` VARCHAR(40) NULL AFTER `ai_analysis`');
CALL gt_add_col_if_missing('reports', 'ai_severity',   '`ai_severity` TINYINT NULL AFTER `ai_category`');
CALL gt_add_col_if_missing('reports', 'ai_intensity',  '`ai_intensity` VARCHAR(20) NULL AFTER `ai_severity`');
CALL gt_add_col_if_missing('reports', 'ai_fake_score', '`ai_fake_score` TINYINT NULL AFTER `ai_intensity`');
CALL gt_add_col_if_missing('reports', 'image_hash',    '`image_hash` CHAR(64) NULL AFTER `ai_fake_score`');

-- index sur image_hash (pour déduplication des images)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'reports'
     AND INDEX_NAME   = 'idx_reports_image_hash'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_reports_image_hash ON reports(image_hash)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
-- C10 — colonnes triage IA sur symptoms
-- =====================================================
CALL gt_add_col_if_missing('symptoms', 'triage_text',    '`triage_text` MEDIUMTEXT NULL AFTER `notes`');
CALL gt_add_col_if_missing('symptoms', 'triage_urgency', '`triage_urgency` VARCHAR(20) NULL AFTER `triage_text`');
CALL gt_add_col_if_missing('symptoms', 'triage_at',      '`triage_at` DATETIME NULL AFTER `triage_urgency`');

-- =====================================================
-- A6 — priority_groups sur alerts
-- =====================================================
CALL gt_add_col_if_missing('alerts', 'priority_groups', '`priority_groups` VARCHAR(120) NULL AFTER `type`');

-- =====================================================
-- A6 — notification ciblée par utilisateur
-- =====================================================
CALL gt_add_col_if_missing('notifications', 'target_user_id', '`target_user_id` INT NULL AFTER `target_role`');
CALL gt_add_col_if_missing('notifications', 'priority',       '`priority` TINYINT NOT NULL DEFAULT 0 AFTER `level`');

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'notifications'
     AND INDEX_NAME   = 'idx_notif_user'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_notif_user ON notifications(target_user_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
-- Nettoyage du helper temporaire
-- =====================================================
DROP PROCEDURE IF EXISTS gt_add_col_if_missing;

-- =====================================================
-- Fin de la migration
-- =====================================================
SELECT 'Migration 2026-05-03 — OK (full features pack)' AS message;
