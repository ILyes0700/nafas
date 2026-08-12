-- ============================================================================
-- migration_open_data.sql  --  Gabes-Tatenafas v4.0
-- Migration vers donnees reelles Open-Meteo / CAMS + suppression du CGAN
--
-- VERSION CORRIGEE POUR WAMPSERVER (chemin CSV deja mis a jour).
-- A EXECUTER EN UNE SEULE PASSE dans l'onglet SQL de phpMyAdmin.
--
-- Le script est idempotent : le relancer une deuxieme fois ne casse rien
-- et ne duplique aucune donnee.
-- ============================================================================

USE `gabes_tatenafas`;

SET NAMES utf8mb4;


-- ============================================================================
-- ETAPE A  --  Creation de la table open_data
-- ============================================================================
-- 21 colonnes, dans l'ORDRE EXACT du CSV gabes_air_quality_dataset.csv.
-- Cet ordre est important : LOAD DATA les lit positionnellement.

CREATE TABLE IF NOT EXISTS `open_data` (
  `id`                   INT(11)      NOT NULL AUTO_INCREMENT,
  `city`                 VARCHAR(50)  NOT NULL,
  `time`                 DATETIME     NOT NULL,
  `pm2_5`                FLOAT        DEFAULT NULL,
  `pm10`                 FLOAT        DEFAULT NULL,
  `nitrogen_dioxide`     FLOAT        DEFAULT NULL,
  `sulphur_dioxide`      FLOAT        DEFAULT NULL,
  `ozone`                FLOAT        DEFAULT NULL,
  `carbon_monoxide`      FLOAT        DEFAULT NULL,
  `dust`                 FLOAT        DEFAULT NULL,
  `us_aqi`               FLOAT        DEFAULT NULL,
  `temperature_2m`       FLOAT        DEFAULT NULL,
  `relative_humidity_2m` FLOAT        DEFAULT NULL,
  `surface_pressure`     FLOAT        DEFAULT NULL,
  `wind_speed_10m`       FLOAT        DEFAULT NULL,
  `wind_direction_10m`   FLOAT        DEFAULT NULL,
  `precipitation`        FLOAT        DEFAULT NULL,
  `cloud_cover`          FLOAT        DEFAULT NULL,
  `hour`                 TINYINT(4)   DEFAULT NULL,
  `day_of_week`          TINYINT(4)   DEFAULT NULL,
  `month`                TINYINT(4)   DEFAULT NULL,
  `is_weekend`           TINYINT(1)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_city_time` (`city`, `time`),
  KEY `idx_city_time` (`city`, `time`),
  KEY `idx_time` (`time`),
  KEY `idx_aqi` (`us_aqi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La cle UNIQUE (city, time) est ce qui rend l'import re-jouable : si tu
-- relances LOAD DATA, les lignes deja presentes sont ignorees au lieu d'etre
-- dupliquees (grace au mot-cle IGNORE ci-dessous).


-- ============================================================================
-- ETAPE B  --  Import du CSV
-- ============================================================================
-- Chemin corrige pour ton install WampServer :
--   C:/wamp64/www/gabes-tatenafas-main/gabes-tatenafas/db/gabes_air_quality_dataset.csv
--
-- Si MySQL refuse avec "The used command is not allowed", c'est que
-- local_infile est desactive. Dans ce cas, saute cette etape et passe par
-- l'onglet Importer de phpMyAdmin (procedure detaillee en section 6.2).

LOAD DATA LOCAL INFILE 'C:/wamp64/www/gabes-tatenafas-main/gabes-tatenafas/db/gabes_air_quality_dataset.csv'
IGNORE INTO TABLE `open_data`
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(`city`, `time`, `pm2_5`, `pm10`, `nitrogen_dioxide`, `sulphur_dioxide`,
 `ozone`, `carbon_monoxide`, `dust`, `us_aqi`, `temperature_2m`,
 `relative_humidity_2m`, `surface_pressure`, `wind_speed_10m`,
 `wind_direction_10m`, `precipitation`, `cloud_cover`, `hour`,
 `day_of_week`, `month`, `is_weekend`);

-- Si ton CSV vient de Windows, les lignes finissent peut-etre par \r\n.
-- Symptome : la colonne is_weekend est vide partout. Remede : remplace
-- LINES TERMINATED BY '\n' par LINES TERMINATED BY '\r\n'.

-- Verification immediate de l'import
SELECT
  `city`,
  COUNT(*)     AS nb_lignes,
  MIN(`time`)  AS debut,
  MAX(`time`)  AS fin,
  ROUND(AVG(`us_aqi`), 1) AS aqi_moyen
FROM `open_data`
GROUP BY `city`
ORDER BY `city`;

-- ATTENDU : 7 lignes, environ 21 900 par ville, du 2024-01-01 au 2026-07-02.
-- Si tu vois 1 seule ligne nommee "city", tu as oublie IGNORE 1 ROWS.


-- ============================================================================
-- ETAPE C  --  Suppression des tables CGAN (section 1.3)
-- ============================================================================

DROP TABLE IF EXISTS `api_readings_augmented`;
DROP TABLE IF EXISTS `risk_scores_augmented`;

-- api_readings est CONSERVEE volontairement : elle contient tes vraies mesures
-- terrain 3-API (juin-juillet 2026) et sert de reference de comparaison. Elle
-- n'est simplement plus la source d'entrainement.


-- ============================================================================
-- ETAPE D  --  Correction de la table zones (section 1.2)
-- ============================================================================
-- Tout est encapsule dans une procedure pour pouvoir tester l'existence de la
-- colonne city_key et rendre le script re-jouable sans erreur.

DROP PROCEDURE IF EXISTS `sp_migrate_zones_open_data`;

DELIMITER $$

CREATE PROCEDURE `sp_migrate_zones_open_data`()
BEGIN
  DECLARE v_has_key INT DEFAULT 0;
  DECLARE v_done    INT DEFAULT 0;

  -- La colonne city_key existe-t-elle deja ?
  SELECT COUNT(*) INTO v_has_key
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'zones'
    AND COLUMN_NAME  = 'city_key';

  -- La migration a-t-elle deja tourne ?
  IF v_has_key > 0 THEN
    SELECT COUNT(*) INTO v_done FROM `zones` WHERE `city_key` IS NOT NULL;
  END IF;

  IF v_done >= 7 THEN
    SELECT 'Migration zones DEJA EFFECTUEE - aucune action.' AS info;
  ELSE

    -- --- D.1 : ajout de city_key -------------------------------------------
    IF v_has_key = 0 THEN
      ALTER TABLE `zones`
        ADD COLUMN `city_key` VARCHAR(50) NULL AFTER `name`;
    END IF;

    -- --- D.2 : elargissement des coordonnees --------------------------------
    -- Le seed fournit 9 decimales ; DECIMAL(10,6) les tronquerait.
    ALTER TABLE `zones`
      MODIFY COLUMN `lat` DECIMAL(12,9) DEFAULT NULL,
      MODIFY COLUMN `lng` DECIMAL(12,9) DEFAULT NULL;

    SET FOREIGN_KEY_CHECKS = 0;

    -- --- D.3 : decalage temporaire a +1000 ----------------------------------
    -- Indispensable : les zones 2 et 3 s'ECHANGENT. Un UPDATE direct
    -- ecraserait l'une avec l'autre.
    UPDATE `alerts`            SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `users`             SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `reports`           SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `symptoms`          SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `school_status`     SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `risk_scores`       SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `pollution_forecast` SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;
    UPDATE `fuzzy_reco_logs`   SET `zone_id` = `zone_id` + 1000 WHERE `zone_id` BETWEEN 1 AND 6;

    -- --- D.4 : remapping vers les nouveaux ids ------------------------------
    -- 1->1 Centre Ville  = Gabes_ville
    -- 2->3 Chatt Salem   = Chott_Salem
    -- 3->2 Ghannouche    = Ghannouche
    -- 4->5 Chenini       = Chenini
    -- 5->6 El Bled       = El_Bled
    -- 6->1 Bouchamma     -> absorbee par Gabes Ville (absente du dataset)
    UPDATE `alerts`             SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `users`              SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `reports`            SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `symptoms`           SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `school_status`      SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `risk_scores`        SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `pollution_forecast` SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;
    UPDATE `fuzzy_reco_logs`    SET `zone_id` = CASE `zone_id` WHEN 1001 THEN 1 WHEN 1002 THEN 3 WHEN 1003 THEN 2 WHEN 1004 THEN 5 WHEN 1005 THEN 6 WHEN 1006 THEN 1 ELSE `zone_id` END WHERE `zone_id` BETWEEN 1001 AND 1006;

    -- --- D.5 : api_readings utilise city_id en VARCHAR ----------------------
    -- Attention : ce n'est PAS une cle etrangere, c'est une chaine ('1'..'6').
    -- On la remappe aussi pour que les anciennes mesures terrain restent
    -- rattachees a la bonne zone.
    UPDATE `api_readings` SET `city_id` = CONCAT('tmp', `city_id`)
      WHERE `city_id` IN ('1','2','3','4','5','6');
    UPDATE `api_readings` SET `city_id` = CASE `city_id`
        WHEN 'tmp1' THEN '1' WHEN 'tmp2' THEN '3' WHEN 'tmp3' THEN '2'
        WHEN 'tmp4' THEN '5' WHEN 'tmp5' THEN '6' WHEN 'tmp6' THEN '1'
        ELSE `city_id` END
      WHERE `city_id` LIKE 'tmp%';

    -- --- D.6 : nouveau seed des 7 villes reelles ----------------------------
    DELETE FROM `zones`;
    ALTER TABLE `zones` AUTO_INCREMENT = 1;

    INSERT INTO `zones`
      (`id`, `name`, `city_key`, `name_ar`, `category`, `population`,
       `pollution_level`, `status`, `lat`, `lng`, `description`)
    VALUES
      (1, 'Gabes Ville',  'Gabes_ville',  'مدينة قابس', 'urban',      75000, 47, 'warning',  33.889334848, 10.096435713, 'Centre-ville de Gabes.'),
      (2, 'Ghannouche',   'Ghannouche',   'غنوش',       'industrial', 32000, 82, 'critical', 33.943871971, 10.067081982, 'Zone industrielle - proche du complexe chimique (GCT).'),
      (3, 'Chott Salem',  'Chott_Salem',  'شط السلام',  'industrial', 45000, 71, 'critical', 33.901897588, 10.100104537, 'Sous le vent du complexe chimique, exposition frequente au SO2.'),
      (4, 'Teboulbou',    'Teboulbou',    'تبلبو',      'urban',      20000, 40, 'warning',  33.840965860, 10.130874866, 'Zone peripherique sud de Gabes.'),
      (5, 'Chenini',      'Chenini',      'شنني',       'rural',      18000, 27, 'safe',     33.879739967, 10.063002515, 'Chenini Nahal - village semi-rural oasien.'),
      (6, 'El Bled',      'El_Bled',      'البلد',      'urban',      28000, 54, 'warning',  33.891980366, 10.084975530, 'Vieille ville de Gabes, coeur residentiel dense.'),
      (7, 'Matmata',      'Matmata',      'مطماطة',     'rural',      15000, 25, 'safe',     33.445236290,  9.804702222, 'Matmata - zone rurale, hors influence directe du complexe chimique.');

    -- --- D.7 : contrainte d'unicite sur city_key ----------------------------
    -- Posee APRES le seed : si on la posait avant, les lignes NULL heritees
    -- de l'ancien seed pourraient poser probleme selon la version de MySQL.
    ALTER TABLE `zones`
      ADD UNIQUE KEY `uq_zones_city_key` (`city_key`);

    SET FOREIGN_KEY_CHECKS = 1;

    SELECT 'Migration zones TERMINEE - 7 villes reelles en place.' AS info;
  END IF;
END$$

DELIMITER ;

CALL `sp_migrate_zones_open_data`();

DROP PROCEDURE IF EXISTS `sp_migrate_zones_open_data`;


-- ============================================================================
-- ETAPE E  --  Verifications finales
-- ============================================================================

-- E.1 : les 7 zones et leur cle de jointure
SELECT `id`, `name`, `city_key`, `category`, `lat`, `lng`
FROM `zones` ORDER BY `id`;

-- E.2 : la jointure zones <-> open_data fonctionne-t-elle vraiment ?
-- ATTENDU : 7 lignes, aucune avec nb_mesures = 0.
SELECT
  z.`id`,
  z.`name`,
  z.`city_key`,
  COUNT(o.`id`) AS nb_mesures,
  ROUND(AVG(o.`us_aqi`), 1) AS aqi_moyen
FROM `zones` z
LEFT JOIN `open_data` o ON o.`city` = z.`city_key`
GROUP BY z.`id`, z.`name`, z.`city_key`
ORDER BY z.`id`;

-- E.3 : recherche de lignes orphelines dans TOUTES les tables dependantes.
-- ATTENDU : 0 partout. Une valeur > 0 signifie qu'un zone_id pointe dans le
-- vide et que la contrainte FK sera refusee.
SELECT 'alerts'             AS table_name, COUNT(*) AS orphelins FROM `alerts`             WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'users',              COUNT(*) FROM `users`              WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'reports',            COUNT(*) FROM `reports`            WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'symptoms',           COUNT(*) FROM `symptoms`           WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'school_status',      COUNT(*) FROM `school_status`      WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'risk_scores',        COUNT(*) FROM `risk_scores`        WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'pollution_forecast', COUNT(*) FROM `pollution_forecast` WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`)
UNION ALL SELECT 'fuzzy_reco_logs',    COUNT(*) FROM `fuzzy_reco_logs`    WHERE `zone_id` IS NOT NULL AND `zone_id` NOT IN (SELECT `id` FROM `zones`);

-- E.4 : les tables CGAN ont bien disparu. ATTENDU : 0 ligne.
SELECT `TABLE_NAME`
FROM information_schema.TABLES
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` IN ('api_readings_augmented', 'risk_scores_augmented');

-- E.5 : volume total importe. ATTENDU : environ 130 000 - 155 000 lignes.
SELECT COUNT(*) AS total_lignes_open_data FROM `open_data`;

-- ============================================================================
-- FIN. Si E.3 renvoie 0 partout et E.2 renvoie 7 villes peuplees,
-- la migration est reussie : tu peux lancer python -m models.train_all
-- ============================================================================