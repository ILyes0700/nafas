-- =====================================================
-- Gabes Tatenafas - قابس تتنفس
-- Système intelligent d'alerte environnementale et sanitaire
-- Schéma MySQL complet + données de démonstration
-- =====================================================

CREATE DATABASE IF NOT EXISTS gabes_tatenafas
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gabes_tatenafas;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pollution_forecast;
DROP TABLE IF EXISTS daily_tips;
DROP TABLE IF EXISTS weekly_summaries;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS personal_diary;
DROP TABLE IF EXISTS fragile_profiles;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reports_pdf;
DROP TABLE IF EXISTS chatbot_logs;
DROP TABLE IF EXISTS risk_scores;
DROP TABLE IF EXISTS school_status;
DROP TABLE IF EXISTS school_absences;
DROP TABLE IF EXISTS symptom_messages;
DROP TABLE IF EXISTS symptoms;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS zones;
DROP TABLE IF EXISTS users_roles;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Rôles utilisateurs ----------
CREATE TABLE users_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(40) UNIQUE NOT NULL,
  label_fr VARCHAR(100) NOT NULL,
  label_ar VARCHAR(100) NOT NULL,
  description TEXT,
  permissions JSON
) ENGINE=InnoDB;

-- ---------- Zones de Gabès ----------
CREATE TABLE zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  name_ar VARCHAR(120) NOT NULL,
  category VARCHAR(60) DEFAULT 'urban',
  population INT DEFAULT 0,
  pollution_level INT DEFAULT 0,        -- 0..100
  pollution_updated_at DATETIME NULL,
  status ENUM('safe','warning','critical') DEFAULT 'safe',
  lat DECIMAL(10,6),
  lng DECIMAL(10,6),
  description TEXT
) ENGINE=InnoDB;

-- ---------- Alertes ----------
CREATE TABLE alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT,
  title VARCHAR(160) NOT NULL,
  message TEXT,
  severity ENUM('info','warning','danger','critical') DEFAULT 'info',
  type VARCHAR(60) DEFAULT 'pollution',
  priority_groups VARCHAR(120) NULL,             -- ex: 'asthma,child,elderly' (A6)
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved TINYINT(1) DEFAULT 0,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Comptes utilisateurs (auth) ----------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120),
  email VARCHAR(160),
  role ENUM('citizen','health','school','admin') NOT NULL DEFAULT 'citizen',
  zone_id INT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (zone_id),
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Signalements citoyens ----------
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT,
  citizen_name VARCHAR(120) DEFAULT 'Anonyme',
  category ENUM('odor','smoke','breathing','dust','noise','other') DEFAULT 'other',
  description TEXT,
  image_path VARCHAR(255) NULL,                  -- chemin relatif uploads/reports/...
  ai_analysis TEXT NULL,                         -- analyse Groq Vision (llama-4-scout)
  ai_category   VARCHAR(40) NULL,                -- catégorie IA (C1)
  ai_severity   TINYINT     NULL,                -- gravité 1..10 (C2)
  ai_intensity  VARCHAR(20) NULL,                -- low/medium/high (C2)
  ai_fake_score TINYINT     NULL,                -- 0..100 — suspect ≥70 (C3)
  image_hash    CHAR(64)    NULL,                -- sha256 — déduplication (C3)
  ai_analysis_at DATETIME NULL,                  -- horodatage analyse IA
  reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','validated','rejected') DEFAULT 'pending',
  KEY idx_reports_image_hash (image_hash),
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Symptômes ----------
CREATE TABLE symptoms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT,
  citizen_id INT NULL,                           -- lien vers users.id (citoyen connecté)
  citizen_name VARCHAR(120) DEFAULT 'Anonyme',
  symptom VARCHAR(120) NOT NULL,
  severity ENUM('mild','moderate','severe') DEFAULT 'mild',
  notes TEXT,
  triage_text    MEDIUMTEXT NULL,                -- triage IA (C10)
  triage_urgency VARCHAR(20) NULL,               -- low/medium/high/severe
  triage_at      DATETIME NULL,
  status ENUM('new','in_progress','resolved') NOT NULL DEFAULT 'new',  -- pris en charge par santé
  reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_symptoms_citizen (citizen_id),
  FOREIGN KEY (zone_id)    REFERENCES zones(id) ON DELETE SET NULL,
  FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Messages chat santé ↔ citoyen (par symptôme) ----------
CREATE TABLE symptom_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symptom_id INT NOT NULL,
  sender_id INT NULL,
  sender_role ENUM('citizen','health','admin') NOT NULL,
  sender_name VARCHAR(120) NULL,
  message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY idx_smsg_symptom (symptom_id),
  KEY idx_smsg_sender  (sender_id),
  FOREIGN KEY (symptom_id) REFERENCES symptoms(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Statut écoles ----------
CREATE TABLE school_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_name VARCHAR(160) NOT NULL,
  zone_id INT,
  status ENUM('normal','vigilance','danger','suspended') DEFAULT 'normal',
  absentees INT DEFAULT 0,
  symptoms_count INT DEFAULT 0,
  last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Risk Scores ----------
CREATE TABLE risk_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT,
  score INT DEFAULT 0,                  -- 0..100
  level ENUM('safe','warning','critical') DEFAULT 'safe',
  computed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Chatbot نفاس ----------
CREATE TABLE chatbot_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_label VARCHAR(120) DEFAULT 'citizen',
  message TEXT NOT NULL,
  response TEXT,
  intent VARCHAR(60),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- PDF Rapports ----------
CREATE TABLE reports_pdf (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160),
  period VARCHAR(40),
  filename VARCHAR(200),
  generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  generated_by VARCHAR(80) DEFAULT 'health_authority'
) ENGINE=InnoDB;

-- ---------- Notifications ----------
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_role    VARCHAR(40) DEFAULT 'all',
  target_user_id INT NULL,                       -- ciblage individuel (A6 — fragile)
  title VARCHAR(160),
  message TEXT,
  level ENUM('info','warning','danger') DEFAULT 'info',
  priority TINYINT NOT NULL DEFAULT 0,           -- 0=normal, 10=urgent fragile
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) DEFAULT 0,
  KEY idx_notif_user (target_user_id)
) ENGINE=InnoDB;

-- ---------- Absences scolaires (mode école) ----------
CREATE TABLE school_absences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  student_name VARCHAR(160) NOT NULL,
  student_class VARCHAR(60),
  absent_date DATE NOT NULL,
  reason ENUM('respiratoire','allergie','fievre','oculaire','digestif','asthme','autre','non_precise') DEFAULT 'non_precise',
  notes TEXT,
  reported_by VARCHAR(60),
  reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_school_date (school_id, absent_date),
  KEY idx_date (absent_date)
) ENGINE=InnoDB;

-- =====================================================
-- DONNÉES DE SEED
-- =====================================================

-- IMPORTANT : `users.zone_id` référence `zones.id` (FK), donc on insère
-- les zones AVANT les users. De même `users_roles` est indépendant et peut
-- venir où on veut, on le met en premier pour la lisibilité.

INSERT INTO users_roles (role_key, label_fr, label_ar, description, permissions) VALUES
('citizen','Citizen','المواطن','Gabès resident','["view_air","report","symptoms","chatbot"]'),
('health','Health Authority','المسؤول الصحي','Public-health decision maker','["dashboard","stats","reports","alerts"]'),
('school','School Director','مدير المدرسة','School administrator','["school_mode","alerts","reports"]'),
('admin','Administrator','المسؤول العام','Global management','["all"]');

INSERT INTO zones (name, name_ar, category, population, pollution_level, status, lat, lng, description) VALUES
('Centre Ville','وسط المدينة','urban',75000,47,'warning',33.885889,10.107319,'Downtown Gabès — Bab Bhar area, commerce and traffic.'),
('Chatt Salem','شط السلام','industrial',45000,71,'critical',33.901649,10.100321,'Downwind of the chemical complex, frequent SO2 exposure.'),
('Ghannouche','غنوش','industrial',32000,82,'critical',33.943053,10.066739,'Industrial zone — phosphate complex emissions hotspot.'),
('Chenini','شنني','rural',18000,27,'safe',33.879796,10.063941,'Chenini Nahal — semi-rural oasis village west of Gabès.'),
('El Bled','البلد','urban',28000,54,'warning',33.891530,10.089126,'Old town of Gabès (l''ancien Bled), dense residential core.'),
('Bouchamma','بوشمة','urban',22000,38,'warning',33.902802,10.052750,'Bouchamma — mixed residential western district.');

-- Comptes par défaut (mot de passe : admin123 / health123 / school123 / citizen123)
-- Hash bcrypt PASSWORD_BCRYPT cost 10 — issu du dump de production.
INSERT INTO users (username, password_hash, full_name, email, role, zone_id, is_active) VALUES
('admin',   '$2y$10$pvgTPyloVsCj803VFkxzbOK6dcCMpBck1eFfuxJwV97YNbxXqUCxy', 'Global Administrator',                 'admin@gabes-tatenafas.local',          'admin',   NULL, 1),
('health',  '$2y$10$EPKzkdMZoGjFpPh0dclhAehWnW8LkmdEsuoHfN.bNTG5Rg15aA7Hi', 'Regional Health Directorate',          'health@gabes-tatenafas.local',         'health',  1,    1),
('school',  '$2y$10$c2kETQmRlo7cCSINCf8trOnOUsNA0duDAbCKMG2.xYzhzODeUVgU.', 'Director of Ghannouche Primary 1',     'school.ghannouche@gabes-tatenafas.local','school',  3,    1),
('citizen1','$2y$10$9yomqQM8EAcvfqED/Qgu8.y6PKc9MuZ2k5ZGXZ03tODU4KK8Q4MXe', 'Ahmed Ben Ali',                        'ahmed@example.tn',                     'citizen', 1,    1);

INSERT INTO alerts (zone_id, title, message, severity, type, created_at) VALUES
(3,'SO₂ spike detected','High sulfur dioxide level recorded at Ghannouche. Avoid outdoor exertion.','critical','pollution', NOW() - INTERVAL 2 HOUR),
(2,'Respiratory vigilance','PM2.5 particulate matter rising in Chatt Salem.','danger','pollution', NOW() - INTERVAL 5 HOUR),
(1,'Degraded air quality','Centre Ville: moderate index, monitoring recommended.','warning','pollution', NOW() - INTERVAL 9 HOUR),
(4,'Reported odor','Several reports of sulfur smell in Chenini.','warning','odor', NOW() - INTERVAL 1 DAY),
(3,'School mode enabled','Suspension of outdoor activities advised for Ghannouche schools.','danger','school', NOW() - INTERVAL 3 HOUR),
(6,'Smoke reported','Visible smoke reported by residents of Bouchamma.','warning','smoke', NOW() - INTERVAL 6 HOUR);

INSERT INTO reports (zone_id, citizen_name, category, description, reported_at, status) VALUES
(3,'Ahmed B.','smoke','Thick smoke above the factory this morning.', NOW() - INTERVAL 4 HOUR,'validated'),
(2,'Salma K.','odor','Strong sulfur smell near the port.', NOW() - INTERVAL 7 HOUR,'pending'),
(1,'Anonymous','breathing','Difficulty breathing while walking.', NOW() - INTERVAL 1 DAY,'validated'),
(3,'Imen T.','dust','A lot of dust in the air.', NOW() - INTERVAL 2 HOUR,'pending'),
(4,'Anonymous','odor','Unusual chemical smell.', NOW() - INTERVAL 8 HOUR,'pending'),
(6,'Mohamed S.','smoke','Black smoke visible from my window.', NOW() - INTERVAL 30 MINUTE,'pending');

INSERT INTO symptoms (zone_id, citizen_name, symptom, severity, notes, reported_at) VALUES
(3,'Anonymous','Dry cough','moderate','Persistent since this morning', NOW() - INTERVAL 3 HOUR),
(3,'Anonymous','Headache','mild',NULL, NOW() - INTERVAL 5 HOUR),
(2,'Anonymous','Eye irritation','moderate','Red and watery eyes', NOW() - INTERVAL 6 HOUR),
(1,'Anonymous','Shortness of breath','severe','Difficulty breathing on exertion', NOW() - INTERVAL 1 DAY),
(4,'Anonymous','Nausea','mild',NULL, NOW() - INTERVAL 12 HOUR),
(3,'Anonymous','Dry cough','severe','Nocturnal attack', NOW() - INTERVAL 2 HOUR),
(2,'Anonymous','Sore throat','mild',NULL, NOW() - INTERVAL 4 HOUR);

INSERT INTO school_status (school_name, zone_id, status, absentees, symptoms_count, notes) VALUES
('Ghannouche Primary School 1', 3, 'danger', 22, 14, 'Outdoor activities suspended'),
('Gabès Pilot High School', 1, 'vigilance', 8, 4, 'Heightened monitoring'),
('Chatt Salem School', 2, 'vigilance', 11, 6, 'Parent notification sent'),
('Chenini College', 4, 'normal', 2, 1, NULL),
('El Bled School', 5, 'normal', 0, 0, NULL),
('Bouchamma School', 6, 'vigilance', 5, 3, 'Follow-up in progress');

-- Seed historic risk scores so the dashboard has data on day 1.
-- Values reflect the long-running pattern in Gabès: industrial north-east
-- spikes (Ghannouche, Chatt Salem), downtown moderate (Centre Ville, El Bled),
-- western residential lower (Bouchamma), rural west lowest (Chenini).
INSERT INTO risk_scores (zone_id, score, level) VALUES
(1, 51, 'warning'),
(2, 73, 'critical'),
(3, 84, 'critical'),
(4, 29, 'safe'),
(5, 56, 'warning'),
(6, 39, 'safe');

INSERT INTO chatbot_logs (user_label, message, response, intent) VALUES
('citizen','I have a headache and I am coughing','Your symptoms may be linked to the current pollution (high level). Stay indoors, hydrate, and see a doctor if they persist for more than 24 hours.','symptom_check'),
('citizen','What is the air quality in Ghannouche?','Ghannouche is currently at CRITICAL level. Avoid outdoor activities.','air_query');

INSERT INTO notifications (target_role, title, message, level) VALUES
('citizen','Local alert','High risk in your zone, limit outdoor exposure.','danger'),
('school','School mode recommended','Critical risk in Ghannouche — suspension of outdoor activities advised.','danger'),
('health','SO₂ spike','Continuous monitoring of critical zones.','warning'),
('all','System operational','Gabès Tatenafas application connected to the local database.','info');

INSERT INTO reports_pdf (title, period, filename, generated_by) VALUES
('Daily Report — Air Quality','daily','daily-report.pdf','health_authority'),
('Weekly Report','weekly','weekly-report.pdf','health_authority'),
('Monthly Summary','monthly','monthly-report.pdf','health_authority');


-- =====================================================
-- TABLES AJOUTÉES — Pack 2026-05-03 (full features)
-- (déjà fournies ici inline pour les nouvelles installations ;
--  la migration 2026-05-03-full-features.sql est idempotente
--  pour les bases existantes.)
-- =====================================================

CREATE TABLE fragile_profiles (
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
) ENGINE=InnoDB;

CREATE TABLE personal_diary (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  diary_date      DATE NOT NULL,
  mood            TINYINT NOT NULL DEFAULT 3,
  cough           TINYINT NOT NULL DEFAULT 0,
  breath_diff    TINYINT NOT NULL DEFAULT 0,
  eye_irritation  TINYINT NOT NULL DEFAULT 0,
  headache        TINYINT NOT NULL DEFAULT 0,
  fatigue         TINYINT NOT NULL DEFAULT 0,
  notes           VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_diary_user_date (user_id, diary_date),
  UNIQUE KEY u_diary_user_day (user_id, diary_date),
  CONSTRAINT fk_diary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  scope_key     VARCHAR(80) NOT NULL,
  action_type   VARCHAR(40) NOT NULL,
  occurred_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl_scope_time (scope_key, occurred_at)
) ENGINE=InnoDB;

CREATE TABLE weekly_summaries (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  week_start    DATE NOT NULL,
  week_end      DATE NOT NULL,
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  model         VARCHAR(80) NULL,
  summary_md    MEDIUMTEXT NOT NULL,
  metrics_json  TEXT NULL,
  UNIQUE KEY u_weekly_period (week_start, week_end)
) ENGINE=InnoDB;

CREATE TABLE daily_tips (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tip_date      DATE NOT NULL,
  language      VARCHAR(10) NOT NULL DEFAULT 'fr',
  status_at_gen VARCHAR(20) NULL,
  tip_text      MEDIUMTEXT NOT NULL,
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_tip_day_lang (tip_date, language)
) ENGINE=InnoDB;

CREATE TABLE pollution_forecast (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  zone_id         INT NOT NULL,
  horizon_hours   TINYINT NOT NULL,
  predicted_score TINYINT NOT NULL,
  predicted_level ENUM('safe','warning','critical') NOT NULL DEFAULT 'safe',
  computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_forecast_zone_horizon (zone_id, horizon_hours, computed_at),
  CONSTRAINT fk_forecast_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- =====================================================================
-- 2026-05-07 — Fuzzy Recommendations + Data Augmentation + Hybrid Forecast
-- =====================================================================
-- This migration adds the tables required for the 3 new academic features:
--   1. Fuzzy logic recommendations (logs of activated rules)
--   2. Multi-source API verification + synthetic data augmentation
--   3. Hybrid ML/DL pollution forecasting with quantitative metrics
--
-- Idempotent: safe to re-run.

-- ----- 1. Fuzzy recommendation logs ------------------------------------
-- Stores every fuzzy-based recommendation computed for a user so we can
-- trace which rules fired (explainability) and what the crisp output was.
CREATE TABLE IF NOT EXISTS fuzzy_reco_logs (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NULL,
  zone_id         INT NULL,
  pollution       INT NOT NULL,
  vulnerability   INT NOT NULL,            -- 0..10 (sum of weighted flags)
  symptom_sev     INT NOT NULL,            -- 0..10
  alerts_24h      INT NOT NULL,
  age             INT NULL,
  risk_fuzzy      DECIMAL(5,2) NOT NULL,   -- defuzzified centroid 0..100
  urgency_level   ENUM('low','moderate','high','critical') NOT NULL,
  fired_rules     TEXT NULL,               -- JSON list of rule ids + activations
  computed_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fuzzy_user (user_id),
  KEY idx_fuzzy_at   (computed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- 2A. API verification log ----------------------------------------
-- Each time an external air-quality API is queried we record raw value,
-- normalized value, outlier flags, and trust score. Lets the admin audit
-- the data pipeline and detect upstream API drifts.
CREATE TABLE IF NOT EXISTS api_verification_log (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  source           VARCHAR(20) NOT NULL,        -- 'iqair' | 'waqi' | 'openaq' | 'fused'
  zone_id          INT NOT NULL,
  raw_value        INT NULL,                    -- as returned by the source
  normalized_value INT NULL,                    -- 0..100 pollution_level scale
  trust_score      DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  flags            VARCHAR(120) NULL,           -- 'range_ok,outlier,cross_ok'
  verified_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_api_zone (zone_id, verified_at),
  KEY idx_api_src  (source, verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- 2B. Augmented (synthetic) historical scores ---------------------
-- Synthetic risk-score points generated by either PHP statistical methods
-- (jittering / time-warping / bootstrap) or a Python TimeGAN/Diffusion
-- pipeline. The forecast trainer reads BOTH real and synthetic data.
CREATE TABLE IF NOT EXISTS risk_scores_augmented (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  zone_id           INT NOT NULL,
  synthetic_at      DATETIME NOT NULL,
  score             INT NOT NULL,
  generation_method ENUM('jitter','magnitude_warp','time_warp','bootstrap',
                         'timegan','tsdiff','csdi') NOT NULL,
  generator_version VARCHAR(20) NULL,
  fidelity_score    DECIMAL(3,2) NULL,        -- 0..1 (Wasserstein-derived)
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aug_zone (zone_id, synthetic_at),
  KEY idx_aug_meth (generation_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- 3A. Hybrid ML/DL forecast predictions ---------------------------
-- The hybrid forecaster (PHP AR(7) ensembled with a DL-inspired multi-
-- EWMA-sigmoid; or, if Python is available, XGBoost+LSTM) writes its
-- predictions here. forecast.php prefers the most recent prediction
-- and falls back to the legacy EWMA only when the table is empty.
CREATE TABLE IF NOT EXISTS forecast_predictions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  zone_id         INT NOT NULL,
  horizon_hours   INT NOT NULL,                -- 6 | 12 | 24
  predicted_score INT NOT NULL,                -- 0..100
  predicted_level ENUM('safe','warning','critical') NOT NULL DEFAULT 'safe',
  method          VARCHAR(50) NOT NULL,        -- 'ensemble_ar_mewma' etc.
  confidence      DECIMAL(3,2) NULL,           -- 0..1
  computed_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fcst_zone (zone_id, horizon_hours, computed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- 3B. Forecast model metrics --------------------------------------
-- For every (model, zone, training run) we store the 5 standard error
-- metrics. Admin UI reads this to show the "EWMA vs Hybrid" comparison
-- and prove (numerically) that hybridization wins.
CREATE TABLE IF NOT EXISTS forecast_metrics (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  model_name   VARCHAR(60) NOT NULL,           -- 'ewma','ar7','mewma','ensemble','lstm','xgboost'
  zone_id      INT NULL,                       -- NULL = aggregated across all zones
  mae          DECIMAL(6,3) NULL,
  rmse         DECIMAL(6,3) NULL,
  mape         DECIMAL(6,3) NULL,              -- in percent
  r2           DECIMAL(5,3) NULL,
  smape        DECIMAL(6,3) NULL,              -- in percent
  sample_size  INT NULL,
  trained_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_metric_model (model_name, trained_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- 3C. WAQI cache (second source for verification) ----------------
CREATE TABLE IF NOT EXISTS waqi_cache (
  zone_id      INT PRIMARY KEY,
  aqi          INT NOT NULL,
  pollution    INT NOT NULL,
  station_name VARCHAR(140) NULL,
  fetched_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
