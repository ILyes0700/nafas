-- ============================================================
-- Migration 2026-05-02 — Analyse IA des images de signalements
-- À exécuter sur la base existante `gabes_tatenafas`
--   phpMyAdmin → onglet SQL → coller ce fichier → Exécuter
--   OU :   mysql -u root gabes_tatenafas < 2026-05-02-ai-image-analysis.sql
-- Idempotent : utilise des vérifications conditionnelles.
-- ============================================================

USE `gabes_tatenafas`;

-- ----------------------------------------------------------------------
-- 1) Colonne `ai_analysis` : analyse textuelle de l'image par le modèle
--    Groq Vision (llama-4-scout). NULL tant qu'aucune image n'a été
--    analysée pour ce signalement.
-- ----------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'ai_analysis'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `reports`
     ADD COLUMN `ai_analysis` TEXT
     COLLATE utf8mb4_unicode_ci NULL AFTER `image_path`',
  'SELECT 1');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- ----------------------------------------------------------------------
-- 2) Colonne `ai_analysis_at` : horodatage de l'analyse (debug + cache).
-- ----------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'ai_analysis_at'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `reports`
     ADD COLUMN `ai_analysis_at` DATETIME NULL AFTER `ai_analysis`',
  'SELECT 1');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;

-- Fin de migration ✓
SELECT 'Migration 2026-05-02 — OK (ai_analysis, ai_analysis_at)' AS result;
