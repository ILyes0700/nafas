-- ============================================================
-- Migration 2026-05-01 — Chat santé/citoyen + Photos signalements
-- À exécuter sur la base existante `gabes_tatenafas`
--   phpMyAdmin → onglet SQL → coller ce fichier → Exécuter
--   OU :   mysql -u root gabes_tatenafas < 2026-05-01-chat-and-photos.sql
-- Idempotent : utilise IF NOT EXISTS / vérifications conditionnelles.
-- ============================================================

USE `gabes_tatenafas`;

-- ----------------------------------------------------------------------
-- 1) Ajout d'une colonne `status` aux symptômes
--    new       = signalé, pas encore traité
--    in_progress = pris en charge par l'autorité santé
--    resolved  = clos
-- ----------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'symptoms' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `symptoms`
     ADD COLUMN `status` ENUM(''new'',''in_progress'',''resolved'')
     COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''new'' AFTER `notes`',
  'SELECT 1');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- ----------------------------------------------------------------------
-- 2) Ajout de `citizen_id` aux symptômes (lien direct vers users)
--    Permet au citoyen connecté de voir SES propres symptômes/chat
--    (les anciennes lignes restent NULL — on retombe sur citizen_name)
-- ----------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'symptoms' AND COLUMN_NAME = 'citizen_id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `symptoms`
     ADD COLUMN `citizen_id` INT NULL AFTER `zone_id`,
     ADD KEY `idx_symptoms_citizen` (`citizen_id`)',
  'SELECT 1');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

-- ----------------------------------------------------------------------
-- 3) Table `symptom_messages` — fil de discussion santé ↔ citoyen
--    Le chat n'est visible côté citoyen qu'après le premier message
--    envoyé par un compte 'health' ou 'admin'.
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `symptom_messages` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `symptom_id`   INT NOT NULL,
  `sender_id`    INT DEFAULT NULL,
  `sender_role`  ENUM('citizen','health','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_name`  VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message`      TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `read_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_smsg_symptom` (`symptom_id`),
  KEY `idx_smsg_sender`  (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 4) Ajout de `image_path` aux signalements citoyens
--    Stocke un chemin relatif au projet, ex : uploads/reports/xxx.jpg
-- ----------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'image_path'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `reports`
     ADD COLUMN `image_path` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL AFTER `description`',
  'SELECT 1');
PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

-- ----------------------------------------------------------------------
-- 5) Backfill : tenter de relier les anciens symptômes au compte citoyen
--    Match basé sur citizen_name == users.full_name (si exact)
-- ----------------------------------------------------------------------
UPDATE `symptoms` s
JOIN `users` u ON u.full_name = s.citizen_name AND u.role = 'citizen'
SET s.citizen_id = u.id
WHERE s.citizen_id IS NULL;

-- Fin de migration ✓
SELECT 'Migration 2026-05-01 — OK' AS result;
