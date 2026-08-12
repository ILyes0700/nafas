<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 53 : agrégateur du Dashboard IA unifié.
 * Rassemble en UN seul appel JSON les résultats de tous les modules IA.
 * Accès admin/health uniquement. Dégradation gracieuse : chaque section est
 * isolée (table absente => section 'empty', jamais d'erreur 500).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$pdo = db();
$me  = function_exists('auth_user') ? auth_user() : null;
if (!$me || !in_array($me['role'] ?? '', ['admin', 'health'], true)) {
    json_response(['ok' => false, 'error' => 'forbidden'], 403);
}

/** Lit des lignes en toute sécurité ; renvoie [] si la table/colonne manque. */
function ai_safe(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
function ai_table_exists(PDO $pdo, string $t): bool
{
    try {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t)
        )->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

$sections = [];

/* ---- Vue d'ensemble : meilleur modèle / RMSE global / statut entraînement ---- */
$overview = ['best_model' => null, 'global_rmse' => null, 'trained' => false, 'versions' => 0];
$mv = ai_safe($pdo, "SELECT model_name, metrics, status, created_at FROM model_versions ORDER BY created_at DESC LIMIT 50");
if ($mv) {
    $overview['versions'] = count($mv);
    $overview['trained'] = true;
}
// Real training results live in model_performance (written by models/train_all.py).
// Read them first; keep forecast_metrics only as a legacy fallback.
$perf = ai_safe($pdo, "SELECT model_name AS model, ROUND(AVG(rmse),3) rmse, ROUND(AVG(f1_macro),3) f1 FROM model_performance WHERE horizon = '1h' GROUP BY model_name ORDER BY rmse ASC LIMIT 20");
if (!$perf) {
    $perf = ai_safe($pdo, "SELECT model, AVG(rmse) rmse, AVG(f1) f1 FROM forecast_metrics GROUP BY model ORDER BY rmse ASC LIMIT 20");
}
if ($perf) {
    $overview['best_model'] = $perf[0]['model'] ?? null;
    $overview['global_rmse'] = isset($perf[0]['rmse']) ? round((float)$perf[0]['rmse'], 3) : null;
    $overview['trained'] = true;
}
$sections['overview'] = $overview;

/* ---- Comparaison des modèles ---- */
$sections['model_comparison'] = $perf;

/* ---- Deep Learning (TFT / attention) ---- */
$sections['deep_learning'] = [
    'tft'      => ai_safe($pdo, "SELECT * FROM model_predictions WHERE model_name = 'tft' ORDER BY id DESC LIMIT 30"),
    'attention'=> ai_safe($pdo, "SELECT * FROM attention_heatmaps ORDER BY id DESC LIMIT 10"),
];

/* ---- XAI ---- */
$sections['xai'] = [
    'explanations'    => ai_safe($pdo, "SELECT * FROM xai_explanations ORDER BY id DESC LIMIT 20"),
    'interactions'    => ai_safe($pdo, "SELECT * FROM xai_interactions ORDER BY id DESC LIMIT 20"),
    'counterfactuals' => ai_safe($pdo, "SELECT * FROM counterfactual_explanations ORDER BY id DESC LIMIT 20"),
];

/* ---- Incertitude & calibration ---- */
$sections['uncertainty'] = [
    'conformal'   => ai_safe($pdo, "SELECT id, zone_id, conformal_lower, conformal_upper, coverage_target FROM model_predictions WHERE conformal_lower IS NOT NULL ORDER BY id DESC LIMIT 30"),
    'calibration' => ai_safe($pdo, "SELECT * FROM calibration_metrics ORDER BY id DESC LIMIT 20"),
];

/* ---- Anomalies & dérive ---- */
$sections['anomaly_drift'] = [
    'anomalies' => ai_safe($pdo, "SELECT * FROM anomaly_events ORDER BY id DESC LIMIT 20"),
    'drift'     => ai_safe($pdo, "SELECT * FROM drift_monitoring ORDER BY id DESC LIMIT 20"),
];

/* ---- Causalité ---- */
$sections['causality'] = ai_safe($pdo, "SELECT * FROM granger_causality ORDER BY id DESC LIMIT 20");

/* ---- Spatial (GNN) ---- */
$sections['spatial'] = ai_safe($pdo, "SELECT * FROM gnn_spatial_edges ORDER BY id DESC LIMIT 40");

/* ---- Ensemble & A/B ---- */
$sections['ensemble_ab'] = [
    'weights' => ai_safe($pdo, "SELECT * FROM ensemble_weights ORDER BY id DESC LIMIT 20"),
    'ab_runs' => ai_safe($pdo, "SELECT * FROM ab_test_runs ORDER BY id DESC LIMIT 20"),
];

/* ---- Registre des modèles ---- */
$sections['registry'] = $mv;

/* ------------------------------------------------------------------ *
 * Fallback BENCHMARK : si le pipeline n'est pas encore entraîné, on   *
 * peuple chaque section avec des valeurs de référence validées afin   *
 * que le Dashboard IA ne soit jamais vide / DEMO. Le frontend affiche *
 * alors une pastille « RÉF. » (et non « DEMO — non entraîné »).       *
 * ------------------------------------------------------------------ */
/* ZERO DEMO : plus AUCUNE valeur de reference inventee. Si le pipeline n'est
 * pas entraine, les sections restent VIDES et le frontend affiche honnetement
 * « Modele non entraine — aucun resultat reel ». */
$benchmark = empty($mv) && empty($perf); // true = pipeline non entraine (sections reelles vides)

/* ---- État des tables (pour les pastilles PASS/DEMO) ---- */
$tableStatus = [];
foreach ([
    'model_versions','forecast_metrics','model_predictions','attention_heatmaps',
    'xai_explanations','xai_interactions','counterfactual_explanations',
    'calibration_metrics','anomaly_events','drift_monitoring','granger_causality',
    'gnn_spatial_edges','ensemble_weights','ab_test_runs',
] as $t) {
    $tableStatus[$t] = ai_table_exists($pdo, $t);
}

json_response([
    'ok'           => true,
    'generated_at' => date('c'),
    'benchmark'    => $benchmark,
    'sections'     => $sections,
    'table_status' => $tableStatus,
]);
