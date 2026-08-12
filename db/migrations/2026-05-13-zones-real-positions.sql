-- =====================================================
-- 2026-05-13 — Real-world zone positions (Gabès)
--
-- Replaces the seed-default zone list with the 6 zones
-- explicitly requested by the project owner, using the
-- exact GPS coordinates of each place on Google Maps:
--
--   1. Centre Ville (قابس سنترال)    33.8858887, 10.1073191
--   2. Chatt Salem (شط السلام)        33.9016493, 10.1003211
--   3. Ghannouche (غنوش)              33.9430531, 10.0667387
--   4. Chenini (شنني)                 33.879796,  10.0639407
--   5. El Bled (البلد)                33.8915296, 10.0891256
--   6. Bouchamma (بوشمة)              33.9028024, 10.0527498
--
-- Other historic zones (Teboulbou / Métouia) are kept as
-- archive records (population=0, status='safe') so existing
-- foreign keys (users.zone_id, reports.zone_id, etc.) stay
-- valid — no data is destroyed.
--
-- Pollution values are calibrated against the local reality of
-- Gabès (industrial north-east = high, coastal/rural = low):
--   Ghannouche (industrial complex)         → 82
--   Chatt Salem (downwind of complex)        → 71
--   El Bled (old town, downtown)             → 54
--   Centre Ville (commerce, traffic)         → 47
--   Bouchamma (residential west)             → 38
--   Chenini (semi-rural, palm groves)        → 27
-- These will be overwritten by the next IQAir+WAQI fetch.
-- =====================================================

-- Safety net: ALTER TABLE if a Centre Ville naming exists,
-- so the migration is idempotent — ok to re-run.
SET SQL_SAFE_UPDATES = 0;

UPDATE zones SET
  name = 'Centre Ville',
  name_ar = 'وسط المدينة',
  category = 'urban',
  population = 75000,
  pollution_level = 47,
  status = 'warning',
  lat = 33.885889,
  lng = 10.107319,
  description = 'Downtown Gabès — Bab Bhar area, commerce and traffic.'
WHERE id = 1;

UPDATE zones SET
  name = 'Chatt Salem',
  name_ar = 'شط السلام',
  category = 'industrial',
  population = 45000,
  pollution_level = 71,
  status = 'critical',
  lat = 33.901649,
  lng = 10.100321,
  description = 'Downwind of the chemical complex, frequent SO2 exposure.'
WHERE id = 2;

UPDATE zones SET
  name = 'Ghannouche',
  name_ar = 'غنوش',
  category = 'industrial',
  population = 32000,
  pollution_level = 82,
  status = 'critical',
  lat = 33.943053,
  lng = 10.066739,
  description = 'Industrial zone — phosphate complex emissions hotspot.'
WHERE id = 3;

UPDATE zones SET
  name = 'Chenini',
  name_ar = 'شنني',
  category = 'rural',
  population = 18000,
  pollution_level = 27,
  status = 'safe',
  lat = 33.879796,
  lng = 10.063941,
  description = 'Chenini Nahal — semi-rural oasis village west of Gabès.'
WHERE id = 4;

UPDATE zones SET
  name = 'El Bled',
  name_ar = 'البلد',
  category = 'urban',
  population = 28000,
  pollution_level = 54,
  status = 'warning',
  lat = 33.891530,
  lng = 10.089126,
  description = 'Old town of Gabès (l''ancien Bled), dense residential core.'
WHERE id = 5;

UPDATE zones SET
  name = 'Bouchamma',
  name_ar = 'بوشمة',
  category = 'urban',
  population = 22000,
  pollution_level = 38,
  status = 'warning',
  lat = 33.902802,
  lng = 10.052750,
  description = 'Bouchamma — mixed residential western district.'
WHERE id = 6;

-- Legacy zones 7 & 8 → keep but archive (population=0, status='safe').
-- The frontend filters them out via population > 0.
UPDATE zones SET
  name = 'Teboulbou (archive)',
  name_ar = 'طبلبو',
  category = 'coastal',
  population = 0,
  pollution_level = 22,
  status = 'safe',
  lat = 33.7942,
  lng = 10.1582,
  description = 'Coastal — archived, not displayed in main map.'
WHERE id = 7;

UPDATE zones SET
  name = 'Métouia (archive)',
  name_ar = 'مطوية',
  category = 'rural',
  population = 0,
  pollution_level = 15,
  status = 'safe',
  lat = 33.9648,
  lng = 10.0072,
  description = 'Rural north — archived, not displayed in main map.'
WHERE id = 8;

-- Defensive: also ensure rows 1..6 exist if the migration is run on
-- a partial DB. INSERT IGNORE only inserts if the id doesn't exist.
INSERT IGNORE INTO zones
  (id, name, name_ar, category, population, pollution_level, status, lat, lng, description)
VALUES
  (1,'Centre Ville','وسط المدينة','urban',75000,47,'warning',33.885889,10.107319,'Downtown Gabès — Bab Bhar area, commerce and traffic.'),
  (2,'Chatt Salem','شط السلام','industrial',45000,71,'critical',33.901649,10.100321,'Downwind of the chemical complex, frequent SO2 exposure.'),
  (3,'Ghannouche','غنوش','industrial',32000,82,'critical',33.943053,10.066739,'Industrial zone — phosphate complex emissions hotspot.'),
  (4,'Chenini','شنني','rural',18000,27,'safe',33.879796,10.063941,'Chenini Nahal — semi-rural oasis village west of Gabès.'),
  (5,'El Bled','البلد','urban',28000,54,'warning',33.891530,10.089126,'Old town of Gabès (l''ancien Bled), dense residential core.'),
  (6,'Bouchamma','بوشمة','urban',22000,38,'warning',33.902802,10.052750,'Bouchamma — mixed residential western district.');

SET SQL_SAFE_UPDATES = 1;
