-- ============================================================
-- Gabes Tatenafas / Nafass — Upgrade v6/v7 (Parts 31-48)
-- Patch-only migration. Every table uses CREATE TABLE IF NOT EXISTS and
-- every ALTER goes through add_col_if_missing() so the file is re-runnable
-- on a WAMP/MySQL install without failing on existing columns.
--
-- The whole app keeps working WITHOUT this migration (graceful degradation):
-- new PHP libs wrap every query in try/catch and the Python layer is optional.
-- ============================================================

-- ---------- Safe "ADD COLUMN IF NOT EXISTS" helper (MySQL 5.7 compatible) ----
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing(
  IN tbl VARCHAR(128), IN col VARCHAR(128), IN ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

-- ===================== PART 31 — Photo classification =====================
CREATE TABLE IF NOT EXISTS photo_classifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  report_id INT,
  zone_id INT,
  analyzed_at DATETIME,
  detected_category ENUM('clear_sky','haze','industrial_smoke','dust','unclear') DEFAULT 'unclear',
  confidence FLOAT DEFAULT 0,
  raw_vision_response TEXT,
  contributed_to_risk_score TINYINT(1) DEFAULT 0,
  INDEX(report_id), INDEX(zone_id, analyzed_at)
);

-- ============ PART 32 — Anomaly x citizen-report correlation ==============
CREATE TABLE IF NOT EXISTS anomaly_citizen_links (
  id INT PRIMARY KEY AUTO_INCREMENT,
  anomaly_id INT,
  report_id INT,
  time_distance_minutes INT,
  spatial_distance_km FLOAT,
  confidence_boost FLOAT,
  linked_at DATETIME,
  INDEX(anomaly_id), INDEX(report_id)
);

-- ============ PART 33 — Recommendation rules + feedback loop ==============
-- These tables are referenced by the prompt as "v4" but are absent here,
-- so we create them (with the Part 33 calibration columns baked in).
CREATE TABLE IF NOT EXISTS recommendation_rules (
  id INT PRIMARY KEY AUTO_INCREMENT,
  rule_key VARCHAR(80) UNIQUE,
  description VARCHAR(255),
  priority ENUM('urgent','advisory','info') DEFAULT 'advisory',
  active TINYINT(1) DEFAULT 1,
  success_rate FLOAT DEFAULT NULL,
  times_shown INT DEFAULT 0,
  times_useful INT DEFAULT 0,
  updated_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS recommendation_feedback (
  id INT PRIMARY KEY AUTO_INCREMENT,
  rule_key VARCHAR(80),
  user_id INT NULL,
  zone_id INT NULL,
  was_useful TINYINT(1) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(rule_key)
);
CREATE TABLE IF NOT EXISTS recommendations_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  zone_id INT NULL,
  rule_key VARCHAR(80) NULL,
  recommendation_text TEXT,
  source VARCHAR(60) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(zone_id, created_at)
);

-- ============ PART 34 — Notification anti-fatigue throttle ================
CREATE TABLE IF NOT EXISTS notification_throttle (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  zone_id INT,
  last_sent_at DATETIME,
  last_risk_level VARCHAR(20),
  suppressed_count INT DEFAULT 0,
  INDEX(user_id, zone_id)
);

-- ============ PART 35 — Predictive school mode ===========================
CREATE TABLE IF NOT EXISTS school_predictions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT,
  predicted_for DATETIME,
  forecast_aqi FLOAT,
  recommended_status ENUM('normal','vigilance','suspension') DEFAULT 'normal',
  based_on_horizon VARCHAR(10),
  confidence FLOAT,
  applied TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(zone_id, predicted_for)
);

-- ============ PART 37 — GNN spatial edges ================================
CREATE TABLE IF NOT EXISTS gnn_spatial_edges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_source VARCHAR(50),
  zone_target VARCHAR(50),
  wind_correlation FLOAT,
  distance_km FLOAT,
  edge_weight FLOAT,
  updated_at DATETIME,
  INDEX(zone_source), INDEX(zone_target)
);

-- ============ PART 39 — Conformal prediction columns =====================
CALL add_col_if_missing('model_predictions','conformal_lower','conformal_lower FLOAT NULL');
CALL add_col_if_missing('model_predictions','conformal_upper','conformal_upper FLOAT NULL');
CALL add_col_if_missing('model_predictions','conformal_coverage_target','conformal_coverage_target FLOAT DEFAULT 0.90');

-- ============ PART 40 — Counterfactual explanations ======================
CREATE TABLE IF NOT EXISTS counterfactual_explanations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT,
  timestamp DATETIME,
  original_class VARCHAR(20),
  counterfactual_class VARCHAR(20),
  feature_changed VARCHAR(50),
  original_value FLOAT,
  counterfactual_value FLOAT,
  narrative TEXT,
  INDEX(zone_id, timestamp)
);

-- ============ PART 41 — SHAP interaction values ==========================
CREATE TABLE IF NOT EXISTS xai_interactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT,
  computed_at DATETIME,
  feature_a VARCHAR(50),
  feature_b VARCHAR(50),
  interaction_strength FLOAT,
  rank_order INT,
  INDEX(zone_id, computed_at)
);

-- ============ PART 42 — Calibration metrics ==============================
CREATE TABLE IF NOT EXISTS calibration_metrics (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_name VARCHAR(100),
  evaluated_at DATETIME,
  brier_score FLOAT,
  reliability_bin TEXT,
  INDEX(model_name, evaluated_at)
);

-- ============ PART 43 — Model registry / versioning ======================
CREATE TABLE IF NOT EXISTS model_versions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_name VARCHAR(100),
  version VARCHAR(20),
  trained_at DATETIME,
  metrics_snapshot TEXT,
  status ENUM('staging','production','archived') DEFAULT 'staging',
  promoted_at DATETIME NULL,
  INDEX(model_name, status)
);

-- ============ PART 44 — A/B testing runs =================================
CREATE TABLE IF NOT EXISTS ab_test_runs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_a VARCHAR(100),
  model_b VARCHAR(100),
  started_at DATETIME,
  ended_at DATETIME NULL,
  traffic_split FLOAT DEFAULT 0.5,
  winner VARCHAR(100) NULL,
  decision_reason TEXT
);

-- ============ PART 45 — Data quality checks ==============================
CREATE TABLE IF NOT EXISTS data_quality_checks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  checked_at DATETIME,
  source VARCHAR(50),
  check_name VARCHAR(100),
  passed TINYINT(1),
  details TEXT,
  INDEX(source, checked_at)
);

-- ============ PART 47 — RAG sources on chatbot logs ======================
CALL add_col_if_missing('chatbot_logs','rag_sources','rag_sources TEXT NULL');

-- ============ PART 48 — Digital twin scenarios ===========================
CREATE TABLE IF NOT EXISTS digital_twin_scenarios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  scenario_name VARCHAR(150),
  created_at DATETIME,
  zone_id INT,
  parameters_json TEXT,
  simulated_aqi_curve TEXT,
  confidence FLOAT,
  INDEX(zone_id)
);

-- ---------- Seed a few recommendation rules so calibration has data -------
INSERT IGNORE INTO recommendation_rules (rule_key, description, priority) VALUES
  ('mask_high',       'Wear an FFP2 mask outdoors',                'urgent'),
  ('stay_indoors',    'Limit outdoor exposure',                    'urgent'),
  ('ventilate_night', 'Ventilate home at night when AQI is lower', 'advisory'),
  ('hydrate',         'Stay hydrated',                             'info'),
  ('sensitive_watch', 'Sensitive groups: keep reliever medication','advisory');

DROP PROCEDURE IF EXISTS add_col_if_missing;
-- ============================== END v6 ===================================
