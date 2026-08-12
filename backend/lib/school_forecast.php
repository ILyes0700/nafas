<?php
declare(strict_types=1);

/**
 * PART 35 — Mode école PRÉDICTIF.
 *
 * Utilise le forecast hybride déjà en base (`forecast_predictions` /
 * `forecast_metrics`) pour PROPOSER une bascule du mode école avant que la
 * pollution n'atteigne le seuil. C'est une SUGGESTION — jamais une bascule
 * automatique : la décision humaine du directeur est conservée (cohérent avec
 * le README).
 *
 * Dégrade proprement : si aucune prévision n'est disponible, renvoie un statut
 * 'normal' avec confidence 0 et une note explicative.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Applique les MÊMES seuils que compute_risk_score()/global_status() pour rester
 * cohérent avec le reste de l'app.
 *   < 40  -> normal
 *   40-69 -> vigilance
 *   >= 70 -> suspension
 */
function school_status_from_aqi(float $aqi): string
{
    if ($aqi >= 70) return 'suspension';
    if ($aqi >= 40) return 'vigilance';
    return 'normal';
}

/**
 * Retourne la meilleure prévision disponible pour une zone à l'horizon +6h/+12h.
 *
 * @return array{
 *   zone_id:int, forecast_aqi:?float, recommended_status:string,
 *   based_on_horizon:?string, confidence:float, note:string, source:string
 * }
 */
function predict_school_status(PDO $pdo, int $zoneId): array
{
    $out = [
        'zone_id'            => $zoneId,
        'forecast_aqi'       => null,
        'recommended_status' => 'normal',
        'based_on_horizon'   => null,
        'confidence'         => 0.0,
        'note'               => '',
        'source'             => 'none',
    ];

    $row = null;
    // 1) Essai sur forecast_predictions (horizon +6h en priorité, sinon +12h).
    foreach (['+6h', '6h', '+12h', '12h'] as $h) {
        try {
            $q = $pdo->prepare(
                "SELECT predicted_aqi, horizon, confidence_score
                 FROM forecast_predictions
                 WHERE (zone_id = ? OR city_id = ?) AND horizon = ?
                 ORDER BY id DESC LIMIT 1"
            );
            $q->execute([$zoneId, (string)$zoneId, $h]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $row = null; // schéma différent — on tente le fallback plus bas
        }
        if ($row) { $out['source'] = 'forecast_predictions'; break; }
    }

    // 2) Fallback : forecast_metrics (moyenne horizon court).
    if (!$row) {
        try {
            $q = $pdo->prepare(
                "SELECT predicted_aqi, horizon, confidence_score
                 FROM forecast_metrics
                 WHERE zone_id = ? ORDER BY id DESC LIMIT 1"
            );
            $q->execute([$zoneId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) $out['source'] = 'forecast_metrics';
        } catch (Throwable $e) {
            $row = null;
        }
    }

    // 3) Fallback ultime : pollution actuelle de la zone (pas de vraie prévision).
    if (!$row) {
        try {
            $z = $pdo->prepare('SELECT pollution_level FROM zones WHERE id = ?');
            $z->execute([$zoneId]);
            $pol = $z->fetchColumn();
            if ($pol !== false) {
                $aqi = (float)$pol;
                $out['forecast_aqi']       = $aqi;
                $out['recommended_status'] = school_status_from_aqi($aqi);
                $out['based_on_horizon']   = 'now';
                $out['confidence']         = 0.3;
                $out['source']             = 'current_pollution';
                $out['note']               = 'Aucune prévision ML disponible — suggestion basée sur le niveau actuel.';
                _school_log_prediction($pdo, $out);
                return $out;
            }
        } catch (Throwable $e) {
            // ignore
        }
        $out['note'] = 'Aucune donnée de prévision ni de pollution pour cette zone.';
        return $out;
    }

    $aqi = (float)($row['predicted_aqi'] ?? 0);
    $out['forecast_aqi']       = $aqi;
    $out['recommended_status'] = school_status_from_aqi($aqi);
    $out['based_on_horizon']   = (string)($row['horizon'] ?? '+6h');
    $out['confidence']         = max(0.0, min(1.0, (float)($row['confidence_score'] ?? 0.6)));
    $out['note'] = sprintf(
        'Suggestion basée sur la prévision %s (AQI ≈ %d). Décision finale au directeur.',
        $out['based_on_horizon'], (int)round($aqi)
    );

    _school_log_prediction($pdo, $out);
    return $out;
}

/** Journalise la suggestion dans school_predictions (best effort). */
function _school_log_prediction(PDO $pdo, array $p): void
{
    try {
        $pdo->prepare(
            'INSERT INTO school_predictions
             (zone_id, predicted_for, forecast_aqi, recommended_status, based_on_horizon, confidence, applied)
             VALUES (?, NOW() + INTERVAL 6 HOUR, ?, ?, ?, ?, 0)'
        )->execute([
            $p['zone_id'], $p['forecast_aqi'], $p['recommended_status'],
            $p['based_on_horizon'], $p['confidence'],
        ]);
    } catch (Throwable $e) {
        error_log('[school_forecast] log: ' . $e->getMessage());
    }
}
