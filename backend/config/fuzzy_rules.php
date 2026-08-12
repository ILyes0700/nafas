<?php
/**
 * Fuzzy Logic — Input / Output variable definitions + Rule base.
 *
 * This file configures the Mamdani inference engine (backend/lib/fuzzy.php).
 * Each variable is described by a set of membership functions (trapezoidal
 * or triangular), and the rule base maps antecedent combinations to
 * consequent fuzzy sets.
 *
 * Naming convention:
 *   ['trap', a, b, c, d]  → trapezoidal (full membership between b and c)
 *   ['tri',  a, b, c]     → triangular  (peak at b)
 *
 * Membership function shapes have been calibrated for Gabès health context:
 *   - pollution thresholds align with the existing risk score (40=warning, 70=critical)
 *   - vulnerability index is a weighted sum: asthma×2 + heart×2 + allergy×1
 *                                            + pregnant×2 + child×2 + elderly×1.5
 */
declare(strict_types=1);

/* =====================================================================
 *  INPUT VARIABLES
 * ===================================================================== */
const FUZZY_INPUT_VARS = [

    /* pollution_level : 0..100 (matches zones.pollution_level) */
    'pollution' => [
        'LOW'      => ['trap', -10,  0,  20, 40],   // μ=1 for 0–20, ramp to 0 at 40
        'MODERATE' => ['tri',   20, 45, 65],         // peak at 45
        'HIGH'     => ['tri',   50, 70, 85],         // peak at 70
        'EXTREME'  => ['trap',  75, 90, 100, 110],   // μ=1 above 90
    ],

    /* vulnerability_index : 0..10 (computed from fragile_profiles flags)
       Formula: asthma×2 + heart_disease×2 + allergy×1 + pregnant×2 + child×2 + elderly×1.5 */
    'vulnerability' => [
        'NONE'   => ['trap', -1,   0,  0.5, 2],
        'LOW'    => ['tri',   1,   3,  5],
        'MEDIUM' => ['tri',   3.5, 5.5, 7.5],
        'HIGH'   => ['trap',  6,   8,  10, 11],
    ],

    /* symptom_severity : 0..10 (sum-weighted: mild=1, moderate=2, severe=4 per symptom
       reported in the last 24h, capped at 10) */
    'symptom_sev' => [
        'NONE'     => ['trap', -1, 0,  0.5, 1.5],
        'MILD'     => ['tri',   1, 3,  5],
        'MODERATE' => ['tri',   4, 6,  8],
        'SEVERE'   => ['trap',  7, 8, 10, 11],
    ],

    /* alerts_24h : 0..N  (number of active alerts on the user's zone in the last 24h) */
    'alerts_24h' => [
        'QUIET'  => ['trap', -1,  0, 0.5, 2],
        'NORMAL' => ['tri',   1,  3,  5],
        'BUSY'   => ['trap',  4,  6, 20, 25],
    ],

    /* age : 0..120 (optional, defaults to 30 if unknown) */
    'age' => [
        'YOUNG'  => ['trap', -1,  0, 10, 18],        // children / adolescents
        'ADULT'  => ['trap', 16, 25, 55, 65],
        'SENIOR' => ['trap', 60, 70, 120, 130],
    ],
];

/* =====================================================================
 *  OUTPUT VARIABLE
 * ===================================================================== */
const FUZZY_OUTPUT_VARS = [

    /* risk  (defuzzified output) : 0..100 */
    'risk' => [
        'SAFE'     => ['trap', -10,  0,  15, 30],
        'LOW'      => ['tri',   20,  35, 50],
        'MODERATE' => ['tri',   40,  55, 70],
        'HIGH'     => ['tri',   60,  75, 85],
        'CRITICAL' => ['trap',  75,  85, 100, 110],
    ],
];

/* =====================================================================
 *  RULE BASE  (Mamdani style — min-AND connective, max-OR aggregation)
 *
 *  Each rule is:
 *    ['id' => N, 'if' => [var=>SET, ...], 'then' => [out=>SET], 'label' => '...']
 *
 *  25 rules cover the main combinations. Pruning strategy: we omit
 *  rules whose firing would always be negligible (e.g. EXTREME pollution
 *  + NONE vulnerability + QUIET alerts is unrealistic in Gabès).
 * ===================================================================== */
const FUZZY_RULES = [

    // ── Pollution LOW ──────────────────────────────────────────────────
    ['id' =>  1, 'label' => 'Clean air, no vulnerability',
     'if' => ['pollution'=>'LOW', 'vulnerability'=>'NONE'],
     'then' => ['risk'=>'SAFE']],

    ['id' =>  2, 'label' => 'Clean air, mild vulnerability',
     'if' => ['pollution'=>'LOW', 'vulnerability'=>'LOW'],
     'then' => ['risk'=>'SAFE']],

    ['id' =>  3, 'label' => 'Clean air, medium vulnerability',
     'if' => ['pollution'=>'LOW', 'vulnerability'=>'MEDIUM'],
     'then' => ['risk'=>'LOW']],

    ['id' =>  4, 'label' => 'Clean air, high vulnerability (still precautionary)',
     'if' => ['pollution'=>'LOW', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'MODERATE']],

    // ── Pollution MODERATE ─────────────────────────────────────────────
    ['id' =>  5, 'label' => 'Moderate pollution, healthy individual',
     'if' => ['pollution'=>'MODERATE', 'vulnerability'=>'NONE'],
     'then' => ['risk'=>'LOW']],

    ['id' =>  6, 'label' => 'Moderate pollution, low vulnerability',
     'if' => ['pollution'=>'MODERATE', 'vulnerability'=>'LOW'],
     'then' => ['risk'=>'LOW']],

    ['id' =>  7, 'label' => 'Moderate pollution, medium vulnerability',
     'if' => ['pollution'=>'MODERATE', 'vulnerability'=>'MEDIUM'],
     'then' => ['risk'=>'MODERATE']],

    ['id' =>  8, 'label' => 'Moderate pollution, high vulnerability',
     'if' => ['pollution'=>'MODERATE', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'HIGH']],

    // ── Pollution HIGH ─────────────────────────────────────────────────
    ['id' =>  9, 'label' => 'High pollution, healthy individual',
     'if' => ['pollution'=>'HIGH', 'vulnerability'=>'NONE'],
     'then' => ['risk'=>'MODERATE']],

    ['id' => 10, 'label' => 'High pollution, low vulnerability',
     'if' => ['pollution'=>'HIGH', 'vulnerability'=>'LOW'],
     'then' => ['risk'=>'MODERATE']],

    ['id' => 11, 'label' => 'High pollution, medium vulnerability',
     'if' => ['pollution'=>'HIGH', 'vulnerability'=>'MEDIUM'],
     'then' => ['risk'=>'HIGH']],

    ['id' => 12, 'label' => 'High pollution, high vulnerability',
     'if' => ['pollution'=>'HIGH', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'CRITICAL']],

    // ── Pollution EXTREME ──────────────────────────────────────────────
    ['id' => 13, 'label' => 'Extreme pollution, healthy individual',
     'if' => ['pollution'=>'EXTREME', 'vulnerability'=>'NONE'],
     'then' => ['risk'=>'HIGH']],

    ['id' => 14, 'label' => 'Extreme pollution, any vulnerability',
     'if' => ['pollution'=>'EXTREME', 'vulnerability'=>'LOW'],
     'then' => ['risk'=>'HIGH']],

    ['id' => 15, 'label' => 'Extreme pollution, medium vulnerability',
     'if' => ['pollution'=>'EXTREME', 'vulnerability'=>'MEDIUM'],
     'then' => ['risk'=>'CRITICAL']],

    ['id' => 16, 'label' => 'Extreme pollution + high vulnerability → max risk',
     'if' => ['pollution'=>'EXTREME', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'CRITICAL']],

    // ── Symptom-driven rules ───────────────────────────────────────────
    ['id' => 17, 'label' => 'Severe symptoms override',
     'if' => ['symptom_sev'=>'SEVERE'],
     'then' => ['risk'=>'HIGH']],

    ['id' => 18, 'label' => 'Severe symptoms + pollution',
     'if' => ['symptom_sev'=>'SEVERE', 'pollution'=>'HIGH'],
     'then' => ['risk'=>'CRITICAL']],

    ['id' => 19, 'label' => 'Moderate symptoms + moderate pollution',
     'if' => ['symptom_sev'=>'MODERATE', 'pollution'=>'MODERATE'],
     'then' => ['risk'=>'MODERATE']],

    ['id' => 20, 'label' => 'Moderate symptoms + high vulnerability',
     'if' => ['symptom_sev'=>'MODERATE', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'HIGH']],

    // ── Alert-driven rules ─────────────────────────────────────────────
    ['id' => 21, 'label' => 'Many active alerts → heightened risk',
     'if' => ['alerts_24h'=>'BUSY'],
     'then' => ['risk'=>'MODERATE']],

    ['id' => 22, 'label' => 'Many alerts + high pollution',
     'if' => ['alerts_24h'=>'BUSY', 'pollution'=>'HIGH'],
     'then' => ['risk'=>'HIGH']],

    ['id' => 23, 'label' => 'Many alerts + vulnerable',
     'if' => ['alerts_24h'=>'BUSY', 'vulnerability'=>'HIGH'],
     'then' => ['risk'=>'CRITICAL']],

    // ── Age modifiers ──────────────────────────────────────────────────
    ['id' => 24, 'label' => 'Elderly + moderate pollution',
     'if' => ['age'=>'SENIOR', 'pollution'=>'MODERATE'],
     'then' => ['risk'=>'MODERATE']],

    ['id' => 25, 'label' => 'Child + high pollution',
     'if' => ['age'=>'YOUNG', 'pollution'=>'HIGH'],
     'then' => ['risk'=>'HIGH']],
];
