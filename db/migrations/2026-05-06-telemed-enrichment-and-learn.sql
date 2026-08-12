-- =====================================================
-- 2026-05-06 — Telemedicine enrichment + Learn module
-- =====================================================
-- Idempotent (can be re-run without risk).

-- ----- Telemedicine: pre-consultation checklist + post-consultation notes -----
-- We store structured data as JSON-as-TEXT (pre_consult / post_consult).
-- pre_consult = { temperature, pulse, oxygen_sat, symptoms, notes, photo_url }
-- post_consult = { diagnosis, recommendations, prescription, follow_up_days }
-- Idempotent column add (works on MySQL 5.6+ / MariaDB 10.0+).
DROP PROCEDURE IF EXISTS gt_add_telemed_columns;
DELIMITER //
CREATE PROCEDURE gt_add_telemed_columns()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'telemed_requests'
                   AND COLUMN_NAME  = 'pre_consult') THEN
    ALTER TABLE telemed_requests ADD COLUMN pre_consult TEXT NULL AFTER expires_at;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'telemed_requests'
                   AND COLUMN_NAME  = 'post_consult') THEN
    ALTER TABLE telemed_requests ADD COLUMN post_consult TEXT NULL AFTER pre_consult;
  END IF;
END //
DELIMITER ;
CALL gt_add_telemed_columns();
DROP PROCEDURE IF EXISTS gt_add_telemed_columns;

-- ----- Learn module: educational resources (videos, articles, infographics) -----
CREATE TABLE IF NOT EXISTS learn_resources (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(140) NOT NULL UNIQUE,
  kind         ENUM('article','video','infographic','quiz') NOT NULL DEFAULT 'article',
  category     VARCHAR(60) NOT NULL,         -- e.g. 'pollution', 'asthma', 'children', 'mask', 'first-aid'
  language     VARCHAR(8)  NOT NULL DEFAULT 'en',
  title        VARCHAR(220) NOT NULL,
  summary      TEXT NULL,                    -- short description (1-3 sentences)
  body         MEDIUMTEXT NULL,              -- full content (markdown / HTML allowed for articles)
  media_url    VARCHAR(500) NULL,            -- YouTube embed URL or external image
  thumbnail    VARCHAR(500) NULL,            -- preview thumbnail
  duration_min INT NULL,                     -- only for videos
  reading_min  INT NULL,                     -- only for articles
  level        ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  views        INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_learn_kind     (kind),
  KEY idx_learn_category (category),
  KEY idx_learn_lang     (language),
  KEY idx_learn_pub      (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a curated set of starter resources so the page is never empty
INSERT IGNORE INTO learn_resources
  (slug, kind, category, language, title, summary, body, media_url, thumbnail, duration_min, reading_min, level)
VALUES
  ('what-is-pm25', 'article', 'pollution', 'en',
   'What is PM2.5 and why does it matter?',
   'Fine particles smaller than 2.5 microns penetrate deep into the lungs and bloodstream. Learn how they affect your health and what you can do.',
   '## What is PM2.5?\n\nPM2.5 refers to airborne particles with a diameter of 2.5 micrometers or less — about 1/30th the width of a human hair. They come from combustion (vehicles, industry, biomass burning) and chemical reactions in the atmosphere.\n\n## Why are they dangerous?\n- They bypass the nose''s filtering system\n- They reach deep into the alveoli of the lungs\n- They can enter the bloodstream and affect the heart and brain\n\n## In Gabès\nThe Groupe Chimique Tunisien (GCT) and surrounding industry are major contributors. Wind from the south-east often carries plumes toward residential zones.\n\n## What you can do\n1. Check the daily air quality before going out.\n2. Keep windows closed during pollution peaks.\n3. Wear an FFP2 mask if you must go outside on critical days.\n4. Use an air purifier with a HEPA filter at home if possible.',
   NULL, NULL, NULL, 4, 'beginner'),

  ('asthma-prevention-gabes', 'article', 'asthma', 'en',
   'Asthma in Gabès: practical prevention tips',
   'Asthma rates are higher in industrial regions. Concrete steps to reduce attacks and protect your family.',
   '## Why Gabès residents are at higher risk\nLong-term exposure to SO2, NO2, and fine particles inflames the airways and increases asthma severity.\n\n## Daily routine\n- Track air quality before leaving home\n- Carry your reliever inhaler at all times\n- Avoid outdoor exercise on critical days\n- Keep a 7-day diary of symptoms and inhaler use\n\n## Warning signs requiring a doctor\n- Wheezing that doesn''t respond to inhaler\n- Difficulty speaking in full sentences\n- Bluish lips or fingertips\n- Resting heart rate above 120 bpm\n\n## Use the Nafass platform\nReport symptoms in the app. The system correlates spikes with pollution data and can request a telemedicine consultation in one click.',
   NULL, NULL, NULL, 5, 'beginner'),

  ('how-to-wear-ffp2', 'video', 'mask', 'en',
   'How to wear an FFP2 mask correctly',
   'A 2-minute demo: fit, seal, and reuse rules for FFP2 respirators.',
   NULL,
   'https://www.youtube.com/embed/lrvFrH_npQI',
   NULL, 2, NULL, 'beginner'),

  ('children-pollution-protection', 'article', 'children', 'en',
   'Protecting children from air pollution',
   'Children breathe faster than adults and absorb more pollutants per kilo of body weight. Specific guidance for parents and schools.',
   '## Why kids are more vulnerable\n- Higher breathing rate per kg\n- Lungs still developing until ~18\n- Spend more time outdoors\n\n## At school\n- Indoor recess on critical days\n- Air quality monitor in the classroom\n- Teach children the visual air quality cues\n\n## At home\n- No smoking indoors (passive exposure)\n- Cooking with hood ventilation\n- Avoid scented candles / aerosols\n\n## When to keep them home\nSchool Mode (built-in to Nafass) automatically suspends activities at critical thresholds. Trust the system and follow the alerts.',
   NULL, NULL, NULL, 6, 'beginner'),

  ('first-aid-respiratory-distress', 'article', 'first-aid', 'en',
   'First aid for respiratory distress',
   'What to do when someone struggles to breathe — before the ambulance arrives.',
   '## Recognise the emergency\n- Gasping, blue lips, confusion → call 190 (Tunisian SAMU) immediately\n- Mild shortness of breath → seat the person upright, loosen clothing\n\n## Steps\n1. Sit them upright (do not lay them down)\n2. Open windows for fresh air, or move to a less-polluted indoor room with closed windows\n3. If they have a prescribed inhaler — help them use it\n4. Stay calm and reassuring; panic worsens breathing\n5. If unconscious → check breathing, start CPR if needed\n\n## Do not\n- Force them to drink\n- Give unprescribed medication\n- Leave them alone',
   NULL, NULL, NULL, 4, 'beginner'),

  ('pollution-quiz-1', 'quiz', 'pollution', 'en',
   'Test your knowledge: air pollution basics',
   'A 5-question quiz to check what you know about pollution in Gabès.',
   '[{"q":"Which pollutant is most associated with the Gabès phosphate industry?","options":["CO2","SO2","CFC","Methane"],"answer":1},{"q":"PM2.5 stands for…","options":["Particulate matter < 2.5 mm","Particulate matter < 2.5 micrometers","Pollution mass 2.5 kg","Phosphate mineral 2.5"],"answer":1},{"q":"Which mask is most effective for fine particles?","options":["Cloth mask","Surgical mask","FFP2 / N95","Bandana"],"answer":2},{"q":"On a critical day, you should…","options":["Go for a long run outside","Open all windows","Stay indoors with windows closed","Drive with windows down"],"answer":2},{"q":"Children are more vulnerable to pollution because…","options":["They eat more sugar","They breathe faster relative to weight","They sleep more","They drink less water"],"answer":1}]',
   NULL, NULL, NULL, NULL, 'beginner'),

  ('what-is-pm25-ar', 'article', 'pollution', 'ar',
   'ما هو PM2.5 ولماذا يهمّ؟',
   'الجسيمات الدقيقة الأصغر من 2.5 ميكرون تخترق عميقاً في الرئتين ومجرى الدم. تعرّف على آثارها الصحية وكيفية الوقاية.',
   '## ما هو PM2.5؟\n\nيشير PM2.5 إلى الجسيمات المحمولة جواً التي يقلّ قطرها عن 2.5 ميكرومتر — أي حوالي 1/30 من سمك شعرة الإنسان. وتأتي من الاحتراق (السيارات، الصناعة، حرق الكتلة الحيوية).\n\n## لماذا هي خطيرة؟\n- تتجاوز نظام الترشيح في الأنف\n- تصل إلى عمق الحويصلات الرئوية\n- يمكنها دخول مجرى الدم وتؤثر على القلب والدماغ\n\n## في قابس\nالمجمع الكيميائي التونسي والصناعات المحيطة من أكبر المساهمين. غالباً ما تحمل رياح الجنوب الشرقي السحب نحو المناطق السكنية.\n\n## ما يمكنك فعله\n1. تحقّق من جودة الهواء يومياً قبل الخروج.\n2. أبقِ النوافذ مغلقة خلال ذروة التلوّث.\n3. ارتدِ كمامة FFP2 إذا اضطررت للخروج في الأيام الحرجة.\n4. استخدم منقّي هواء بفلتر HEPA في المنزل عند الإمكان.',
   NULL, NULL, NULL, 4, 'beginner');
