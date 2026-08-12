-- =====================================================
-- 2026-06-09 — PART 0 : Multi-API pollution data system
--
-- Tables fondatrices du moteur de fusion multi-API (AccuWeather/IQAir/WAQI).
-- Toutes les valeurs FUSIONNÉES (final_*) sont la source unique consommée
-- par les modules ML / DL / Fuzzy.
--
-- Idempotent : CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- =====================================================
 
USE gabes_tatenafas;
 
-- ---------- Lectures API fusionnées (cœur du système) ----------
CREATE TABLE IF NOT EXISTS api_readings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  city_id VARCHAR(50) NOT NULL,
  city_name VARCHAR(100),
  timestamp DATETIME NOT NULL,
  -- ===== VALEURS FUSIONNÉES (utilisées par ML/DL/Fuzzy) =====
  final_aqi FLOAT,
  final_category VARCHAR(50),
  final_pm25 FLOAT,
  final_pm10 FLOAT,
  final_no2 FLOAT,
  final_so2 FLOAT,
  final_o3 FLOAT,
  final_co FLOAT,
  final_temperature FLOAT,
  final_humidity FLOAT,
  final_wind_speed FLOAT,
  final_wind_direction FLOAT,
  final_pressure FLOAT,
  -- ===== ACCUWEATHER (primaire) =====
  accuw_aqi FLOAT,
  accuw_category VARCHAR(50),
  accuw_pm25 FLOAT,
  accuw_pm10 FLOAT,
  accuw_no2 FLOAT,
  accuw_so2 FLOAT,
  accuw_o3 FLOAT,
  accuw_co FLOAT,
  accuw_temp FLOAT,
  accuw_feels_like FLOAT,
  accuw_humidity FLOAT,
  accuw_wind_speed FLOAT,
  accuw_wind_dir FLOAT,
  accuw_pressure FLOAT,
  accuw_visibility FLOAT,
  accuw_uv_index FLOAT,
  accuw_cloud_cover FLOAT,
  accuw_dew_point FLOAT,
  accuw_weather_text VARCHAR(100),
  accuw_available TINYINT(1) DEFAULT 0,
  -- ===== ACCUWEATHER — prévisions 12h (features futures) =====
  accuw_forecast_1h FLOAT,
  accuw_forecast_3h FLOAT,
  accuw_forecast_6h FLOAT,
  accuw_forecast_12h FLOAT,
  accuw_forecast_temp_max FLOAT,
  accuw_forecast_wind_max FLOAT,
  -- ===== IQAIR =====
  iqair_aqi_us FLOAT,
  iqair_aqi_cn FLOAT,
  iqair_main_pollutant VARCHAR(20),
  iqair_pm25 FLOAT,
  iqair_pm10 FLOAT,
  iqair_temp FLOAT,
  iqair_humidity FLOAT,
  iqair_wind_speed FLOAT,
  iqair_wind_dir FLOAT,
  iqair_pressure FLOAT,
  iqair_available TINYINT(1) DEFAULT 0,
  -- ===== WAQI =====
  waqi_aqi FLOAT,
  waqi_pm25 FLOAT,
  waqi_pm10 FLOAT,
  waqi_no2 FLOAT,
  waqi_so2 FLOAT,
  waqi_o3 FLOAT,
  waqi_co FLOAT,
  waqi_temp FLOAT,
  waqi_humidity FLOAT,
  waqi_wind FLOAT,
  waqi_available TINYINT(1) DEFAULT 0,
  data_quality_score FLOAT,
  fusion_method VARCHAR(50),
  INDEX idx_city_ts (city_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
-- ---------- Configuration / quotas par API ----------
CREATE TABLE IF NOT EXISTS api_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  api_name VARCHAR(50) UNIQUE,
  api_key VARCHAR(200),
  is_active TINYINT(1) DEFAULT 1,
  daily_calls_used INT DEFAULT 0,
  daily_limit INT,
  last_reset DATE,
  last_success DATETIME,
  avg_response_time FLOAT,
  success_rate FLOAT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
INSERT IGNORE INTO api_config
  (api_name, api_key, is_active, daily_calls_used, daily_limit, last_reset, last_success, avg_response_time, success_rate)
VALUES
  ('accuweather', 'SET_IN_backend/config/accuweather.php', 1, 0, 50,    CURDATE(), NULL, 0, 100),
  ('iqair',       'SET_IN_backend/config/iqair.php',       1, 0, 10000, CURDATE(), NULL, 0, 100),
  ('waqi',        'SET_IN_backend/config/waqi.php',        1, 0, 1000,  CURDATE(), NULL, 0, 100);
 
-- ---------- Évaluations Fuzzy (Type-2) liées aux lectures ----------
CREATE TABLE IF NOT EXISTS fuzzy_assessments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  reading_id INT,
  city_id VARCHAR(50),
  timestamp DATETIME,
  pollution_input FLOAT,
  vulnerability_input FLOAT,
  symptom_severity_input FLOAT,
  alerts_24h_input FLOAT,
  fuzzy_score_type2 FLOAT,
  uncertainty_lower FLOAT,
  uncertainty_upper FLOAT,
  risk_level ENUM('low','moderate','high','critical'),
  INDEX idx_city_ts (city_id, timestamp),
  CONSTRAINT fk_fuzzy_reading FOREIGN KEY (reading_id) REFERENCES api_readings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
-- ---------- Performance des modèles (comparaison ML/DL/Fuzzy) ----------
CREATE TABLE IF NOT EXISTS model_performance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  model_name VARCHAR(100),
  city_id VARCHAR(50),
  evaluated_at DATETIME,
  accuracy FLOAT,
  precision_macro FLOAT,
  recall_macro FLOAT,
  f1_macro FLOAT,
  mae FLOAT,
  rmse FLOAT,
  mape FLOAT,
  smape FLOAT,
  r_squared FLOAT,
  auc_roc FLOAT,
  avg_latency_ms FLOAT,
  cv_results TEXT,
  improvement_vs_baseline FLOAT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
SELECT 'Migration 2026-06-09 — OK (api_readings, api_config, fuzzy_assessments, model_performance)' AS message;