-- ============================================================================
-- UPGRADE v8 (2026-07-20) — Intelligence par page + Dashboard IA unifié
-- Idempotent : CREATE TABLE IF NOT EXISTS + procédure add_col_if_missing.
-- Rejouable sans casse.
-- ============================================================================

-- Part 49.1 — Détection de doublons / signalements suspects
CREATE TABLE IF NOT EXISTS report_duplicate_clusters (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cluster_key VARCHAR(100),
  zone_id VARCHAR(50),
  report_ids TEXT,
  first_report_at DATETIME,
  last_report_at DATETIME,
  merged_count INT DEFAULT 1,
  KEY idx_cluster_zone (zone_id, last_report_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Part 49.3 — Trust score citoyen (colonne ajoutée de façon sûre)
DROP PROCEDURE IF EXISTS add_col_if_missing_v8;
DELIMITER //
CREATE PROCEDURE add_col_if_missing_v8(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

CALL add_col_if_missing_v8('users', 'trust_score', '`trust_score` FLOAT DEFAULT 0.5');
DROP PROCEDURE IF EXISTS add_col_if_missing_v8;

CREATE TABLE IF NOT EXISTS trust_score_history (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  changed_at DATETIME,
  delta FLOAT,
  reason VARCHAR(150),
  KEY idx_trust_user (user_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Part 50.1 — Motifs personnels symptômes
CREATE TABLE IF NOT EXISTS personal_patterns (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  detected_at DATETIME,
  pollutant VARCHAR(20),
  lag_hours INT,
  correlation FLOAT,
  p_value FLOAT,
  narrative TEXT,
  active TINYINT(1) DEFAULT 1,
  KEY idx_pattern_user (user_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Part 51.1 — Mémoire santé persistante du chatbot
CREATE TABLE IF NOT EXISTS chatbot_user_memory (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  memory_key VARCHAR(50),
  memory_value TEXT,
  updated_at DATETIME,
  UNIQUE KEY uniq_user_key (user_id, memory_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Part 52 — Alertes automatiques aux parents
CREATE TABLE IF NOT EXISTS parent_child_alerts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parent_user_id INT,
  child_profile_id INT,
  school_zone_id VARCHAR(50),
  triggered_at DATETIME,
  risk_level VARCHAR(20),
  acknowledged TINYINT(1) DEFAULT 0,
  KEY idx_parent (parent_user_id, acknowledged)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
