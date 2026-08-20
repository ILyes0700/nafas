<?php
declare(strict_types=1);

/**
 * Nafass — مقاييس Forecast الحقيقية.
 *
 * لا يعيد هذا endpoint تدريباً داخل PHP ولا ينشئ أرقاماً تجريبية. التدريب يتم
 * من خلال Python ثم تُقرأ النتائج من forecast_metrics أو model_performance.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/forecast_ml.php';

$me = auth_user();
if (!$me || !in_array($me['role'] ?? '', ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_required'], 403);
}

try {
    $pdo = db();
    if (!empty($_GET['train'])) {
        json_response([
            'ok' => false,
            'data_status' => 'not_trained',
            'error' => 'python_training_required',
            'message' => 'Le réentraînement réel se lance avec : python -m models.train_all',
        ], 409);
    }

    $zone = isset($_GET['zone_id']) ? max(0, (int)$_GET['zone_id']) : 0;
    $allowedZoneIds = ['1', '2', '3', '4'];
    if ($zone > 0 && !in_array((string)$zone, $allowedZoneIds, true)) $zone = -1;
    $allowedZoneSql = "'1','2','3','4'";
    $horizon = (string)($_GET['horizon'] ?? '');
    if ($horizon !== '' && !in_array($horizon, ['1h', '6h', '24h'], true)) $horizon = '';

    $rows = [];
    if (ml_table_exists($pdo, 'model_performance')) {
        $where = [];
        $params = [];
        $where[] = "city_id IN ($allowedZoneSql)";
        if ($zone !== 0) { $where[] = 'city_id = ?'; $params[] = (string)$zone; }
        if ($horizon !== '') { $where[] = 'horizon = ?'; $params[] = $horizon; }
        $sql = "SELECT model_name, city_id AS zone_id, horizon, mae, rmse, mape,
                       r_squared AS r2, smape, NULL AS sample_size,
                       evaluated_at AS trained_at
                FROM model_performance";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY evaluated_at DESC LIMIT 500';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$rows && ml_table_exists($pdo, 'forecast_metrics')) {
        $where = [];
        $params = [];
        $where[] = 'zone_id IN (1,2,3,4)';
        if ($zone !== 0) { $where[] = 'zone_id = ?'; $params[] = $zone; }
        $sql = 'SELECT model_name, zone_id, NULL AS horizon, mae, rmse, mape, r2, smape, sample_size, trained_at FROM forecast_metrics';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY trained_at DESC LIMIT 500';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$rows) {
        json_response([
            'ok' => true,
            'data_status' => 'empty',
            'message' => 'Aucune métrique réelle disponible. Exécutez le pipeline Python puis rechargez la page.',
            'summary' => [], 'rows' => [], 'horizon' => $horizon ?: null,
        ]);
    }

    $byModel = [];
    foreach ($rows as $row) {
        $name = (string)($row['model_name'] ?? '');
        if ($name === '') continue;
        if (!isset($byModel[$name])) {
            $byModel[$name] = ['model' => $name, 'n' => 0, 'mae' => 0.0, 'rmse' => 0.0, 'mape' => 0.0, 'r2' => 0.0, 'smape' => 0.0, 'latest' => $row['trained_at'] ?? null];
        }
        $byModel[$name]['n']++;
        foreach (['mae', 'rmse', 'mape', 'r2', 'smape'] as $field) {
            $byModel[$name][$field] += (float)($row[$field] ?? 0);
        }
    }
    $summary = [];
    foreach ($byModel as $item) {
        $n = max(1, $item['n']);
        $summary[] = [
            'model' => $item['model'], 'mae' => round($item['mae'] / $n, 3),
            'rmse' => round($item['rmse'] / $n, 3), 'mape' => round($item['mape'] / $n, 3),
            'r2' => round($item['r2'] / $n, 3), 'smape' => round($item['smape'] / $n, 3),
            'n_runs' => $item['n'], 'latest' => $item['latest'],
        ];
    }
    usort($summary, static fn(array $a, array $b): int => $a['rmse'] <=> $b['rmse']);

    json_response([
        'ok' => true, 'data_status' => 'real', 'horizon' => $horizon ?: null,
        'message' => 'Métriques réellement persistées par le pipeline.',
        'summary' => $summary, 'rows' => $rows,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'data_status' => 'error', 'error' => 'metrics_backend_error', 'message' => $e->getMessage()], 500);
}
