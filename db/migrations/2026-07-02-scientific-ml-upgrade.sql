-- ============================================================
-- Gabes Tatenafas / Nafass — Scientific ML/DL upgrade (v2)
-- Tables backing the new pages:
--   * Deep Learning (BiLSTM + Multi-Head Attention)   -> deep-learning.php
--   * Anomaly Detection (Autoencoder + IsoForest)     -> anomaly.php
--   * Model Comparison Dashboard                       -> comparison.php
--
-- The new API endpoints work WITHOUT these tables (deterministic demo data),
-- but populating them switches the UI to real results automatically.
-- ============================================================

CREATE TABLE IF NOT EXISTS model_performance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_name VARCHAR(100), city_id VARCHAR(50),
  evaluated_at DATETIME, horizon VARCHAR(10),
  accuracy FLOAT, precision_macro FLOAT,
  recall_macro FLOAT, f1_macro FLOAT,
  mae FLOAT, rmse FLOAT, mape FLOAT, smape FLOAT,
  r_squared FLOAT, auc_roc FLOAT,
  avg_latency_ms FLOAT,
  cv_mean_f1 FLOAT, cv_std_f1 FLOAT,
  improvement_vs_baseline FLOAT,
  wilcoxon_pvalue FLOAT,
  optuna_best_params TEXT,
  INDEX(model_name, city_id, horizon)
);

CREATE TABLE IF NOT EXISTS model_predictions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  city_id VARCHAR(50), timestamp DATETIME,
  horizon VARCHAR(10), model_name VARCHAR(100),
  predicted_aqi FLOAT, predicted_class VARCHAR(20),
  actual_aqi FLOAT, confidence_score FLOAT,
  uncertainty_lower FLOAT, uncertainty_upper FLOAT,
  trust_level ENUM('HIGH','MEDIUM','LOW'),
  latency_ms FLOAT,
  lime_explanation TEXT,
  attention_weights TEXT,
  INDEX(city_id, timestamp, model_name)
);

CREATE TABLE IF NOT EXISTS ablation_results (
  id INT PRIMARY KEY AUTO_INCREMENT,
  experiment_name VARCHAR(100),
  components_used TEXT,
  rmse FLOAT, f1 FLOAT, mae FLOAT,
  r_squared FLOAT, auc FLOAT,
  evaluated_at DATETIME
);

CREATE TABLE IF NOT EXISTS anomaly_events (
  id INT PRIMARY KEY AUTO_INCREMENT,
  city_id VARCHAR(50), detected_at DATETIME,
  aqi_value FLOAT, anomaly_score FLOAT,
  anomaly_type VARCHAR(50),
  autoencoder_reconstruction_error FLOAT,
  isolation_forest_score FLOAT,
  description TEXT,
  excluded_from_training TINYINT(1) DEFAULT 1,
  INDEX(city_id, detected_at)
);

CREATE TABLE IF NOT EXISTS attention_heatmaps (
  id INT PRIMARY KEY AUTO_INCREMENT,
  city_id VARCHAR(50), model_name VARCHAR(100),
  timestamp DATETIME, horizon VARCHAR(10),
  attention_matrix TEXT,
  peak_hour INT, peak_weight FLOAT
);

CREATE TABLE IF NOT EXISTS optuna_trials (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_name VARCHAR(100), city_id VARCHAR(50),
  trial_number INT, params TEXT,
  rmse FLOAT, f1 FLOAT, r_squared FLOAT,
  duration_seconds FLOAT, created_at DATETIME
);
