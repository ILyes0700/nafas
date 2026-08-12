<?php
declare(strict_types=1);

/**
 * A1 — Prévision pollution 24h par zone.
 *
 * HYBRID PIPELINE (since 2026-05-07):
 *
 *   1. PRIMARY  : the ensemble (AR(7) + multi-EWMA sigmoid) computed by
 *                 backend/lib/forecast_ml.php. The training script writes
 *                 its output to `forecast_predictions`; we just read it.
 *
 *   2. ON-DEMAND: if no recent prediction exists we train and predict
 *                 inline (works on small dev datasets too).
 *
 *   3. FALLBACK : the legacy EWMA + linear-trend estimator below — kept
 *                 because it always works, even with 3 data points.
 *
 * Both paths expose the SAME response shape so the frontend doesn't have
 * to know which strategy fired.
 */

require_once __DIR__ . '/../config/database.php';

function forecast_compute_zone(int $zoneId): array
{
    $pdo = db();

    /* ---------- Path 1 : prefer the hybrid ML/DL prediction ---------- */
    $mlPath = __DIR__ . '/forecast_ml.php';
    if (is_file($mlPath)) {
        require_once $mlPath;
        $cached = ml_load_cached_forecast($pdo, $zoneId);
        if ($cached !== null && isset($cached['horizons'][24])) {
            $cur = $pdo->prepare("SELECT pollution_level FROM zones WHERE id = ?");
            $cur->execute([$zoneId]);
            $current = (int)$cur->fetchColumn();
            $horizons = [];
            foreach ([6, 12, 24] as $h) {
                $horizons[] = [
                    'h'         => $h,
                    'predicted' => (int)($cached['horizons'][$h] ?? $current),
                    'level'     => $cached['levels'][$h] ?? _level_from_score((int)($cached['horizons'][$h] ?? $current)),
                ];
            }
            return [
                'zone_id'    => $zoneId,
                'method'     => $cached['method'],
                'confidence' => $cached['confidence'],
                'current'    => $current,
                'horizons'   => $horizons,
                'computed_at'=> $cached['computed_at'],
            ];
        }
        // No recent prediction → train on the fly (cheap, < 100ms / zone)
        $live = ml_forecast_zone($pdo, $zoneId, true);
        if (($live['ok'] ?? false) && isset($live['predictions'])) {
            $cur = $pdo->prepare("SELECT pollution_level FROM zones WHERE id = ?");
            $cur->execute([$zoneId]);
            $current = (int)$cur->fetchColumn();
            $horizons = [];
            foreach ([6, 12, 24] as $h) {
                $p = $live['predictions'][$h];
                $horizons[] = ['h' => $h, 'predicted' => $p['score'], 'level' => $p['level']];
            }
            return [
                'zone_id'    => $zoneId,
                'method'     => $live['predictions'][24]['method'],
                'confidence' => $live['predictions'][24]['confidence'],
                'current'    => $current,
                'horizons'   => $horizons,
                'metrics'    => $live['metrics'],
                'alpha'      => $live['alpha'],
            ];
        }
        // Fall through to legacy path
    }
    /* ---------- Path 2 : legacy EWMA + linear (fallback) ---------- */

    /* Historique sur 14 jours, pas trop fin (1 valeur agrégée par jour) */
    $stmt = $pdo->prepare(
        "SELECT DATE(computed_at) AS d, AVG(score) AS s
         FROM risk_scores
         WHERE zone_id = ? AND computed_at >= NOW() - INTERVAL 14 DAY
         GROUP BY DATE(computed_at) ORDER BY d ASC"
    );
    $stmt->execute([$zoneId]);
    $rows = $stmt->fetchAll();

    /* Valeur courante (fallback) */
    $cur = $pdo->prepare("SELECT pollution_level FROM zones WHERE id = ?");
    $cur->execute([$zoneId]);
    $current = (int)$cur->fetchColumn();

    if (count($rows) < 3) {
        return [
            'zone_id'    => $zoneId,
            'method'     => 'fallback-current',
            'current'    => $current,
            'horizons'   => [
                ['h' => 6,  'predicted' => $current, 'level' => _level_from_score($current)],
                ['h' => 12, 'predicted' => $current, 'level' => _level_from_score($current)],
                ['h' => 24, 'predicted' => $current, 'level' => _level_from_score($current)],
            ],
        ];
    }

    /* EWMA (alpha=0.4) */
    $alpha = 0.4;
    $ewma  = (float)$rows[0]['s'];
    foreach (array_slice($rows, 1) as $r) {
        $ewma = $alpha * (float)$r['s'] + (1 - $alpha) * $ewma;
    }

    /* Tendance linéaire (sur les 7 derniers points) */
    $window = array_slice($rows, -7);
    $n = count($window);
    $sumX = $sumY = $sumXY = $sumX2 = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $x = (float)$i;
        $y = (float)$window[$i]['s'];
        $sumX  += $x;
        $sumY  += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    $denom = ($n * $sumX2 - $sumX * $sumX);
    $slope = $denom != 0.0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0.0;

    /* Projeter l'EWMA selon la tendance, par tranche de 6h */
    $horizons = [];
    foreach ([6, 12, 24] as $h) {
        $steps = $h / 24.0;       // exprimé en "jours"
        $forecast = $ewma + $slope * $steps;
        $forecast = max(0, min(100, (int)round($forecast)));
        $horizons[] = [
            'h'         => $h,
            'predicted' => $forecast,
            'level'     => _level_from_score($forecast),
        ];
    }

    return [
        'zone_id'  => $zoneId,
        'method'   => 'ewma+linear',
        'current'  => $current,
        'ewma'     => round($ewma, 2),
        'slope'    => round($slope, 3),
        'horizons' => $horizons,
    ];
}

function _level_from_score(int $s): string
{
    if ($s >= 70) return 'critical';
    if ($s >= 40) return 'warning';
    return 'safe';
}

/**
 * Persiste les prévisions dans pollution_forecast (utilisé par cron).
 */
function forecast_persist_all(): int
{
    $pdo = db();
    $rows = $pdo->query("SELECT id FROM zones")->fetchAll();
    $count = 0;
    foreach ($rows as $z) {
        $f = forecast_compute_zone((int)$z['id']);
        $ins = $pdo->prepare(
            "INSERT INTO pollution_forecast (zone_id, horizon_hours, predicted_score, predicted_level)
             VALUES (?,?,?,?)"
        );
        foreach ($f['horizons'] as $h) {
            $ins->execute([
                (int)$z['id'],
                (int)$h['h'],
                (int)$h['predicted'],
                (string)$h['level'],
            ]);
            $count++;
        }
    }
    return $count;
}
