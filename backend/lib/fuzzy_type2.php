<?php
declare(strict_types=1);

/**
 * Interval Type-2 Fuzzy Logic — SHARED LIBRARY.
 *
 * Extracted from backend/api/fuzzy-type2.php so the SAME type-2 reasoning
 * (Karnik-Mendel type-reduction) can now feed the automatic recommendation
 * engine and the Nafass chatbot — not only the admin scientific page.
 *
 * Public API:
 *   fuzzy_type2_infer(float $crisp): array   → score/band/risk from a crisp 0..100
 *   fuzzy_type2_from_inputs(array $in): array → builds the crisp from the same
 *                                               inputs used by fuzzy_for_user()
 *                                               then runs the inference.
 */

/* --- Membership primitives (prefixed to avoid collisions) --------------- */
function ft2_tri(float $x, float $a, float $b, float $c): float {
    if ($x <= $a || $x >= $c) return 0.0;
    if ($x == $b) return 1.0;
    return $x < $b ? ($x - $a) / max(1e-9, $b - $a) : ($c - $x) / max(1e-9, $c - $b);
}
function ft2_trap(float $x, float $a, float $b, float $c, float $d): float {
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x >= $b && $x <= $c) return 1.0;
    return $x < $b ? ($x - $a) / max(1e-9, $b - $a) : ($d - $x) / max(1e-9, $d - $c);
}

/** Type-2 MF sets for "pollution/risk": each set has an UMF and a LMF (the FOU). */
function ft2_sets(): array {
    return [
        'LOW'     => ['umf' => ['trap', 0, 0, 20, 35],     'lmf' => ['trap', 0, 0, 15, 28],     'centroid' => 15],
        'MEDIUM'  => ['umf' => ['tri', 25, 50, 75],        'lmf' => ['tri', 30, 50, 70],        'centroid' => 40],
        'HIGH'    => ['umf' => ['trap', 60, 75, 90, 100],  'lmf' => ['trap', 65, 78, 88, 100],  'centroid' => 70],
        'EXTREME' => ['umf' => ['trap', 80, 90, 100, 100], 'lmf' => ['trap', 85, 93, 100, 100], 'centroid' => 90],
    ];
}
function ft2_eval_mf(array $def, float $x): float {
    if ($def[0] === 'tri')  return ft2_tri($x, (float)$def[1], (float)$def[2], (float)$def[3]);
    return ft2_trap($x, (float)$def[1], (float)$def[2], (float)$def[3], (float)$def[4]);
}

/**
 * Interval Type-2 inference with (simplified) Karnik-Mendel type-reduction.
 * Left bound uses the UMF firing (wider), right bound uses the LMF firing.
 */
function fuzzy_type2_infer(float $crisp): array {
    $crisp = max(0.0, min(100.0, $crisp));
    $sets = ft2_sets();
    $degrees = [];
    $yl_num = 0.0; $yl_den = 0.0; $yr_num = 0.0; $yr_den = 0.0;
    foreach ($sets as $name => $d) {
        $u = ft2_eval_mf($d['umf'], $crisp);
        $l = ft2_eval_mf($d['lmf'], $crisp);
        $degrees[] = ['set' => $name, 'umf' => round($u, 3), 'lmf' => round($l, 3)];
        $yl_num += $d['centroid'] * $u; $yl_den += $u;
        $yr_num += $d['centroid'] * $l; $yr_den += $l;
    }
    $yl = $yl_den > 0 ? $yl_num / $yl_den : 50.0;
    $yr = $yr_den > 0 ? $yr_num / $yr_den : 50.0;
    if ($yl > $yr) { $t = $yl; $yl = $yr; $yr = $t; }
    $score = ($yl + $yr) / 2.0;
    $band  = $yr - $yl;
    $risk  = $score < 30 ? 'low' : ($score < 55 ? 'moderate' : ($score < 80 ? 'high' : 'critical'));
    return [
        'crisp_input'       => round($crisp, 1),
        'fuzzy_score_type2' => round($score, 1),
        'score'             => round($score, 1),
        'uncertainty_lower' => round($yl, 1),
        'uncertainty_upper' => round($yr, 1),
        'uncertainty_band'  => round($band, 1),
        'risk_level'        => $risk,
        'degrees'           => $degrees,
    ];
}

/**
 * Build the crisp type-2 input from the SAME inputs used by fuzzy_for_user()
 * (pollution 0..100, vulnerability 0..10, symptom_sev 0..10, alerts_24h count)
 * then run the interval type-2 inference. This makes the type-2 score
 * personalized per citizen, not just a raw pollution reading.
 */
function fuzzy_type2_from_inputs(array $in): array {
    $pollution  = (float)($in['pollution'] ?? 0);
    $vuln       = (float)($in['vulnerability'] ?? 0);   // 0..10
    $symp       = (float)($in['symptom_sev'] ?? ($in['symptom_severity'] ?? 0)); // 0..10
    $alerts     = (float)($in['alerts_24h'] ?? 0);
    $crisp = 0.55 * $pollution
           + 0.20 * min(100.0, $vuln * 10.0)
           + 0.15 * min(100.0, $symp * 10.0)
           + 0.10 * min(100.0, $alerts * 20.0);
    $out = fuzzy_type2_infer($crisp);
    $out['inputs'] = [
        'pollution' => round($pollution, 1),
        'vulnerability' => round($vuln, 1),
        'symptom_severity' => round($symp, 1),
        'alerts_24h' => (int)$alerts,
    ];
    return $out;
}
