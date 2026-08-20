<?php
/**
 * Endpoint Deep Learning — résultats réels uniquement.
 *
 * Les métriques sont lues dans :
 *   - model_validation_performance : validation 10 % ;
 *   - model_performance : test final 20 % ;
 *   - dl_artifacts : prédictions et attention réellement produites.
 *
 * Si l'entraînement n'a pas encore produit de lignes, l'API renvoie une liste
 * vide et un état explicite, sans inventer de métriques.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function dl_allowed_models() {
    // Cette page est strictement Deep Learning : RF/XGBoost restent dans forecast-ml.php.
    return [
        'LSTM', 'BiLSTM Simple', 'BiLSTM+MultiHead Attn',
        'BiLSTM+AE', 'CNN+AE'
    ];
}

function dl_artifact($key) {
    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT payload FROM dl_artifacts WHERE artifact_key = ?");
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['payload'])) return null;
        $j = json_decode($row['payload'], true);
        return is_array($j) ? $j : null;
    } catch (Throwable $e) {
        return null;
    }
}

function dl_metric_rows($table, $split) {
    // Les noms de table sont des constantes internes, jamais fournis par GET.
    try {
        $pdo = db();
        $allowed = dl_allowed_models();
        $marks = implode(',', array_fill(0, count($allowed), '?'));
        $activeZoneSql = "'1','2','3','4'";
        $sql = "SELECT model_name, city_id, horizon,
                       AVG(accuracy) AS acc,
                       AVG(precision_macro) AS precision_macro,
                       AVG(recall_macro) AS recall_macro,
                       AVG(f1_macro) AS f1,
                       AVG(mae) AS mae,
                       AVG(rmse) AS rmse,
                       AVG(mape) AS mape,
                       AVG(smape) AS smape,
                       AVG(r_squared) AS r2,
                       AVG(auc_roc) AS auc,
                       AVG(avg_latency_ms) AS latency
                FROM {$table}
                WHERE city_id IN ({$activeZoneSql})
                  AND horizon IN ('1h','6h','24h')
                  AND model_name IN ({$marks})
                GROUP BY model_name, city_id, horizon
                ORDER BY city_id, horizon, model_name";
        $st = $pdo->prepare($sql);
        $st->execute($allowed);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['split'] = $split;
            $r['city_id'] = (string)$r['city_id'];
            $r['horizon'] = (string)$r['horizon'];
            foreach (['acc','precision_macro','recall_macro','f1','mae','rmse','mape','smape','r2','auc','latency'] as $k) {
                $r[$k] = $r[$k] === null ? null : round((float)$r[$k], 4);
            }
        }
        unset($r);
        return $rows;
    } catch (Throwable $e) {
        // La table de validation n'existe pas avant le premier entraînement :
        // cela doit rester une liste vide, jamais une valeur fabriquée.
        return [];
    }
}

if (!empty($_GET['attention'])) {
    $att = dl_artifact('attention');
    if (!$att || empty($att['weights'])) {
        json_response([
            'ok' => false,
            'error' => 'not_trained',
            'message' => "Carte d'attention indisponible : entraînez réellement BiLSTM+Attention avec TensorFlow."
        ]);
    }
    json_response(['ok' => true] + $att);
}

$training = dl_metric_rows('model_training_performance', 'train');
$validation = dl_metric_rows('model_validation_performance', 'validation');
$test = dl_metric_rows('model_performance', 'test');
$models = array_merge($training, $validation, $test);
$predictions = dl_artifact('predictions') ?: [];
$dlAllowed = dl_allowed_models();
// The unified deployment can legitimately select an ML model. Such a forecast
// must not be displayed as a DL result; keep only genuine DL selections.
if (is_array($predictions)) {
    foreach ($predictions as &$prediction) {
        $prediction['horizons'] = array_values(array_filter(
            is_array($prediction['horizons'] ?? null) ? $prediction['horizons'] : [],
            static fn($h): bool => in_array((string)($h['model'] ?? ''), $dlAllowed, true)
        ));
    }
    unset($prediction);
    $predictions = array_values(array_filter($predictions, static fn($p): bool => !empty($p['horizons'])));
}
$series = dl_artifact('series') ?: ['labels' => [], 'actual' => [], 'predicted' => []];
if (!in_array((string)($series['model'] ?? ''), $dlAllowed, true)) {
    $series = ['labels' => [], 'actual' => [], 'predicted' => [], 'model' => null, 'reason' => 'best_validation_model_is_not_deep_learning'];
}
$attention = dl_artifact('attention');

json_response([
    'ok' => true,
    'trained' => !empty($models),
    'models' => $models,
    'predictions' => $predictions,
    'series' => $series,
    'attention' => $attention,
        'message' => empty($models)
        ? "Aucun résultat Deep Learning réel disponible pour les quatre zones actives. Lancez l'entraînement."
        : null,
    'data_source' => [
        'family' => 'DL',
        'models' => dl_allowed_models(),
        'table' => 'open_data',
        'synthetic' => false,
        'protocol' => '70% train / 10% validation / 20% test',
    ],
]);
