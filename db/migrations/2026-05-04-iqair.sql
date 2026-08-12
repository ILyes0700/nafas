-- =========================================================================
-- Gabès-Tatenafas v2.1 — IQAir (AirVisual) integration
--
-- Ajoute la colonne `zones.pollution_updated_at` pour le cache de
-- l'actualisation IQAir (1 actualisation par zone toutes les 60 min).
--
-- Idempotent : peut être relancée sans erreur.
-- =========================================================================

DELIMITER //

-- Helper réutilisable (silencieux si déjà créé par migration précédente)
DROP PROCEDURE IF EXISTS gt_add_col_if_missing //
CREATE PROCEDURE gt_add_col_if_missing (IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col;
  IF n = 0 THEN
    SET @s = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //

DELIMITER ;

CALL gt_add_col_if_missing(
  'zones',
  'pollution_updated_at',
  '`pollution_updated_at` DATETIME NULL AFTER `pollution_level`'
);

DROP PROCEDURE IF EXISTS gt_add_col_if_missing;
