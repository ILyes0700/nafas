<?php
declare(strict_types=1);

/**
 * PART 33 — Auto-calibration des règles de recommandation sur le feedback.
 *
 * Ferme la boucle : les règles jugées peu utiles (`recommendation_feedback`)
 * voient leur priorité rétrogradée automatiquement (urgent -> advisory ->
 * info). Calcul simple, pas de ML lourd :
 *     success_rate = times_useful / times_shown
 *
 * Dégrade proprement si les tables sont absentes.
 */

require_once __DIR__ . '/../config/database.php';

const RULE_CALIB_MIN_SHOWN   = 20;   // seuil minimal d'affichages avant rétrogradation
const RULE_CALIB_FAIL_RATE   = 0.30; // success_rate < 0.30 => rétrograder

/**
 * Enregistre qu'une règle a été affichée (à appeler quand on montre la reco).
 */
function rule_mark_shown(PDO $pdo, string $ruleKey): void
{
    try {
        $pdo->prepare(
            'UPDATE recommendation_rules SET times_shown = times_shown + 1 WHERE rule_key = ?'
        )->execute([$ruleKey]);
    } catch (Throwable $e) {
        error_log('[rule_calibration] mark_shown: ' . $e->getMessage());
    }
}

/**
 * Enregistre un retour utilisateur (was_useful) + met à jour les compteurs.
 */
function rule_record_feedback(PDO $pdo, string $ruleKey, bool $wasUseful, ?int $userId = null, ?int $zoneId = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO recommendation_feedback (rule_key, user_id, zone_id, was_useful)
             VALUES (?,?,?,?)'
        )->execute([$ruleKey, $userId, $zoneId, $wasUseful ? 1 : 0]);
        if ($wasUseful) {
            $pdo->prepare(
                'UPDATE recommendation_rules SET times_useful = times_useful + 1 WHERE rule_key = ?'
            )->execute([$ruleKey]);
        }
    } catch (Throwable $e) {
        error_log('[rule_calibration] record_feedback: ' . $e->getMessage());
    }
}

/**
 * Recalcule success_rate pour chaque règle et rétrograde celles qui échouent.
 *
 * @return array{updated:int, downgraded:int, rules:array} rapport pour l'UI admin
 */
function recalibrate_rules(PDO $pdo): array
{
    $report = ['updated' => 0, 'downgraded' => 0, 'rules' => []];
    $order  = ['urgent' => 2, 'advisory' => 1, 'info' => 0];
    $lower  = ['urgent' => 'advisory', 'advisory' => 'info', 'info' => 'info'];

    try {
        $rules = $pdo->query('SELECT * FROM recommendation_rules')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[rule_calibration] recalibrate: ' . $e->getMessage());
        return $report + ['error' => 'recommendation_rules indisponible'];
    }

    foreach ($rules as $r) {
        $shown  = (int)($r['times_shown'] ?? 0);
        $useful = (int)($r['times_useful'] ?? 0);
        $rate   = $shown > 0 ? round($useful / $shown, 3) : null;
        $prio   = $r['priority'] ?? 'advisory';
        $newPrio = $prio;

        if ($rate !== null && $shown >= RULE_CALIB_MIN_SHOWN && $rate < RULE_CALIB_FAIL_RATE) {
            $newPrio = $lower[$prio] ?? $prio;
        }

        try {
            $pdo->prepare(
                'UPDATE recommendation_rules SET success_rate = ?, priority = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$rate, $newPrio, (int)$r['id']]);
            $report['updated']++;
            if ($newPrio !== $prio) $report['downgraded']++;
            $report['rules'][] = [
                'rule_key'     => $r['rule_key'] ?? null,
                'success_rate' => $rate,
                'from'         => $prio,
                'to'           => $newPrio,
                'times_shown'  => $shown,
            ];
        } catch (Throwable $e) {
            error_log('[rule_calibration] update rule ' . ($r['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }

    return $report;
}
