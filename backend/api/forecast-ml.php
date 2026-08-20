<?php
declare(strict_types=1);

/**
 * Nafass — API ML/XAI réelle.
 *
 * Cette route ne fabrique aucune métrique, aucune courbe ROC et aucun modèle.
 * Elle lit uniquement :
 *   - model_performance : métriques réellement écrites par train_all.py ;
 *   - model_predictions : prédictions réelles du jeu de test ;
 *   - xai_artifacts : TreeSHAP/LIME/DeepSHAP/PDP/Permutation réellement stockés.
 *
 * Si l'entraînement ou les artefacts manquent, la réponse est explicitement
 * data_status=empty et toutes les collections restent vides.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/forecast_ml.php';

$me = auth_user();
if (!$me || !in_array($me['role'] ?? '', ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_required'], 403);
}

function ml_api_table_exists(PDO $pdo, string $table): bool
{
    return function_exists('ml_table_exists') && ml_table_exists($pdo, $table);
}

function ml_api_json(string $payload): ?array
{
    $value = json_decode($payload, true);
    return is_array($value) ? $value : null;
}

function ml_api_roc(array $actual, array $predicted, string $name, string $color): ?array
{
    $n = min(count($actual), count($predicted));
    if ($n < 20) return null;
    $actual = array_map('floatval', array_slice($actual, 0, $n));
    $predicted = array_map('floatval', array_slice($predicted, 0, $n));
    $out = [];
    foreach ([50, 100, 150] as $threshold) {
        $positives = 0; $negatives = 0;
        foreach ($actual as $v) {
            if ($v >= $threshold) $positives++; else $negatives++;
        }
        if ($positives === 0 || $negatives === 0) continue;
        $points = [];
        $scores = $predicted;
        sort($scores, SORT_NUMERIC);
        $cuts = array_values(array_unique(array_merge([$scores[0] - 1e-9], $scores, [$scores[count($scores) - 1] + 1e-9])));
        foreach ($cuts as $cut) {
            $tp = 0; $fp = 0;
            foreach ($predicted as $i => $score) {
                $guess = $score >= $cut;
                $real = $actual[$i] >= $threshold;
                if ($guess && $real) $tp++;
                if ($guess && !$real) $fp++;
            }
            $points[] = ['x' => round($fp / $negatives, 4), 'y' => round($tp / $positives, 4)];
        }
        usort($points, static function (array $a, array $b): int { return $a['x'] <=> $b['x']; });
        $auc = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $auc += ($points[$i]['x'] - $points[$i - 1]['x']) * ($points[$i]['y'] + $points[$i - 1]['y']) / 2;
        }
        $out[] = [
            'name' => 'Classe AQI ≥ ' . $threshold . ' · score ' . $name,
            'label' => 'Seuil AQI ≥ ' . $threshold,
            'auc' => round(max(0, min(1, $auc)), 3),
            'fpr' => array_column($points, 'x'),
            'tpr' => array_column($points, 'y'),
            'threshold' => $threshold,
            'color' => [50 => '#0d3b66', 100 => '#2f6fb3', 150 => '#16a34a'][$threshold] ?? $color,
            'derived_from' => 'actual_aqi threshold + predicted_aqi score',
        ];
    }
    return $out ?: null;
}

function ml_api_recommendations(array $global, array $lime): array
{
    if (!$global) return [];
    $top = (string)($global[0]['feature'] ?? 'variable inconnue');
    $out = [[
        'title' => 'Surveiller ' . $top,
        'rationale' => 'Cette variable présente la plus forte importance dans l’artefact XAI réellement calculé.',
        'action' => 'Renforcer la surveillance de cette variable dans les prochaines mesures.',
        'impact' => 'Décision fondée sur l’explication du modèle réellement entraîné.',
        'priority' => 'haute',
        'zone' => '',
    ]];
    foreach ($lime as $item) {
        if ((float)($item['weight'] ?? 0) <= 0) continue;
        $feature = (string)($item['feature'] ?? 'variable');
        $out[] = [
            'title' => 'Contrôler ' . $feature,
            'rationale' => 'L’explication locale réelle indique une contribution positive à la prédiction de l’AQI.',
            'action' => 'Vérifier les mesures et les sources d’émission associées avant toute décision.',
            'impact' => 'Réduire l’incertitude opérationnelle sans inventer de valeur.',
            'priority' => 'moyenne',
            'zone' => '',
        ];
        if (count($out) >= 4) break;
    }
    return $out;
}

$empty = static function (string $status, string $message, int $http = 200): void {
    json_response([
        'ok' => $http < 400,
        'data_status' => $status,
        'message' => $message,
        'models' => [],
        'roc' => ['classes' => [], 'macro' => null, 'mode' => 'unavailable'],
        'shap' => ['global' => [], 'local' => [], 'deep' => [], 'beeswarm' => [], 'base_value' => null, 'predicted' => null],
        'pdp' => [], 'permutation' => [], 'lime' => [],
        'xai_method' => null, 'recommendations' => [],
        'ai_reco' => ['source' => 'none', 'recommendations' => []],
        'comparison' => null, 'optuna_best' => [],
        'cv' => ['f1_mean' => null, 'f1_std' => null, 'rmse_mean' => null, 'rmse_std' => null, 'folds' => 0],
        'data_source' => ['name' => 'Open-Meteo/CAMS + ERA5', 'table' => 'open_data', 'synthetic' => false],
    ], $http);
};

try {
    $pdo = db();
    if (!ml_api_table_exists($pdo, 'model_performance')) {
        $empty('empty', 'La table des performances réelles n’existe pas. Lancez les migrations puis l’entraînement Python.');
    }

    $horizon = (string)($_GET['horizon'] ?? '1h');
    if (!in_array($horizon, ['1h', '6h', '24h'], true)) $horizon = '1h';
    // Cette page est strictement ML : les modèles profonds sont servis par deep-learning.php.
    $allowedModels = ['Random Forest', 'XGBoost + Fuzzy'];
    $marks = implode(',', array_fill(0, count($allowedModels), '?'));
    $activeZoneSql = "'1','2','3','4'";
    $st = $pdo->prepare(
        "SELECT model_name, AVG(accuracy) acc, AVG(precision_macro) prec,
                AVG(recall_macro) rec, AVG(f1_macro) f1, AVG(mae) mae,
                AVG(rmse) rmse, AVG(mape) mape, AVG(smape) smape,
                AVG(r_squared) r2, AVG(auc_roc) auc, AVG(avg_latency_ms) latency,
                COUNT(*) folds
         FROM model_performance
         WHERE city_id IN ({$activeZoneSql})
           AND horizon = ? AND model_name IN ({$marks})
         GROUP BY model_name ORDER BY AVG(rmse) ASC"
    );
    $st->execute(array_merge([$horizon], $allowedModels));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $empty('empty', 'Aucun résultat réel n’est disponible pour l’horizon ' . $horizon . '. Exécutez python -m models.train_all.');
    }

    $models = [];
    foreach ($rows as $row) {
        $models[] = [
            'model' => (string)$row['model_name'],
            'acc' => round((float)$row['acc'], 3), 'prec' => round((float)$row['prec'], 3),
            'rec' => round((float)$row['rec'], 3), 'f1' => round((float)$row['f1'], 3),
            'mae' => round((float)$row['mae'], 3), 'rmse' => round((float)$row['rmse'], 3),
            'mape' => round((float)$row['mape'], 3), 'smape' => round((float)$row['smape'], 3),
            'r2' => round((float)$row['r2'], 3), 'auc' => $row['auc'] === null ? null : round((float)$row['auc'], 3),
            'latency' => round((float)$row['latency'], 2), 'folds' => (int)$row['folds'],
        ];
    }

    $selection = [
        'rule' => 'validation_only_unavailable',
        'model' => null,
        'validation_rmse' => null,
        'test_is_report_only' => true,
    ];
    $bestModel = null;
    if (ml_api_table_exists($pdo, 'model_validation_performance')) {
        $vst = $pdo->prepare(
            "SELECT model_name, AVG(rmse) validation_rmse, AVG(r_squared) validation_r2,
                    COUNT(*) folds
             FROM model_validation_performance
             WHERE city_id IN ({$activeZoneSql}) AND horizon = ?
               AND model_name IN ({$marks})
             GROUP BY model_name ORDER BY AVG(rmse) ASC"
        );
        $vst->execute(array_merge([$horizon], $allowedModels));
        $vrows = $vst->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vrows as $vrow) {
            $candidate = (string)$vrow['model_name'];
            $known = false;
            foreach ($models as $modelRow) {
                if ($modelRow['model'] === $candidate) { $known = true; break; }
            }
            if (!$known) continue;
            $bestModel = $candidate;
            $selection = [
                'rule' => 'validation_only',
                'model' => $candidate,
                'validation_rmse' => $vrow['validation_rmse'] === null ? null : round((float)$vrow['validation_rmse'], 3),
                'validation_r2' => $vrow['validation_r2'] === null ? null : round((float)$vrow['validation_r2'], 3),
                'validation_folds' => (int)$vrow['folds'],
                'test_rmse' => null,
                'test_f1' => null,
                'test_auc' => null,
                'test_is_report_only' => true,
            ];
            break;
        }
    }
    if ($bestModel !== null) {
        foreach ($models as $modelRow) {
            if (($modelRow['model'] ?? null) !== $bestModel) continue;
            $selection['test_rmse'] = $modelRow['rmse'] ?? null;
            $selection['test_f1'] = $modelRow['f1'] ?? null;
            $selection['test_auc'] = $modelRow['auc'] ?? null;
            break;
        }
    }

    $cv = ['f1_mean' => null, 'f1_std' => null, 'rmse_mean' => null, 'rmse_std' => null, 'folds' => 0];
    $cvrow = null;
    if ($bestModel !== null) {
        $cvst = $pdo->prepare(
            "SELECT AVG(f1_macro) f1_mean, STDDEV_POP(f1_macro) f1_std,
                    AVG(rmse) rmse_mean, STDDEV_POP(rmse) rmse_std, COUNT(*) folds
             FROM model_performance
             WHERE city_id IN ({$activeZoneSql}) AND horizon = ? AND model_name = ?"
        );
        $cvst->execute([$horizon, $bestModel]);
        $cvrow = $cvst->fetch(PDO::FETCH_ASSOC);
    }
    if ($cvrow && $cvrow['folds'] !== null) {
        $cv = [
            'f1_mean' => round((float)$cvrow['f1_mean'], 3), 'f1_std' => round((float)$cvrow['f1_std'], 3),
            'rmse_mean' => round((float)$cvrow['rmse_mean'], 3), 'rmse_std' => round((float)$cvrow['rmse_std'], 3),
            'folds' => (int)$cvrow['folds'],
        ];
    }

    $shap = ['global' => [], 'local' => [], 'deep' => [], 'beeswarm' => [], 'base_value' => null, 'predicted' => null];
    $lime = []; $pdp = []; $permutation = []; $xaiMethod = null; $comparison = null;
    if (ml_api_table_exists($pdo, 'xai_artifacts')) {
        $xr = $pdo->query("SELECT payload FROM xai_artifacts WHERE artifact_key = 'pollutant_xai' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $xp = $xr ? ml_api_json((string)$xr['payload']) : null;
        if ($xp && !empty($xp['method']) && $xp['method'] !== 'none') {
            $shap['global'] = is_array($xp['shap_global'] ?? null) ? $xp['shap_global'] : [];
            $shap['local'] = is_array($xp['shap_local'] ?? null) ? $xp['shap_local'] : [];
            $shap['deep'] = is_array($xp['shap_deep'] ?? null) ? $xp['shap_deep'] : [];
            $shap['beeswarm'] = is_array($xp['beeswarm'] ?? null) ? $xp['beeswarm'] : [];
            $lime = is_array($xp['lime'] ?? null) ? $xp['lime'] : [];
            $pdp = is_array($xp['pdp'] ?? null) ? $xp['pdp'] : [];
            $permutation = is_array($xp['permutation'] ?? null) ? $xp['permutation'] : [];
            $shap['base_value'] = isset($xp['base_value']) ? (float)$xp['base_value'] : null;
            $shap['predicted'] = isset($xp['predicted']) ? (float)$xp['predicted'] : null;
            $xaiMethod = (string)$xp['method'];
            if ($shap['global'] && $shap['deep']) {
                $a = array_slice(array_map(static fn($x): string => (string)($x['feature'] ?? ''), $shap['global']), 0, 3);
                $b = array_slice(array_map(static fn($x): string => (string)($x['feature'] ?? ''), $shap['deep']), 0, 3);
                $overlap = count(array_intersect($a, $b));
                $comparison = [
                    'linear_top3' => $a, 'deep_top3' => $b,
                    'overlap' => $overlap, 'agreement_percent' => $a ? round($overlap / count($a) * 100) : 0,
                    'winner' => 'données XAI persistées',
                    'text' => 'Comparaison calculée à partir des artefacts XAI persistés, sans valeur de référence inventée.',
                ];
            }
        }
    }

    $rocClasses = [];
    if ($bestModel !== null && ml_api_table_exists($pdo, 'model_predictions')) {
        $pr = $pdo->prepare(
            "SELECT predicted_aqi, actual_aqi FROM model_predictions
             WHERE city_id IN ({$activeZoneSql})
               AND horizon = ? AND model_name = ?
               AND predicted_aqi IS NOT NULL AND actual_aqi IS NOT NULL
             ORDER BY timestamp ASC LIMIT 20000"
        );
        $pr->execute([$horizon, $bestModel]);
        $pairs = $pr->fetchAll(PDO::FETCH_ASSOC);
        $actual = []; $predicted = [];
        foreach ($pairs as $pair) { $actual[] = $pair['actual_aqi']; $predicted[] = $pair['predicted_aqi']; }
        $rocClasses = ml_api_roc($actual, $predicted, $bestModel, '#0d3b66') ?: [];
    }

    $recommendations = ml_api_recommendations($shap['global'], $lime);

    // Les hyperparamètres réellement persistés sont affichés comme configuration.
    // Une courbe Optuna n'est pas inventée si l'historique trial par trial n'est pas stocké.
    $optunaBest = [];
    if (ml_api_table_exists($pdo, 'model_hyperparameters')) {
        try {
            $hst = $pdo->prepare(
                "SELECT model_name, params, updated_at FROM model_hyperparameters
                 WHERE model_name IN ({$marks}) ORDER BY model_name"
            );
            $hst->execute($allowedModels);
            foreach ($hst->fetchAll(PDO::FETCH_ASSOC) as $hrow) {
                $parsed = ml_api_json((string)($hrow['params'] ?? ''));
                $optunaBest[] = [
                    'model' => (string)$hrow['model_name'],
                    'params' => $parsed ?: [],
                    'updated_at' => $hrow['updated_at'] ?? null,
                    'source' => 'model_hyperparameters',
                ];
            }
        } catch (Throwable $ignored) {
            $optunaBest = [];
        }
    }

    json_response([
        'ok' => true,
        'data_status' => 'real',
        'horizon' => $horizon,
        'message' => 'Résultats réels issus de la base et du pipeline d’entraînement.',
        'models' => $models,
        'selection' => $selection,
        'roc' => [
            'classes' => $rocClasses,
            'macro' => null,
            'mode' => $rocClasses ? 'aqi_threshold_diagnostic' : 'unavailable',
            'note' => $rocClasses
                ? 'AUC diagnostique calculée à partir de seuils AQI sur actual_aqi et du score predicted_aqi; elle ne remplace pas une ROC probabiliste multiclasses.'
                : 'Aucune paire réelle actual_aqi/predicted_aqi disponible pour cet horizon.',
        ],
        'shap' => $shap,
        'pdp' => $pdp,
        'permutation' => $permutation,
        'lime' => $lime,
        'xai_method' => $xaiMethod,
        'recommendations' => $recommendations,
        'ai_reco' => ['source' => $recommendations ? 'real_xai_rules' : 'none', 'recommendations' => $recommendations],
        'comparison' => $comparison,
        'optuna_best' => $optunaBest,
        'cv' => $cv,
        'data_source' => [
            'family' => 'ML',
            'models' => $allowedModels,
            'name' => 'Open-Meteo Air Quality (CAMS Europe) + ERA5',
            'table' => 'open_data', 'cities' => 4,
            'period' => '2024-01-01 → 2026-07-02', 'grain' => 'horaire',
            'protocol' => 'split chronologique 70/10/20', 'synthetic' => false,
        ],
        'references' => ['Lundberg & Lee (2017), NeurIPS', 'Ribeiro et al. (2016), KDD'],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'data_status' => 'error',
        'error' => 'ml_backend_error',
        'message' => 'Erreur backend ML : ' . $e->getMessage(),
        'models' => [], 'roc' => ['classes' => [], 'macro' => null, 'mode' => 'unavailable'],
        'shap' => ['global' => [], 'local' => [], 'deep' => [], 'beeswarm' => [], 'base_value' => null, 'predicted' => null],
        'pdp' => [], 'permutation' => [], 'lime' => [], 'recommendations' => [],
    ], 500);
}
