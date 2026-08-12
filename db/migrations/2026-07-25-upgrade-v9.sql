-- ============================================================================
-- UPGRADE v9 (2026-07-25) — Carte : nouvelles couches (Layers)
-- Idempotent. Ne touche pas school_status (table distincte déjà existante).
-- ============================================================================

-- Part 55.1 — Couche Écoles (géolocalisées pour la carte)
CREATE TABLE IF NOT EXISTS schools (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150),
  zone_id VARCHAR(50),
  lat FLOAT,
  lng FLOAT,
  current_status ENUM('normal','vigilance','suspension') DEFAULT 'normal',
  KEY idx_school_zone (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Part 55.2 — Couche Zones sûres / points de refuge
CREATE TABLE IF NOT EXISTS safe_points (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150),
  type ENUM('mosque','health_center','school_closed','other') DEFAULT 'other',
  zone_id VARCHAR(50),
  lat FLOAT,
  lng FLOAT,
  has_filtration TINYINT(1) DEFAULT 0,
  KEY idx_safe_zone (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Semences facultatives (uniquement si les tables sont vides) --------------
INSERT INTO schools (name, zone_id, lat, lng, current_status)
SELECT * FROM (
  SELECT 'École Ghannouche Centre' AS name, '1' AS zone_id, 33.9290 AS lat, 10.0720 AS lng, 'normal' AS current_status
  UNION ALL SELECT 'École Chatt Essalem', '2', 33.8850, 10.1050, 'normal'
  UNION ALL SELECT 'École Gabès Médina', '3', 33.8815, 10.0980, 'normal'
) seed
WHERE NOT EXISTS (SELECT 1 FROM schools LIMIT 1);

INSERT INTO safe_points (name, type, zone_id, lat, lng, has_filtration)
SELECT * FROM (
  SELECT 'Centre de santé Gabès' AS name, 'health_center' AS type, '3' AS zone_id, 33.8820 AS lat, 10.0990 AS lng, 1 AS has_filtration
  UNION ALL SELECT 'Grande Mosquée Gabès', 'mosque', '3', 33.8805, 10.0975, 0
) seed
WHERE NOT EXISTS (SELECT 1 FROM safe_points LIMIT 1);
