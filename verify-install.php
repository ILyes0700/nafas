<?php
/**
 * verify-install.php - Verification de l'installation Nafass / Gabes-Tatenafas.
 *
 * v4.0 : migration vers les donnees reelles Open-Meteo.
 *   - Tous les controles lies au CGAN et a l'augmentation de donnees ont ete
 *     supprimes (gan.php, data_augment.php, scripts d'augmentation, poids GAN,
 *     table risk_scores_augmented).
 *   - Nouvelle section "Migration Open-Meteo" : open_data, zones.city_key,
 *     les 7 villes reelles, l'absence de fichiers CGAN residuels.
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT = __DIR__;
$rows = [];
$pass = 0; $fail = 0; $warn = 0;

function row(string $section, string $check, bool $ok, string $detail = '', bool $isWarn = false): void
{
    global $rows, $pass, $fail, $warn;
    $rows[] = ['section' => $section, 'check' => $check, 'ok' => $ok, 'detail' => $detail, 'warn' => $isWarn];
    if ($isWarn) $warn++;
    elseif ($ok) $pass++;
    else $fail++;
}

function file_contains(string $path, string $needle): bool
{
    if (!is_file($path)) return false;
    $content = (string)@file_get_contents($path);
    return strpos($content, $needle) !== false;
}

/* ──────────────────────────── 1. FILES ───────────────── */
$newFiles = [
    /* Phase 1 — API verification
       v4.0 : les 6 entrees liees au CGAN / a l'augmentation ont ete retirees
       (gan.php, data_augment.php, augment_data.php, train_gan.php,
        gan_generate.php, train_augment.py). Ces fichiers sont supprimes. */
    'backend/config/waqi.php'                => 'WAQI config',
    'backend/lib/waqi.php'                   => 'WAQI client',
    'backend/lib/api_verifier.php'           => 'Multi-source verifier (IQR + Z-score)',
    'backend/api/verify-data.php'            => 'Admin endpoint to trigger verification',
    /* Phase 2 — Fuzzy Mamdani engine */
    'backend/lib/fuzzy.php'                  => 'Mamdani fuzzy engine',
    'backend/lib/fuzzy_context.php'          => 'Shared fuzzy helper for ALL endpoints',
    'backend/config/fuzzy_rules.php'         => 'Fuzzy rule base (5 vars x 25 rules)',
    /* Phase 3 — Hybrid forecast */
    'backend/lib/forecast_ml.php'            => 'AR(7) + multi-EWMA + ensemble (source open_data)',
    'backend/api/forecast-metrics.php'       => 'Forecast metrics endpoint',
    'scripts/predict.php'                    => 'CLI: hybrid forecast prediction',
    'scripts/train_forecast.py'              => 'Optional Python: XGBoost+LSTM',
    'scripts/requirements.txt'               => 'Python dependencies',
    /* Frontend — admin Forecast page */
    'frontend/pages/forecast.html'           => 'Forecast admin HTML',
    'frontend/scripts/pages/forecast.js'     => 'Forecast admin JS',
    'frontend/styles/forecast.css'           => 'Forecast admin CSS',
    /* DB + docs */
    'db/migrations/2026-05-07-fuzzy-augment-hybrid.sql' => 'SQL migration (NEW TABLES)',
    'MODIFICATIONS-2026.md'                  => 'Full modifications documentation',
];

foreach ($newFiles as $rel => $label) {
    $full = $ROOT . DIRECTORY_SEPARATOR . $rel;
    $ok = is_file($full);
    row('Files added', $rel, $ok, $ok ? "✓ $label (".filesize($full)." bytes)" : "MISSING — copy from .zip");
}

/* ──────────────────────────── 2. PATCHED FILES ───────────── */
$patches = [
    ['backend/lib/iqair.php',                  "api_verifier.php",   'iqair.php now calls verify_zone() (multi-source)'],
    ['backend/api/recommendations.php',        "fuzzy_recommend",    'Recommendations call fuzzy_recommend() FIRST'],
    ['backend/api/dashboard.php',              "fuzzy_for_user",     'Dashboard endpoint includes fuzzy block'],
    ['backend/api/diary-ai.php',               "fuzzy_for_user",     'Diary AI is fuzzy-aware'],
    ['backend/api/triage.php',                 "fuzzy_for_user",     'Symptom triage is fuzzy-aware'],
    ['backend/api/tips.php',                   "fuzzy_for_user",     'Daily tips are fuzzy-aware'],
    ['backend/api/weekly-summary.php',         "fuzzy_for_user",     'Weekly summary is fuzzy-aware'],
    ['backend/api/chatbot.php',                "fuzzy_for_user",     'Chatbot system prompt is fuzzy-aware'],
    ['backend/config/groq.php',                "FUZZY-LOGIC RISK",   'Groq system prompt embeds fuzzy block'],
    ['backend/lib/forecast.php',               "forecast_ml",        'Forecast uses AR(7) + EWMA ensemble'],
    ['backend/lib/auth.php',                   "'forecast'",         'Forecast route allowed for admin/health'],
    ['frontend/index.php',                     "forecast.css",       'Forecast nav + CSS included'],
    ['frontend/scripts/router.js',             "forecast",           'Router knows the forecast route'],
    ['frontend/scripts/pages/dashboard.js',    "renderFuzzyDetails", 'Dashboard renders fuzzy panel'],
    ['frontend/scripts/pages/diary.js',        "Fuzzy Mamdani",      'Diary page renders fuzzy panel'],
    ['frontend/scripts/pages/symptoms.js',     "Fuzzy",              'Symptoms triage shows fuzzy badge'],
    ['frontend/styles/dashboard.css',          "dash-reco-fuzzy",    'CSS for fuzzy panel'],
    ['db/schema.sql',                          "fuzzy_reco_logs",    'Schema includes fuzzy_reco_logs table'],
];
foreach ($patches as [$rel, $needle, $label]) {
    $full = $ROOT . DIRECTORY_SEPARATOR . $rel;
    $ok = file_contains($full, $needle);
    row('Files patched', $rel, $ok, $ok ? "✓ $label" : "PATCH MISSING — re-extract the .zip over your project");
}

/* ──────────────────────────── 3. DB ──────────────────── */
$dbFile = $ROOT . '/backend/config/database.php';
$pdo = null; $dbName = '?';
if (is_file($dbFile)) {
    try {
        require_once $dbFile;
        if (function_exists('db')) $pdo = db();
        if ($pdo) {
            $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
            row('Database', 'Connection', true, "✓ Connected to `$dbName`");
        }
    } catch (Throwable $e) {
        row('Database', 'Connection', false, 'PDO error: ' . $e->getMessage());
    }
} else {
    row('Database', 'database.php', false, 'backend/config/database.php is missing');
}

/* Tables created by the 2026-05-07 migration.
   v4.0 : `risk_scores_augmented` a ete retiree — elle est droppee par
   migration_open_data.sql. Sa presence serait desormais une ANOMALIE. */
$tables = [
    'fuzzy_reco_logs'       => 'fuzzy decision audit log (point 1)',
    'api_verification_log'  => 'multi-source verification log (point 2)',
    'waqi_cache'            => 'WAQI API cache (point 2)',
    'forecast_predictions'  => 'hybrid forecast outputs (point 3)',
    'forecast_metrics'      => 'MAE/RMSE/MAPE/R2/SMAPE (point 3)',
];
if ($pdo) {
    foreach ($tables as $t => $why) {
        try {
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                                        WHERE TABLE_SCHEMA = DATABASE()
                                          AND TABLE_NAME = ".$pdo->quote($t))->fetchColumn() > 0;
            row('DB tables', $t, $exists,
                $exists
                    ? "✓ $why"
                    : "MISSING — run: mysql -u root $dbName < db/migrations/2026-05-07-fuzzy-augment-hybrid.sql");
        } catch (Throwable $e) {
            row('DB tables', $t, false, $e->getMessage());
        }
    }
}

/* ============================================================== *
 * MIGRATION OPEN-METEO v4.0 (2026-08-10) — NOUVELLE SECTION
 * ============================================================== */

/* 3.1 — La table open_data existe-t-elle et est-elle remplie ? */
if ($pdo) {
    try {
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = DATABASE()
                                      AND TABLE_NAME = 'open_data'")->fetchColumn() > 0;
        if (!$exists) {
            row('Open-Meteo', 'Table open_data', false,
                'MISSING — execute migration_open_data.sql dans phpMyAdmin');
        } else {
            $n = (int)$pdo->query('SELECT COUNT(*) FROM open_data')->fetchColumn();
            $ok = $n > 100000;
            $r = $pdo->query('SELECT MIN(time) a, MAX(time) b FROM open_data')->fetch();
            row('Open-Meteo', 'Table open_data', $ok,
                $ok ? sprintf('✓ %s lignes reelles | %s -> %s',
                              number_format($n, 0, '.', ' '), $r['a'], $r['b'])
                    : "Seulement $n lignes — l'import CSV est incomplet "
                      . '(attendu ~134 000). Verifie LOAD DATA LOCAL INFILE.');
        }
    } catch (Throwable $e) {
        row('Open-Meteo', 'Table open_data', false, $e->getMessage());
    }
}

/* 3.2 — Les 2 tables CGAN ont-elles bien ete supprimees ? */
if ($pdo) {
    foreach (['api_readings_augmented', 'risk_scores_augmented'] as $t) {
        try {
            $still = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                                       WHERE TABLE_SCHEMA = DATABASE()
                                         AND TABLE_NAME = ".$pdo->quote($t))->fetchColumn() > 0;
            row('Open-Meteo', "Table $t supprimee", !$still,
                $still ? "ENCORE PRESENTE — execute: DROP TABLE IF EXISTS $t;"
                       : '✓ correctement droppee');
        } catch (Throwable $e) {
            row('Open-Meteo', "Table $t supprimee", false, $e->getMessage());
        }
    }
}

/* 3.3 — zones.city_key et les 7 villes reelles */
if ($pdo) {
    try {
        $col = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE()
                                   AND TABLE_NAME = 'zones'
                                   AND COLUMN_NAME = 'city_key'")->fetchColumn() > 0;
        row('Open-Meteo', 'Colonne zones.city_key', $col,
            $col ? '✓ presente' : 'MISSING — ALTER TABLE zones ADD COLUMN city_key VARCHAR(50) UNIQUE AFTER name;');

        if ($col) {
            $expected = ['Gabes_ville','Ghannouche','Chott_Salem','Teboulbou',
                         'Chenini','El_Bled','Matmata'];
            $have = $pdo->query('SELECT city_key FROM zones WHERE city_key IS NOT NULL')
                        ->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($expected, $have);
            row('Open-Meteo', 'Les 7 villes reelles', empty($missing),
                empty($missing) ? '✓ ' . implode(', ', $expected)
                                : 'MANQUANTES: ' . implode(', ', $missing));

            /* Chaque zone doit trouver ses lignes dans open_data. Un city_key
               mal orthographie (casse !) ne leve aucune erreur SQL mais rend
               la jointure vide — c'est le piege le plus frequent. */
            $orphans = $pdo->query(
                'SELECT z.name FROM zones z
                  WHERE z.city_key IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM open_data o WHERE o.city = z.city_key)'
            )->fetchAll(PDO::FETCH_COLUMN);
            row('Open-Meteo', 'Jointure zones <-> open_data', empty($orphans),
                empty($orphans) ? '✓ les 7 zones sont jointes'
                                : 'Zones sans donnees: ' . implode(', ', $orphans)
                                  . ' (verifie la CASSE du city_key)');

            /* Anciennes villes qui ne doivent plus apparaitre */
            $old = $pdo->query(
                "SELECT name FROM zones WHERE name IN ('Bouchamma','Centre Ville')"
            )->fetchAll(PDO::FETCH_COLUMN);
            row('Open-Meteo', 'Anciennes zones renommees', empty($old),
                empty($old) ? '✓ plus de Bouchamma / Centre Ville'
                            : 'ENCORE PRESENTES: ' . implode(', ', $old));
        }
    } catch (Throwable $e) {
        row('Open-Meteo', 'zones.city_key', false, $e->getMessage());
    }
}

/* 3.4 — Integrite referentielle apres le remapping des zone_id */
if ($pdo) {
    $deps = ['alerts', 'users', 'reports', 'symptoms', 'school_status', 'risk_scores'];
    foreach ($deps as $t) {
        try {
            $orphan = (int)$pdo->query(
                "SELECT COUNT(*) FROM `$t` d
                  WHERE d.zone_id IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM zones z WHERE z.id = d.zone_id)"
            )->fetchColumn();
            row('Open-Meteo', "$t.zone_id sans orphelin", $orphan === 0,
                $orphan === 0 ? '✓ aucune ligne orpheline'
                              : "$orphan lignes pointent vers un zone_id inexistant");
        } catch (Throwable $e) {
            /* table absente sur certaines installations : simple avertissement */
            row('Open-Meteo', "$t.zone_id", true, 'table absente (ignore)', true);
        }
    }
}

/* 3.5 — Les 11 fichiers CGAN ont-ils bien ete supprimes du disque ? */
$cganFiles = [
    'models/cgan_trainer.py',
    'models/augment_db.py',
    'scripts/train_augment.py',
    'scripts/train_gan.php',
    'scripts/gan_generate.php',
    'scripts/augment_data.php',
    'backend/api/cgan.php',
    'backend/lib/gan.php',
    'backend/lib/data_augment.php',
    'frontend/scripts/pages/cgan.js',
    'storage/gan/weights.json',
];
foreach ($cganFiles as $rel) {
    $abs = $ROOT . '/' . $rel;
    $gone = !is_file($abs);
    row('Open-Meteo', "Supprime: $rel", $gone,
        $gone ? '✓ absent' : 'ENCORE PRESENT — supprime ce fichier');
}

/* 3.6 — Le nouveau modele BiLSTM+Autoencoder est-il en place ? */
row('Open-Meteo', 'models/bilstm_autoencoder.py',
    is_file($ROOT . '/models/bilstm_autoencoder.py'),
    is_file($ROOT . '/models/bilstm_autoencoder.py')
        ? '✓ nouveau modele present'
        : 'MISSING — nouveau modele BiLSTM+AE attendu');
row('Open-Meteo', 'train_all appelle BiLSTM+AE',
    file_contains($ROOT . '/models/train_all.py', 'BiLSTM+AE'),
    'Le modele doit apparaitre dans model_performance');

/* 3.7 — Plus aucune trace de CGAN dans le code source ? */
$noCgan = [
    'models/data_loader.py'          => 'load_api_augmented',
    'models/train_all.py'            => 'augmented',
    'backend/lib/forecast_ml.php'    => 'risk_scores_augmented',
    'models/ablation_study.py'       => 'CGAN',
];
foreach ($noCgan as $rel => $needle) {
    $abs = $ROOT . '/' . $rel;
    $clean = is_file($abs) ? !file_contains($abs, $needle) : true;
    row('Open-Meteo', "$rel sans '$needle'", $clean,
        $clean ? '✓ propre' : "CONTIENT ENCORE '$needle' — fichier non mis a jour");
}

/* ============================================================== *
 * UPGRADE v6 (2026-07-15) — Scientific ML upgrade checks
 * ============================================================== */
$v6Files = [
    'db/migrations/2026-07-15-upgrade-v6.sql'    => 'v6 SQL migration',
    'backend/lib/anomaly_correlation.php'        => 'Part 32 — anomaly x citizen-report correlation',
    'backend/lib/rule_calibration.php'           => 'Part 33 — rule auto-calibration',
    'backend/lib/school_forecast.php'            => 'Part 35 — predictive school mode',
    'backend/lib/data_quality_validator.php'     => 'Part 45 — upstream data validation',
    'backend/lib/rag_context.php'                => 'Part 47 — RAG context builder',
    'backend/api/school-forecast.php'            => 'Part 35 — school forecast endpoint',
    'backend/api/model-registry.php'             => 'Part 43/44 — registry & A/B endpoint',
    'backend/api/digital-twin.php'               => 'Part 48 — digital twin endpoint',
    'frontend/pages/model-registry.html'         => 'Part 43 — registry page',
    'frontend/pages/digital-twin.html'           => 'Part 48 — digital twin page',
    'frontend/scripts/pages/model-registry.js'   => 'Part 43 — registry JS',
    'frontend/scripts/pages/digital-twin.js'     => 'Part 48 — digital twin JS',
    'models/tft_forecaster.py'                   => 'Part 36 — TFT',
    'models/gnn_spatial.py'                      => 'Part 37 — GNN spatial',
    'models/pinn_dispersion.py'                  => 'Part 38 — PINN gaussian plume',
    'models/conformal_predictor.py'              => 'Part 39 — conformal prediction',
    'models/xai_counterfactual.py'               => 'Part 40 — counterfactual (DiCE)',
    'models/calibration_eval.py'                 => 'Part 42 — calibration / Brier',
    'models/model_registry_manager.py'           => 'Part 43 — model registry',
    'models/ab_testing_controller.py'            => 'Part 44 — A/B testing',
    'models/rl_ensemble_agent.py'                => 'Part 46 — RL ensemble (LinUCB)',
    'models/digital_twin.py'                     => 'Part 48 — digital twin sim',
];
foreach ($v6Files as $rel => $label) {
    $abs = $ROOT . '/' . $rel;
    row('v6 Files', $rel, is_file($abs), is_file($abs) ? "OK — $label" : "MISSING — $label");
}

/* v6 functions inside patched files */
$v6Fn = [
    ['backend/lib/groq_vision.php',            'classify_pollution_photo',  'Part 31 — photo pollution classifier'],
    ['backend/lib/notify.php',                 'should_send_notification',  'Part 34 — anti-fatigue throttle'],
    ['backend/lib/rag_context.php',            'build_rag_context',         'Part 47 — RAG context'],
    ['backend/lib/school_forecast.php',        'predict_school_status',     'Part 35 — school forecast'],
    ['backend/lib/anomaly_correlation.php',    'link_anomalies_to_reports', 'Part 32 — anomaly link'],
    ['backend/lib/rule_calibration.php',       'recalibrate_rules',         'Part 33 — rule calibration'],
    ['backend/lib/data_quality_validator.php', 'validate_reading',          'Part 45 — data validation'],
];
foreach ($v6Fn as [$file, $fn, $label]) {
    row('v6 Functions', "$fn()", file_contains($ROOT . '/' . $file, "function $fn"), $label);
}

/* v6 patched wiring */
row('v6 Wiring', 'chatbot uses RAG',              file_contains($ROOT.'/backend/api/chatbot.php', 'build_rag_context'), 'Part 47 — chatbot grounded on retrieved facts');
row('v6 Wiring', 'groq prompt ragBlock',          file_contains($ROOT.'/backend/config/groq.php', 'ragBlock'), 'Part 47 — RAG injected in system prompt');
row('v6 Wiring', 'reports classify photo',        file_contains($ROOT.'/backend/api/reports.php', 'photo_classifications'), 'Part 31 — photo classified on upload');
row('v6 Wiring', 'risk score photo signal',       file_contains($ROOT.'/backend/lib/helpers.php', 'photoBoost'), 'Part 31 — photo signal in risk score');
row('v6 Wiring', 'admin routes (registry/twin)',  file_contains($ROOT.'/backend/lib/auth.php', 'model-registry'), 'Part 43/48 — admin-only pages');
row('v6 Wiring', 'router v6 pages',               file_contains($ROOT.'/frontend/scripts/router.js', 'initDigitalTwin'), 'Part 43/48 — front routing');
row('v6 Wiring', 'train_all v6 hooks',            file_contains($ROOT.'/models/train_all.py', '_run_v6_hooks'), 'Parts 37/39/43/44/46 — training hooks');
row('v6 Wiring', 'school predictive encart',      file_contains($ROOT.'/frontend/pages/school.html', 'school-forecast-encart'), 'Part 35 — suggestion encart');

/* v6 tables */
$v6Tables = [
    'photo_classifications', 'anomaly_citizen_links', 'recommendation_rules',
    'recommendation_feedback', 'recommendations_log', 'notification_throttle',
    'school_predictions', 'gnn_spatial_edges', 'counterfactual_explanations',
    'xai_interactions', 'calibration_metrics', 'model_versions', 'ab_test_runs',
    'data_quality_checks', 'digital_twin_scenarios',
];
if ($pdo) {
    foreach ($v6Tables as $t) {
        try {
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                                        WHERE TABLE_SCHEMA = DATABASE()
                                          AND TABLE_NAME = ".$pdo->quote($t))->fetchColumn() > 0;
            row('v6 DB tables', $t, $exists,
                $exists ? 'OK' : "MISSING — run: mysql -u root $dbName < db/migrations/2026-07-15-upgrade-v6.sql");
        } catch (Throwable $e) {
            row('v6 DB tables', $t, false, $e->getMessage());
        }
    }
}

/* ==============================================================
 * UPGRADE v8 + v9 (2026-07-20 / 2026-07-25) — Intelligence & Carte
 * ============================================================== */
$v89Files = [
    'db/migrations/2026-07-20-upgrade-v8.sql'     => 'v8 SQL migration',
    'db/migrations/2026-07-25-upgrade-v9.sql'     => 'v9 SQL migration',
    'backend/lib/report_dedup.php'                => 'Part 49.1 — report deduplication',
    'backend/lib/report_nlp_classifier.php'       => 'Part 49.2 — NLP text classifier',
    'backend/lib/symptom_pattern_detector.php'    => 'Part 50.1 — personal pattern (Pearson/lag)',
    'backend/lib/symptom_forecast.php'            => 'Part 50.2 — personal symptom forecast',
    'backend/lib/chatbot_emergency_detector.php'  => 'Part 51.2/51.3 — emergency + language register',
    'backend/api/ai-dashboard-data.php'           => 'Part 53 — unified AI dashboard aggregator',
    'backend/api/map-layers.php'                  => 'Part 55 — map layers data',
    'frontend/pages/ai-dashboard.html'            => 'Part 53 — AI dashboard page',
    'frontend/scripts/pages/ai-dashboard.js'      => 'Part 53 — AI dashboard JS',
    'frontend/styles/ai-dashboard.css'            => 'Part 53 — AI dashboard CSS',
    'frontend/lib/timelapse_export.js'            => 'Part 54.3 — time-lapse GIF export',
];
foreach ($v89Files as $rel => $label) {
    $abs = $ROOT . '/' . $rel;
    row('v8/v9 Files', $rel, is_file($abs), is_file($abs) ? "OK — $label" : "MISSING — $label");
}

/* v8/v9 functions inside new/patched files */
$v89Fn = [
    ['backend/lib/report_dedup.php',               'find_duplicate_cluster',   'Part 49.1 — dedup'],
    ['backend/lib/report_nlp_classifier.php',      'classify_report_text',     'Part 49.2 — NLP classify'],
    ['backend/lib/symptom_pattern_detector.php',   'detect_personal_pattern',  'Part 50.1 — personal pattern'],
    ['backend/lib/symptom_forecast.php',           'personal_symptom_forecast','Part 50.2 — personal forecast'],
    ['backend/lib/chatbot_emergency_detector.php', 'detect_emergency_signal',  'Part 51.2 — emergency'],
    ['backend/lib/chatbot_emergency_detector.php', 'detect_language_register', 'Part 51.3 — language register'],
];
foreach ($v89Fn as [$file, $fn, $label]) {
    row('v8/v9 Functions', "$fn()", file_contains($ROOT . '/' . $file, "function $fn"), $label);
}

/* v8/v9 patched wiring */
row('v8/v9 Wiring', 'reports dedup+NLP',        file_contains($ROOT.'/backend/api/reports.php', 'find_duplicate_cluster'), 'Part 49 — dedup+classify on insert');
row('v8/v9 Wiring', 'symptoms triage',          file_contains($ROOT.'/backend/api/symptoms.php', 'suggest_telemed'), 'Part 50.3 — intelligent triage');
row('v8/v9 Wiring', 'chatbot memory',           file_contains($ROOT.'/backend/api/chatbot.php', 'chatbot_user_memory'), 'Part 51.1 — persistent memory');
row('v8/v9 Wiring', 'groq memory/lang block',   file_contains($ROOT.'/backend/config/groq.php', 'memoryBlock'), 'Part 51 — memory+lang in prompt');
row('v8/v9 Wiring', 'risk score trust weight',  file_contains($ROOT.'/backend/lib/helpers.php', 'trustFactor'), 'Part 49.3 — trust-weighted reports');
row('v8/v9 Wiring', 'ai-dashboard admin/health',file_contains($ROOT.'/backend/lib/auth.php', 'ai-dashboard'), 'Part 53 — restricted page');
row('v8/v9 Wiring', 'router ai-dashboard',      file_contains($ROOT.'/frontend/scripts/router.js', 'initAiDashboard'), 'Part 53 — front routing');
row('v8/v9 Wiring', 'timelapse granularity',    file_contains($ROOT.'/backend/api/timelapse.php', 'granularity'), 'Part 54.1 — hourly granularity');
row('v8/v9 Wiring', 'map v9 layers',            file_contains($ROOT.'/frontend/scripts/pages/map.js', 'map-layers.php'), 'Part 55 — new map layers');
row('v8/v9 Wiring', 'map speed control',        file_contains($ROOT.'/frontend/scripts/pages/map.js', 'playSpeed'), 'Part 54.2 — playback speed');
row('v8/v9 Wiring', 'index ai-dashboard.js',    file_contains($ROOT.'/frontend/index.php', 'pages/ai-dashboard.js'), 'Part 53 — script include');

/* v8/v9 tables */
$v89Tables = [
    'report_duplicate_clusters', 'trust_score_history', 'personal_patterns',
    'chatbot_user_memory', 'parent_child_alerts', 'schools', 'safe_points',
];
if ($pdo) {
    foreach ($v89Tables as $t) {
        try {
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                                        WHERE TABLE_SCHEMA = DATABASE()
                                          AND TABLE_NAME = ".$pdo->quote($t))->fetchColumn() > 0;
            row('v8/v9 DB tables', $t, $exists,
                $exists ? 'OK' : "MISSING — run the v8/v9 migrations in db/migrations/");
        } catch (Throwable $e) {
            row('v8/v9 DB tables', $t, false, $e->getMessage());
        }
    }
    try {
        $col = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE()
                                   AND TABLE_NAME = 'users' AND COLUMN_NAME = 'trust_score'")->fetchColumn() > 0;
        row('v8/v9 DB tables', 'users.trust_score', $col, $col ? 'OK' : 'MISSING — run v8 migration');
    } catch (Throwable $e) {
        row('v8/v9 DB tables', 'users.trust_score', false, $e->getMessage());
    }
}

/* ──────────────────────────── 4. RUNTIME ───────────────── */
$fz = null;
try {
    require_once $ROOT . '/backend/lib/fuzzy.php';
    if (function_exists('fuzzy_recommend')) {
        $fz = fuzzy_recommend([
            'pollution' => 70, 'vulnerability' => 7, 'symptom_sev' => 6,
            'alerts_24h' => 2, 'age' => 65,
        ]);
        $ok = isset($fz['risk_score']) && $fz['risk_score'] >= 50;
        row('Runtime', 'Fuzzy engine inference (test case)', $ok,
            $ok ? sprintf("✓ score=%.1f urgency=%s rules_fired=%d",
                          $fz['risk_score'], $fz['urgency_level'], count($fz['fired_rules']))
                : 'fuzzy_recommend() returned an unexpected value');
    } else {
        row('Runtime', 'fuzzy_recommend()', false, 'Function not defined — backend/lib/fuzzy.php missing or broken');
    }
} catch (Throwable $e) {
    row('Runtime', 'fuzzy_recommend()', false, $e->getMessage());
}

/* v4.0 — CORRECTION D'UN BUG PREEXISTANT.
   L'ancien code testait function_exists('forecast_hybrid'). Cette fonction
   n'a JAMAIS existe dans backend/lib/forecast_ml.php, qui expose en realite
   ml_forecast_zone(), ml_forecast_all_zones() et ml_load_cached_forecast().
   Ce controle etait donc rouge en permanence depuis l'origine. */
try {
    require_once $ROOT . '/backend/lib/forecast_ml.php';
    $ok = function_exists('ml_forecast_zone');
    row('Runtime', 'ml_forecast_zone()', $ok, $ok ? '✓ defined' : 'function missing');

    /* La source de donnees doit bien etre open_data et plus risk_scores_augmented. */
    $srcOk = file_contains($ROOT . '/backend/lib/forecast_ml.php', 'FROM open_data')
          && !file_contains($ROOT . '/backend/lib/forecast_ml.php', 'risk_scores_augmented');
    row('Runtime', 'forecast_ml lit open_data', $srcOk,
        $srcOk ? '✓ source migree, plus aucune lecture augmentee'
               : 'forecast_ml.php n\'a pas ete migre vers open_data');
} catch (Throwable $e) {
    row('Runtime', 'ml_forecast_zone()', false, $e->getMessage());
}

try {
    require_once $ROOT . '/backend/lib/api_verifier.php';
    $ok = function_exists('verify_zone');
    row('Runtime', 'verify_zone()', $ok, $ok ? '✓ defined' : 'function missing');
} catch (Throwable $e) {
    row('Runtime', 'verify_zone()', false, $e->getMessage());
}

try {
    require_once $ROOT . '/backend/lib/fuzzy_context.php';
    $ok = function_exists('fuzzy_for_user');
    row('Runtime', 'fuzzy_for_user() — universal helper', $ok, $ok ? '✓ defined' : 'function missing');
} catch (Throwable $e) {
    row('Runtime', 'fuzzy_for_user()', false, $e->getMessage());
}

/* v4.0 : les deux blocs runtime GAN ont ete SUPPRIMES.
   — gan_seed() / gan_train() / gan_sample() entrainaient un vrai GAN sur
     5 epochs a CHAQUE chargement de cette page. backend/lib/gan.php etant
     supprime, ce bloc aurait fait planter verify-install.php.
   — La verification de storage/gan/weights.json n'a plus d'objet.
   La verification que ces fichiers sont bien ABSENTS est faite plus haut,
   dans la section "Open-Meteo" (point 3.5). */

/* ──────────────────────────── 5. RENDER ────────────────── */
$bySection = [];
foreach ($rows as $r) $bySection[$r['section']][] = $r;

$total = $pass + $fail + $warn;
?>