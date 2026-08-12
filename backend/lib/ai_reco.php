<?php
declare(strict_types=1);

/**
 * UNIFIED AI RECOMMENDATION ENGINE.
 *
 * Combines THREE deterministic sources into ONE explainable recommendation
 * that every surface (dashboard card, chatbot, tips) reuses:
 *
 *   1. Fuzzy Type-1  (Mamdani)          → fuzzy_for_user()      [fuzzy_context.php]
 *   2. Fuzzy Type-2  (Interval, KM)     → fuzzy_type2_from_inputs() [fuzzy_type2.php]
 *   3. AI/ML model snapshot             → ai_model_snapshot()   [DB model_* tables]
 *
 * The final risk score is type-2 dominant (type-2 handles the extra
 * uncertainty of the environmental readings), corrected by the ML model when
 * a real prediction is available. The chatbot MUST call ai_reco_for_user()
 * and answer consistently with the returned assessment (never "random").
 */

require_once __DIR__ . '/fuzzy_context.php';   // fuzzy_for_user() (type-1)
require_once __DIR__ . '/fuzzy_type2.php';     // fuzzy_type2_from_inputs()

/**
 * Latest AI/ML model snapshot. Uses the trained tables when present
 * (model_predictions / model_performance), otherwise returns the validated
 * demo best-model so the pipeline still produces a grounded answer.
 */
function ai_model_snapshot(PDO $pdo): array {
    // 1) A real per-zone prediction (best case).
    try {
        $r = $pdo->query(
            "SELECT model_name, predicted_aqi, predicted_class, confidence_score,
                    trust_level, horizon
               FROM model_predictions
              ORDER BY timestamp DESC LIMIT 1"
        )->fetch();
        if ($r && $r['model_name']) {
            return [
                'trained'         => true,
                'source'          => 'model_predictions',
                'best_model'      => (string)$r['model_name'],
                'predicted_aqi'   => $r['predicted_aqi'] !== null ? round((float)$r['predicted_aqi'], 1) : null,
                'predicted_class' => $r['predicted_class'] ?: null,
                'confidence'      => $r['confidence_score'] !== null ? round((float)$r['confidence_score'], 2) : null,
                'trust_level'     => $r['trust_level'] ?: null,
                'horizon'         => $r['horizon'] ?: null,
            ];
        }
    } catch (Throwable $e) { /* table missing */ }

    // 2) A trained performance row (best model by F1).
    try {
        $r = $pdo->query(
            "SELECT model_name, f1_macro, r_squared, auc_roc
               FROM model_performance
              WHERE f1_macro IS NOT NULL
              ORDER BY f1_macro DESC LIMIT 1"
        )->fetch();
        if ($r && $r['model_name']) {
            return [
                'trained'    => true,
                'source'     => 'model_performance',
                'best_model' => (string)$r['model_name'],
                'f1'         => $r['f1_macro'] !== null ? round((float)$r['f1_macro'], 3) : null,
                'r2'         => $r['r_squared'] !== null ? round((float)$r['r_squared'], 3) : null,
                'auc'        => $r['auc_roc'] !== null ? round((float)$r['auc_roc'], 3) : null,
                'predicted_aqi' => null, 'predicted_class' => null,
                'confidence' => $r['auc_roc'] !== null ? round((float)$r['auc_roc'], 2) : null,
            ];
        }
    } catch (Throwable $e) { /* table missing */ }

    // 3) No trained model yet — HONEST empty snapshot (NO demo numbers).
    //    The recommendation stays fully intelligent through Fuzzy Type-1 +
    //    Type-2 (Karnik-Mendel); the ML layer simply reports "not trained yet"
    //    instead of inventing metrics. Real-results-only guarantee.
    return [
        'trained'         => false,
        'source'          => 'none',
        'best_model'      => null,
        'f1'              => null,
        'r2'              => null,
        'auc'             => null,
        'predicted_aqi'   => null,
        'predicted_class' => null,
        'confidence'      => null,
    ];
}

/**
 * Map a 0..100 score to the shared urgency vocabulary.
 */
function ai_reco_urgency(float $score): string {
    return $score < 30 ? 'low' : ($score < 55 ? 'moderate' : ($score < 80 ? 'high' : 'critical'));
}

/**
 * Build the unified recommendation for a user.
 *
 * @return array [
 *   'risk_score'    => float 0..100  (blended, type-2 dominant),
 *   'urgency_level' => low|moderate|high|critical,
 *   'actions'       => string[],
 *   'explanation'   => string,
 *   'type1'         => [...fuzzy Mamdani summary...],
 *   'type2'         => [...interval type-2 summary...],
 *   'model'         => [...AI model snapshot...],
 *   'inputs'        => [...crisp inputs...],
 *   'context'       => [...zone/flags/alerts...],
 * ]
 */
function ai_reco_for_user(PDO $pdo, int $userId, array $opts = []): array {
    // 1) Type-1 Mamdani (also produces the crisp inputs + actions).
    $t1 = fuzzy_for_user($pdo, $userId, $opts);
    // 2) Type-2 interval on the same inputs.
    $t2 = fuzzy_type2_from_inputs($t1['inputs']);
    // 3) AI/ML model snapshot.
    $model = ai_model_snapshot($pdo);

    // ---- Blend: type-2 dominant, nudged by the ML prediction if present.
    $blend = 0.35 * (float)$t1['risk_score'] + 0.65 * (float)$t2['score'];
    if ($model['predicted_aqi'] !== null) {
        // Convert AQI (~0..300) to a 0..100 pressure and nudge the score by 20%.
        $aqiPressure = max(0.0, min(100.0, (float)$model['predicted_aqi'] / 3.0));
        $blend = 0.8 * $blend + 0.2 * $aqiPressure;
    }
    $risk = round($blend, 1);
    $urgency = ai_reco_urgency($risk);

    // ---- Actions: reuse the Mamdani action plan, escalate if type-2 says worse.
    $actions = $t1['actions'] ?? [];
    $order = ['low' => 0, 'moderate' => 1, 'high' => 2, 'critical' => 3];
    if (($order[$t2['risk_level']] ?? 0) > ($order[$t1['urgency_level']] ?? 0)) {
        array_unshift($actions, 'Type-2 uncertainty analysis rates the situation as '
            . strtoupper($t2['risk_level']) . ' — take the stricter precautions below.');
    }

    $modelLabel = $model['best_model'] ?: 'no ML model trained yet';
    $explanation = sprintf(
        'AI decision: %s + Fuzzy Type-2 (Karnik-Mendel). '
        . 'Type-1 score %.0f, Type-2 score %.0f (±%.0f uncertainty band) → blended risk %.0f/100 (%s).',
        $modelLabel,
        (float)$t1['risk_score'], (float)$t2['score'], (float)$t2['uncertainty_band'],
        $risk, strtoupper($urgency)
    );

    return [
        'risk_score'    => $risk,
        'urgency_level' => $urgency,
        'actions'       => array_values($actions),
        'explanation'   => $explanation,
        'type1'         => [
            'risk_score'   => $t1['risk_score'],
            'urgency_level'=> $t1['urgency_level'],
            'fired_rules'  => array_slice($t1['fired_rules'] ?? [], 0, 3),
            'explanation'  => $t1['explanation'] ?? '',
        ],
        'type2'         => $t2,
        'model'         => $model,
        'inputs'        => $t1['inputs'],
        'context'       => $t1['context'] ?? [],
    ];
}

/**
 * Compact grounding block for the LLM system prompt so the chatbot answers
 * strictly from the model + fuzzy results (never random).
 */
function ai_reco_prompt_block(array $ai): string {
    $m = $ai['model'] ?? [];
    $t2 = $ai['type2'] ?? [];
    $modelLine = (($m['best_model'] ?? null) ?: 'no ML model trained yet')
        . (($m['f1'] ?? null) !== null ? sprintf(' (F1=%.3f)', (float)$m['f1']) : '')
        . (($m['predicted_aqi'] ?? null) !== null ? sprintf(', predicted AQI=%.0f', (float)$m['predicted_aqi']) : '')
        . (($m['trained'] ?? false) ? '' : ' [not trained yet]');
    return sprintf(
        "\n## AI-MODEL + FUZZY TYPE-2 ASSESSMENT (deterministic — answer MUST match this)\n"
        . "- AI model: %s\n"
        . "- Fuzzy Type-2 score: %.0f/100 (uncertainty band ±%.0f), level %s\n"
        . "- Blended risk score: %.0f/100 → urgency %s\n"
        . "You MUST base your advice on this assessment. Do NOT invent a different\n"
        . "risk level, and do NOT answer generically — use these numbers.\n",
        $modelLine,
        (float)($t2['score'] ?? 0), (float)($t2['uncertainty_band'] ?? 0),
        strtoupper((string)($t2['risk_level'] ?? 'n/a')),
        (float)($ai['risk_score'] ?? 0), strtoupper((string)($ai['urgency_level'] ?? 'n/a'))
    );
}
