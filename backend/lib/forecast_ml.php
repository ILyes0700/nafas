<?php
declare(strict_types=1);

/**
 * Nafass — Bibliothèque partagée Forecast/ML.
 *
 * Ce fichier ne doit jamais produire de réponse HTTP et ne doit jamais appeler
 * json_response(). Il fournit uniquement des fonctions réutilisables.
 * Les résultats affichés doivent provenir des tables réelles écrites par le
 * pipeline Python ou des mesures réelles utilisées par les fallbacks explicites.
 */
require_once __DIR__ . '/helpers.php';

/** Retourne true si une table existe dans la base courante. */
function ml_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $st->execute([$table]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Ajustement OLS stable avec une très petite régularisation ridge. */
function ml_ols_fit(array $X, array $y): ?array
{
    $n = count($X);
    if ($n < 2 || count($y) !== $n || !isset($X[0]) || !is_array($X[0])) return null;
    $p = count($X[0]);
    if ($p < 1 || $n < $p) return null;

    $A = array_fill(0, $p, array_fill(0, $p, 0.0));
    $b = array_fill(0, $p, 0.0);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($X[$i]) || count($X[$i]) !== $p) return null;
        $yi = (float)$y[$i];
        for ($r = 0; $r < $p; $r++) {
            $xr = (float)$X[$i][$r];
            $b[$r] += $xr * $yi;
            for ($c = $r; $c < $p; $c++) {
                $A[$r][$c] += $xr * (float)$X[$i][$c];
            }
        }
    }
    for ($r = 0; $r < $p; $r++) {
        for ($c = 0; $c < $r; $c++) $A[$r][$c] = $A[$c][$r];
        $A[$r][$r] += 1e-8;
    }

    for ($col = 0; $col < $p; $col++) {
        $pivot = $col;
        $max = abs($A[$col][$col]);
        for ($r = $col + 1; $r < $p; $r++) {
            if (abs($A[$r][$col]) > $max) {
                $max = abs($A[$r][$col]);
                $pivot = $r;
            }
        }
        if ($max < 1e-12) return null;
        if ($pivot !== $col) {
            [$A[$pivot], $A[$col]] = [$A[$col], $A[$pivot]];
            [$b[$pivot], $b[$col]] = [$b[$col], $b[$pivot]];
        }
        $diag = $A[$col][$col];
        for ($r = $col + 1; $r < $p; $r++) {
            $factor = $A[$r][$col] / $diag;
            if (abs($factor) < 1e-15) continue;
            for ($c = $col; $c < $p; $c++) {
                $A[$r][$c] -= $factor * $A[$col][$c];
            }
            $b[$r] -= $factor * $b[$col];
        }
    }

    $beta = array_fill(0, $p, 0.0);
    for ($r = $p - 1; $r >= 0; $r--) {
        $sum = $b[$r];
        for ($c = $r + 1; $c < $p; $c++) $sum -= $A[$r][$c] * $beta[$c];
        if (abs($A[$r][$r]) < 1e-12) return null;
        $beta[$r] = $sum / $A[$r][$r];
    }
    return $beta;
}

/** Lit la dernière série de prévisions réellement persistée pour une zone. */
function ml_load_cached_forecast(PDO $pdo, int $zoneId): ?array
{
    if ($zoneId <= 0 || !ml_table_exists($pdo, 'forecast_predictions')) return null;
    try {
        $sql = "SELECT horizon_hours, predicted_score, predicted_level, method,
                       confidence, computed_at
                FROM forecast_predictions
                WHERE zone_id = ?
                  AND computed_at = (
                    SELECT MAX(computed_at) FROM forecast_predictions WHERE zone_id = ?
                  )
                ORDER BY horizon_hours ASC";
        $st = $pdo->prepare($sql);
        $st->execute([$zoneId, $zoneId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return null;

        $horizons = [];
        $levels = [];
        $confidence = null;
        $method = null;
        $computedAt = null;
        foreach ($rows as $row) {
            $h = (int)$row['horizon_hours'];
            if (!in_array($h, [6, 12, 24], true)) continue;
            $horizons[$h] = (int)$row['predicted_score'];
            $levels[$h] = (string)$row['predicted_level'];
            if ($row['confidence'] !== null) $confidence = (float)$row['confidence'];
            $method = (string)$row['method'];
            $computedAt = $row['computed_at'];
        }
        if (!$horizons) return null;
        return [
            'horizons' => $horizons,
            'levels' => $levels,
            'confidence' => $confidence,
            'method' => $method ?: 'persisted_real_forecast',
            'computed_at' => $computedAt,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Retourne uniquement une prédiction déjà entraînée/persistée.
 * Aucun entraînement PHP et aucune valeur de démonstration ne sont générés.
 */
function ml_forecast_zone(PDO $pdo, int $zoneId, bool $allowCompute = false): array
{
    $cached = ml_load_cached_forecast($pdo, $zoneId);
    if ($cached === null) {
        return ['ok' => false, 'error' => 'not_trained', 'message' => 'Aucune prévision réelle persistée pour cette zone.'];
    }
    return ['ok' => true, 'predictions' => $cached['horizons'], 'forecast' => $cached];
}

/**
 * Compatibilité avec les anciennes routes : un endpoint HTTP ne doit pas
 * lancer un entraînement caché. Le réentraînement se fait avec Python.
 */
function ml_forecast_all_zones(PDO $pdo): array
{
    return [
        'ok' => false,
        'error' => 'python_training_required',
        'message' => 'Lancez le pipeline réel avec : python -m models.train_all',
    ];
}
