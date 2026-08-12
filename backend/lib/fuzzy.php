<?php
/**
 * Mamdani Fuzzy Inference Engine (PHP implementation).
 *
 * Reference: L.A. Zadeh, "Fuzzy Sets", Information and Control 8(3), 1965.
 *            E.H. Mamdani, "Application of fuzzy algorithms…", Proc. IEE, 1974.
 *
 * This module is COMPLETELY standalone — no external dependency, no LLM, no
 * network call. Given crisp numeric inputs it:
 *
 *   1. FUZZIFIES each input through trapezoidal / triangular membership functions
 *   2. EVALUATES a rule base (loaded from fuzzy_rules.php) using min-AND
 *   3. AGGREGATES consequent activations via max-OR
 *   4. DEFUZZIFIES the output via the centroid method (Mamdani classic)
 *
 * The module exposes a single public function:
 *
 *   fuzzy_recommend(array $inputs): array
 *
 * which returns:
 *   {
 *     risk_score     : float   (0..100, defuzzified centroid)
 *     urgency_level  : string  (low|moderate|high|critical)
 *     fuzzified      : { var => { set => degree, ... }, ... }
 *     fired_rules    : [ { id, antecedents, consequent, activation }, ... ]
 *     explanation    : string  (human-readable trace)
 *   }
 */
declare(strict_types=1);

/* =====================================================================
 *  MEMBERSHIP FUNCTIONS
 * ===================================================================== */

/**
 * Trapezoidal membership function.
 *
 *   μ(x)
 *   1 ─ ┌────────────┐
 *       │  a  b    c  d
 *   0 ──┘            └──
 *       a=left foot, b=left shoulder, c=right shoulder, d=right foot.
 *       If b==c it degenerates into a triangle. If a==b and c==d it's a rectangle.
 */
function fuzzy_trapez(float $x, float $a, float $b, float $c, float $d): float
{
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x >= $b && $x <= $c) return 1.0;
    if ($x < $b)  return ($x - $a) / max(0.0001, $b - $a);
    /* $x > $c */ return ($d - $x) / max(0.0001, $d - $c);
}

/** Triangular = special case of trapezoidal where b==c. */
function fuzzy_tri(float $x, float $a, float $b, float $c): float
{
    return fuzzy_trapez($x, $a, $b, $b, $c);
}

/** Gaussian-style bell. Used when smooth curves are preferred (optional). */
function fuzzy_gauss(float $x, float $center, float $sigma): float
{
    return exp(-0.5 * (($x - $center) / max(0.0001, $sigma)) ** 2);
}

/* =====================================================================
 *  FUZZIFICATION
 * ===================================================================== */

/**
 * Apply all membership functions defined for one variable and return the
 * degree of membership in each fuzzy set.
 *
 * @param float $value   Crisp value
 * @param array $sets    [ 'LOW' => [type, params...], 'HIGH' => [...], ... ]
 * @return array         [ 'LOW' => 0.2, 'HIGH' => 0.8, ... ]
 */
function fuzzy_fuzzify(float $value, array $sets): array
{
    $out = [];
    foreach ($sets as $label => $def) {
        $type = $def[0];
        if ($type === 'trap')  $out[$label] = fuzzy_trapez($value, $def[1], $def[2], $def[3], $def[4]);
        elseif ($type === 'tri')   $out[$label] = fuzzy_tri($value, $def[1], $def[2], $def[3]);
        elseif ($type === 'gauss') $out[$label] = fuzzy_gauss($value, $def[1], $def[2]);
        else $out[$label] = 0.0;
    }
    return $out;
}

/* =====================================================================
 *  RULE EVALUATION (Mamdani, min-AND, max-OR)
 * ===================================================================== */

/**
 * Evaluate one rule: compute its activation strength (firing strength).
 *
 * @param array $antecedents  e.g. ['pollution'=>'HIGH','vulnerability'=>'HIGH']
 * @param array $fuzzified    e.g. ['pollution'=>['LOW'=>0,'HIGH'=>0.8], ...]
 * @return float              activation strength in [0..1]
 */
function fuzzy_rule_fire(array $antecedents, array $fuzzified): float
{
    $activation = 1.0;
    foreach ($antecedents as $var => $set) {
        $deg = $fuzzified[$var][$set] ?? 0.0;
        $activation = min($activation, $deg);   // AND = min
    }
    return $activation;
}

/**
 * Evaluate ALL rules and return fired ones with their activation + consequent.
 *
 * @param array $rules      loaded from fuzzy_rules.php
 * @param array $fuzzified  output of fuzzy_fuzzify() for each variable
 * @return array            [ ['id'=>1, 'activation'=>0.6, 'consequent'=>['risk'=>'CRITICAL']], ... ]
 */
function fuzzy_evaluate_rules(array $rules, array $fuzzified): array
{
    $fired = [];
    foreach ($rules as $r) {
        $act = fuzzy_rule_fire($r['if'], $fuzzified);
        if ($act > 0.001) {
            $fired[] = [
                'id'          => $r['id'],
                'activation'  => round($act, 4),
                'antecedents' => $r['if'],
                'consequent'  => $r['then'],
                'label'       => $r['label'] ?? '',
            ];
        }
    }
    return $fired;
}

/* =====================================================================
 *  AGGREGATION + DEFUZZIFICATION  (centroid)
 * ===================================================================== */

/**
 * Build the aggregated output membership function for one output variable
 * and defuzzify via centroid.
 *
 * @param string $outVar     e.g. 'risk'
 * @param array  $fired      list of fired rules
 * @param array  $outSets    membership functions for the output var
 * @param float  $rangeMin
 * @param float  $rangeMax
 * @param int    $steps      discretization steps
 * @return float             crisp output (centroid)
 */
function fuzzy_defuzzify_centroid(
    string $outVar,
    array  $fired,
    array  $outSets,
    float  $rangeMin = 0.0,
    float  $rangeMax = 100.0,
    int    $steps    = 200
): float {
    $dx   = ($rangeMax - $rangeMin) / $steps;
    $sumW = 0.0;
    $sumX = 0.0;

    for ($i = 0; $i <= $steps; $i++) {
        $x = $rangeMin + $i * $dx;
        // Aggregate: max of (min(activation, μ_consequent(x))) over all rules
        $maxMu = 0.0;
        foreach ($fired as $r) {
            $setName = $r['consequent'][$outVar] ?? null;
            if (!$setName) continue;
            $def = $outSets[$setName] ?? null;
            if (!$def) continue;
            $mu = 0.0;
            if ($def[0] === 'trap')  $mu = fuzzy_trapez($x, $def[1], $def[2], $def[3], $def[4]);
            elseif ($def[0] === 'tri') $mu = fuzzy_tri($x, $def[1], $def[2], $def[3]);
            $mu = min($r['activation'], $mu);   // clipping
            $maxMu = max($maxMu, $mu);          // max-OR
        }
        $sumW += $maxMu;
        $sumX += $x * $maxMu;
    }
    return $sumW > 0 ? round($sumX / $sumW, 2) : ($rangeMin + $rangeMax) / 2;
}

/* =====================================================================
 *  PUBLIC API
 * ===================================================================== */

/**
 * Main entry point. Accepts crisp inputs, runs the full Mamdani pipeline,
 * and returns the structured result.
 *
 * @param array $inputs  [
 *     'pollution'     => 0..100,
 *     'vulnerability' => 0..10,
 *     'symptom_sev'   => 0..10,
 *     'alerts_24h'    => 0..N,
 *     'age'           => 0..120  (optional)
 * ]
 */
function fuzzy_recommend(array $inputs): array
{
    /* 1. Load configuration */
    require_once __DIR__ . '/../config/fuzzy_rules.php';
    $vars  = FUZZY_INPUT_VARS;   // defined in fuzzy_rules.php
    $rules = FUZZY_RULES;
    $outV  = FUZZY_OUTPUT_VARS;

    /* 2. Fuzzify each input */
    $fuzzified = [];
    foreach ($vars as $varName => $sets) {
        $val = (float)($inputs[$varName] ?? 0);
        $fuzzified[$varName] = fuzzy_fuzzify($val, $sets);
    }

    /* 3. Evaluate rules */
    $fired = fuzzy_evaluate_rules($rules, $fuzzified);

    /* 4. Defuzzify */
    $riskScore = fuzzy_defuzzify_centroid(
        'risk', $fired, $outV['risk'] ?? [],
        0.0, 100.0, 300
    );

    /* 5. Map to discrete urgency */
    if ($riskScore >= 75) $urgency = 'critical';
    elseif ($riskScore >= 50) $urgency = 'high';
    elseif ($riskScore >= 30) $urgency = 'moderate';
    else $urgency = 'low';

    /* 6. Build human-readable explanation */
    $explain = [];
    // Sort by activation descending
    usort($fired, fn($a, $b) => $b['activation'] <=> $a['activation']);
    foreach (array_slice($fired, 0, 5) as $r) {
        $conds = [];
        foreach ($r['antecedents'] as $v => $s) $conds[] = "$v=$s";
        $consq = [];
        foreach ($r['consequent'] as $v => $s)  $consq[] = "$v=$s";
        $explain[] = sprintf(
            'R%d (%.0f%%): IF %s THEN %s',
            $r['id'], $r['activation'] * 100,
            implode(' AND ', $conds), implode(', ', $consq)
        );
    }
    $explanation = implode("\n", $explain);

    /* 7. Action priorities based on urgency */
    $actions = [];
    if ($riskScore >= 75) {
        $actions = [
            'Stay indoors and close all windows immediately.',
            'Wear an FFP2 mask if you must go outside.',
            'Contact a doctor if you experience breathing difficulties.',
            'Keep medication nearby if you have respiratory conditions.',
        ];
    } elseif ($riskScore >= 50) {
        $actions = [
            'Limit outdoor activities, especially exercise.',
            'Keep windows partially closed during peak hours.',
            'Monitor your symptoms closely today.',
        ];
    } elseif ($riskScore >= 30) {
        $actions = [
            'Reduce prolonged outdoor exertion.',
            'Vulnerable individuals should take precautions.',
        ];
    } else {
        $actions = [
            'Air quality is acceptable — enjoy your day normally.',
            'Stay hydrated and maintain good ventilation.',
        ];
    }

    return [
        'risk_score'    => $riskScore,
        'urgency_level' => $urgency,
        'fuzzified'     => $fuzzified,
        'fired_rules'   => $fired,
        'explanation'   => $explanation,
        'actions'       => $actions,
    ];
}
