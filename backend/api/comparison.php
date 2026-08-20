<?php
/**
 * Comparaison réelle des modèles autorisés.
 *
 * Aucun catalogue de référence, aucune valeur Demo et aucune série synthétique
 * ne sont générés. Les agrégats viennent uniquement de model_performance,
 * qui contient les mesures TEST réellement écrites par train_all.py.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me || !in_array($me['role'] ?? '', ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function cmp_allowed_models(): array {
    return [
        'Random Forest', 'XGBoost + Fuzzy', 'LSTM', 'BiLSTM Simple',
        'BiLSTM+MultiHead Attn', 'BiLSTM+AE', 'CNN+AE'
    ];
}

function cmp_empty(string $message): void {
    json_response([
        'ok' => true,
        'data_status' => 'empty',
        'message' => $message,
        'models' => [],
        'demo' => false,
        'master' => [],
        'horizonModels' => [],
        'horizons' => [],
        'ablation' => [],
        'significance' => [],
        'literature' => [],
        'literatureNote' => '',
        'optuna' => [],
        'radar' => ['axes' => [], 'models' => []],
        'series' => ['labels' => [], 'actual' => [], 'predicted' => [], 'lower' => [], 'upper' => []],
        'best' => null,
    ]);
}

try {
    $pdo = db();
    $allowed = cmp_allowed_models();
    $marks = implode(',', array_fill(0, count($allowed), '?'));
    $activeZoneSql = "'1','2','3','4'";

    $st = $pdo->prepare(
        "SELECT model_name,
                AVG(accuracy) acc, AVG(precision_macro) prec,
                AVG(recall_macro) rec, AVG(f1_macro) f1,
                AVG(mae) mae, AVG(rmse) rmse, AVG(mape) mape,
                AVG(smape) smape, AVG(r_squared) r2, AVG(auc_roc) auc,
                AVG(avg_latency_ms) latency, COUNT(*) zones
         FROM model_performance
         WHERE city_id IN ({$activeZoneSql})
           AND horizon = '1h' AND model_name IN ({$marks})
         GROUP BY model_name
         ORDER BY AVG(rmse) ASC"
    );
    $st->execute($allowed);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        cmp_empty("Aucun résultat réel n'est disponible pour les quatre zones actives. Lancez l'entraînement.");
    }

    $master = [];
    foreach ($rows as $r) {
        $master[] = [
            'model' => (string)$r['model_name'],
            'acc' => round((float)$r['acc'], 3),
            'prec' => round((float)$r['prec'], 3),
            'rec' => round((float)$r['rec'], 3),
            'f1' => round((float)$r['f1'], 3),
            'mae' => round((float)$r['mae'], 3),
            'rmse' => round((float)$r['rmse'], 3),
            'mape' => $r['mape'] === null ? null : round((float)$r['mape'], 3),
            'smape' => $r['smape'] === null ? null : round((float)$r['smape'], 3),
            'r2' => $r['r2'] === null ? null : round((float)$r['r2'], 3),
            'auc' => $r['auc'] === null ? null : round((float)$r['auc'], 3),
            'latency' => round((float)$r['latency'], 3),
            'zones' => (int)$r['zones'],
            'benchmark' => false,
        ];
    }

    $bestRmse = min(array_column($master, 'rmse'));
    foreach ($master as &$m) {
        $m['best'] = ((float)$m['rmse'] === (float)$bestRmse);
        $m['recommended'] = false;
    }
    unset($m);

    $hz = $pdo->prepare(
        "SELECT model_name, horizon, AVG(rmse) rmse, AVG(f1_macro) f1, AVG(auc_roc) auc
         FROM model_performance
         WHERE city_id IN ({$activeZoneSql})
           AND model_name IN ({$marks}) AND horizon IN ('1h','6h','24h')
         GROUP BY model_name, horizon
         ORDER BY horizon, AVG(rmse) ASC"
    );
    $hz->execute($allowed);
    $horizonData = [];
    $present = [];
    foreach ($hz->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $h = (string)$r['horizon'];
        $name = (string)$r['model_name'];
        $present[$name] = true;
        $horizonData[$h][$name] = [
            'rmse' => round((float)$r['rmse'], 3),
            'f1' => round((float)$r['f1'], 3),
            'auc' => $r['auc'] === null ? null : round((float)$r['auc'], 3),
        ];
    }
    $horizonModels = array_values(array_filter($allowed, static fn($name) => isset($present[$name])));

    // Classement réel du moins bon au meilleur selon le RMSE TEST à +1h.
    $ranked = $master;
    usort($ranked, static fn($a, $b) => $b['rmse'] <=> $a['rmse']);
    $ablation = [];
    $previousRmse = null;
    $previousF1 = null;
    foreach ($ranked as $m) {
        $row = ['config' => $m['model'], 'rmse' => $m['rmse'], 'f1' => $m['f1'], 'r2' => $m['r2'], 'auc' => $m['auc']];
        $row['delta_rmse'] = $previousRmse === null ? null : round(($previousRmse - $m['rmse']) / max(1e-9, $previousRmse) * 100, 2);
        $row['delta_f1'] = $previousF1 === null ? null : round(($m['f1'] - $previousF1) / max(1e-9, abs($previousF1)) * 100, 2);
        $ablation[] = $row;
        $previousRmse = $m['rmse'];
        $previousF1 = $m['f1'];
    }

    $significance = [];
    try {
        $sig = $pdo->prepare(
            "SELECT model_name, AVG(wilcoxon_pvalue) p
             FROM model_performance
             WHERE city_id IN ({$activeZoneSql})
               AND model_name IN ({$marks}) AND wilcoxon_pvalue IS NOT NULL
             GROUP BY model_name ORDER BY p ASC"
        );
        $sig->execute($allowed);
        foreach ($sig->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $p = (float)$r['p'];
            $significance[] = [
                'comparison' => (string)$r['model_name'],
                'wilcoxon_p' => round($p, 6),
                'stat' => 'p=' . round($p, 6),
                'significant' => $p < 0.05,
            ];
        }
    } catch (Throwable $e) {
        $significance = [];
    }

    $maxLatency = max(array_map(static fn($m) => (float)$m['latency'], $master)) ?: 1.0;
    $radarModels = [];
    foreach ($master as $m) {
        $speed = max(0, round(100 - ((float)$m['latency'] / $maxLatency) * 100, 1));
        $radarModels[] = [
            'name' => $m['model'],
            'values' => [
                round((float)$m['acc'], 1),
                round((float)$m['f1'] * 100, 1),
                round(max(0, (float)($m['r2'] ?? 0)) * 100, 1),
                $speed,
            ],
        ];
    }

    $best = $master[0] ?? null;
    $bestPayload = $best ? [
        'name' => $best['model'],
        'vs_baseline' => ['rmse' => null, 'f1' => null, 'auc' => null],
        'wilcoxon_p' => null,
        'components' => ['Résultat TEST réel', 'Sélection et classement indépendants des autres modèles', 'Données des quatre zones actives'],
    ] : null;

    json_response([
        'ok' => true,
        'data_status' => 'real',
        'message' => 'Comparaison réelle des modèles autorisés, sans Demo ni données synthétiques.',
        'demo' => false,
        'master' => $master,
        'horizonModels' => $horizonModels,
        'horizons' => $horizonData,
        'ablation' => $ablation,
        'significance' => $significance,
        'literature' => [],
        'literatureNote' => '',
        'optuna' => [],
        'radar' => ['axes' => ['Accuracy', 'F1', 'R²', 'Vitesse'], 'models' => $radarModels],
        'series' => ['labels' => [], 'actual' => [], 'predicted' => [], 'lower' => [], 'upper' => []],
        'best' => $bestPayload,
        'allowed_models' => $allowed,
        'protocol' => '70% train / 10% validation / 20% test',
        'data_source' => ['table' => 'open_data', 'zones' => 4, 'excluded_from_training' => ['Chenini', 'El_Bled', 'Matmata'], 'synthetic' => false],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'data_status' => 'error',
        'error' => 'comparison_backend_error',
        'message' => 'Erreur backend comparaison : ' . $e->getMessage(),
        'master' => [], 'horizonModels' => [], 'horizons' => [], 'ablation' => [],
        'significance' => [], 'literature' => [], 'optuna' => [],
        'radar' => ['axes' => [], 'models' => []],
        'series' => ['labels' => [], 'actual' => [], 'predicted' => [], 'lower' => [], 'upper' => []],
        'best' => null,
    ], 500);
}
