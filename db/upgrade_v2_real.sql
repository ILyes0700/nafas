-- ============================================================
-- GABES-TATENAFAS v2.0 — Upgrade the REAL database in place.
-- Run AFTER importing gabes_tatenafas_real.sql.
-- Safe / idempotent: ALTER ... ADD COLUMN IF NOT EXISTS (MariaDB / WAMP)
-- and CREATE TABLE IF NOT EXISTS.
-- ============================================================
USE `gabes_tatenafas`;

-- --- Extend existing tables to the v2 scientific schema ---
ALTER TABLE `model_performance`
  ADD COLUMN IF NOT EXISTS `horizon` VARCHAR(10) DEFAULT '1h',
  ADD COLUMN IF NOT EXISTS `cv_mean_f1` FLOAT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cv_std_f1` FLOAT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cv_mean_rmse` FLOAT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cv_std_rmse` FLOAT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `roc_data` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `wilcoxon_pvalue` FLOAT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `optuna_best_params` TEXT DEFAULT NULL;

ALTER TABLE `fuzzy_assessments`
  ADD COLUMN IF NOT EXISTS `uncertainty_band` FLOAT DEFAULT NULL;

-- --- New tables for the scientific modules ---
CREATE TABLE IF NOT EXISTS `model_predictions` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), timestamp DATETIME,
  horizon VARCHAR(10), model_name VARCHAR(100), predicted_aqi FLOAT, predicted_class VARCHAR(20),
  actual_aqi FLOAT, confidence_score FLOAT, uncertainty_lower FLOAT, uncertainty_upper FLOAT,
  trust_level ENUM('HIGH','MEDIUM','LOW'), latency_ms FLOAT,
  shap_values TEXT, lime_explanation TEXT, attention_weights TEXT,
  INDEX(city_id, timestamp, model_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ensemble_weights` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), updated_at DATETIME,
  weight_xgboost FLOAT DEFAULT 0.25, weight_random_forest FLOAT DEFAULT 0.25,
  weight_bilstm FLOAT DEFAULT 0.25, weight_xgb_bilstm FLOAT DEFAULT 0.25,
  score_xgboost FLOAT, score_rf FLOAT, score_bilstm FLOAT, score_xgb_bilstm FLOAT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `drift_monitoring` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), detected_at DATETIME,
  drift_score FLOAT, kl_divergence FLOAT, statistical_shift FLOAT,
  drift_detected TINYINT(1) DEFAULT 0, retraining_triggered TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `anomaly_events` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), detected_at DATETIME,
  aqi_value FLOAT, anomaly_score FLOAT, anomaly_type VARCHAR(50),
  autoencoder_reconstruction_error FLOAT, isolation_forest_score FLOAT,
  description TEXT, excluded_from_training TINYINT(1) DEFAULT 1, INDEX(city_id, detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `smart_alerts` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), triggered_at DATETIME,
  alert_level ENUM('INFO','WARNING','CRITICAL'), predicted_aqi FLOAT, confidence FLOAT,
  explanation TEXT, shap_top_features TEXT, lime_local_explanation TEXT,
  recommendations TEXT, acknowledged TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `health_impact` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), timestamp DATETIME,
  aqi_value FLOAT, pm25_value FLOAT, so2_value FLOAT, vulnerable_population_pct FLOAT,
  health_impact_score FLOAT, health_risk_level VARCHAR(50), recommendations TEXT, INDEX(city_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ablation_results` (
  id INT PRIMARY KEY AUTO_INCREMENT, experiment_name VARCHAR(100), components_used TEXT,
  rmse FLOAT, f1 FLOAT, mae FLOAT, r_squared FLOAT, auc FLOAT, evaluated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `granger_causality` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), cause_variable VARCHAR(50),
  effect_variable VARCHAR(50), lag_hours INT, f_statistic FLOAT, p_value FLOAT,
  is_causal TINYINT(1), confidence_level FLOAT, computed_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `optimization_history` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), cycle_date DATETIME,
  rmse_before FLOAT, rmse_after FLOAT, f1_before FLOAT, f1_after FLOAT,
  features_added TEXT, features_removed TEXT, improvement_pct FLOAT,
  optuna_trials INT, best_params TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `optuna_trials` (
  id INT PRIMARY KEY AUTO_INCREMENT, model_name VARCHAR(100), city_id VARCHAR(50),
  trial_number INT, params TEXT, rmse FLOAT, f1 FLOAT, r_squared FLOAT,
  duration_seconds FLOAT, created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attention_heatmaps` (
  id INT PRIMARY KEY AUTO_INCREMENT, city_id VARCHAR(50), model_name VARCHAR(100),
  timestamp DATETIME, horizon VARCHAR(10), attention_matrix TEXT, peak_hour INT, peak_weight FLOAT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lime_explanations` (
  id INT PRIMARY KEY AUTO_INCREMENT, prediction_id INT, city_id VARCHAR(50), timestamp DATETIME,
  feature_name VARCHAR(100), lime_weight FLOAT, feature_value FLOAT, direction ENUM('positive','negative')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `websocket_sessions` (
  id INT PRIMARY KEY AUTO_INCREMENT, session_id VARCHAR(100), city_id VARCHAR(50),
  connected_at DATETIME, last_update DATETIME, updates_sent INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `federated_rounds` (
  id INT PRIMARY KEY AUTO_INCREMENT, round_number INT, participating_cities TEXT,
  global_rmse_before FLOAT, global_rmse_after FLOAT, aggregation_method VARCHAR(50), completed_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
