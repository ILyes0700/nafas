<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 50.1 : détection de motifs personnels.
 * Corrélation de Pearson entre l'historique symptômes d'un utilisateur et la
 * pollution de sa zone décalée (lag 0-72h). N'affiche un motif que si p<0.05.
 * Dégradation gracieuse : renvoie null si données insuffisantes / tables absentes.
 */

require_once __DIR__ . '/helpers.php';

if (!function_exists('_pearson')) {
    function _pearson(array $x, array $y): array
    {
        $n = min(count($x), count($y));
        if ($n < 8) return ['r' => 0.0, 'p' => 1.0, 'n' => $n];
        $x = array_slice(array_values($x), 0, $n);
        $y = array_slice(array_values($y), 0, $n);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $mx; $dy = $y[$i] - $my;
            $sxy += $dx * $dy; $sxx += $dx * $dx; $syy += $dy * $dy;
        }
        if ($sxx <= 0 || $syy <= 0) return ['r' => 0.0, 'p' => 1.0, 'n' => $n];
        $r = $sxy / sqrt($sxx * $syy);
        $r = max(-0.999999, min(0.999999, $r));
        // t de Student -> p approximatif bilatéral.
        $t = $r * sqrt(($n - 2) / (1 - $r * $r));
        $p = _t_pvalue_twosided(abs($t), $n - 2);
        return ['r' => $r, 'p' => $p, 'n' => $n];
    }
}

if (!function_exists('_t_pvalue_twosided')) {
    // Approximation raisonnable de la p-value bilatérale d'un t de Student.
    function _t_pvalue_twosided(float $t, int $df): float
    {
        if ($df <= 0) return 1.0;
        $x = $df / ($df + $t * $t);
        $p = _betai($df / 2.0, 0.5, $x); // = probabilité dans les queues
        return max(0.0, min(1.0, $p));
    }
    function _betai(float $a, float $b, float $x): float
    {
        if ($x <= 0.0) return 0.0;
        if ($x >= 1.0) return 1.0;
        $bt = exp(_gammaln($a + $b) - _gammaln($a) - _gammaln($b)
                + $a * log($x) + $b * log(1 - $x));
        if ($x < ($a + 1) / ($a + $b + 2)) return $bt * _betacf($a, $b, $x) / $a;
        return 1.0 - $bt * _betacf($b, $a, 1 - $x) / $b;
    }
    function _betacf(float $a, float $b, float $x): float
    {
        $MAXIT = 100; $EPS = 3.0e-7; $FPMIN = 1.0e-30;
        $qab = $a + $b; $qap = $a + 1.0; $qam = $a - 1.0;
        $c = 1.0; $d = 1.0 - $qab * $x / $qap;
        if (abs($d) < $FPMIN) $d = $FPMIN; $d = 1.0 / $d; $h = $d;
        for ($m = 1; $m <= $MAXIT; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d; if (abs($d) < $FPMIN) $d = $FPMIN;
            $c = 1.0 + $aa / $c; if (abs($c) < $FPMIN) $c = $FPMIN;
            $d = 1.0 / $d; $h *= $d * $c;
            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d; if (abs($d) < $FPMIN) $d = $FPMIN;
            $c = 1.0 + $aa / $c; if (abs($c) < $FPMIN) $c = $FPMIN;
            $d = 1.0 / $d; $del = $d * $c; $h *= $del;
            if (abs($del - 1.0) < $EPS) break;
        }
        return $h;
    }
    function _gammaln(float $xx): float
    {
        $cof = [76.18009172947146, -86.50532032941677, 24.01409824083091,
                -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
        $x = $xx; $y = $xx; $tmp = $x + 5.5; $tmp -= ($x + 0.5) * log($tmp);
        $ser = 1.000000000190015;
        for ($j = 0; $j <= 5; $j++) { $y += 1.0; $ser += $cof[$j] / $y; }
        return -$tmp + log(2.5066282746310005 * $ser / $x);
    }
}

/**
 * Détecte le meilleur lag corrélé entre symptômes de l'utilisateur et pollution.
 * @return array|null narratif + stats si significatif (p<0.05), sinon null.
 */
function detect_personal_pattern(PDO $pdo, int $userId): ?array
{
    try {
        // Zone de l'utilisateur.
        $zst = $pdo->prepare('SELECT zone_id FROM users WHERE id = ?');
        $zst->execute([$userId]);
        $zoneId = (int)($zst->fetchColumn() ?: 0);
        if (!$zoneId) return null;

        // Série symptômes journalière (sevérité numérisée) sur 60 jours.
        $sy = $pdo->prepare(
            "SELECT DATE(reported_at) d,
                    AVG(CASE severity WHEN 'severe' THEN 3 WHEN 'moderate' THEN 2 ELSE 1 END) sev
             FROM symptoms
             WHERE user_id = ? AND reported_at >= NOW() - INTERVAL 60 DAY
             GROUP BY DATE(reported_at) ORDER BY d"
        );
        $sy->execute([$userId]);
        $symByDay = [];
        foreach ($sy->fetchAll() as $r) $symByDay[$r['d']] = (float)$r['sev'];
        if (count($symByDay) < 8) return null;

        // Série pollution journalière de la zone (risk_scores) sur 63 jours.
        $ps = $pdo->prepare(
            "SELECT DATE(computed_at) d, AVG(score) sc
             FROM risk_scores
             WHERE zone_id = ? AND computed_at >= NOW() - INTERVAL 63 DAY
             GROUP BY DATE(computed_at) ORDER BY d"
        );
        $ps->execute([$zoneId]);
        $polByDay = [];
        foreach ($ps->fetchAll() as $r) $polByDay[$r['d']] = (float)$r['sc'];
        if (count($polByDay) < 8) return null;

        // Teste des lags de 0, 1, 2 et 3 jours (0-72h).
        $best = null;
        foreach ([0, 1, 2, 3] as $lagDays) {
            $x = []; $y = [];
            foreach ($symByDay as $day => $sev) {
                $ref = date('Y-m-d', strtotime($day . " -{$lagDays} day"));
                if (isset($polByDay[$ref])) { $x[] = $polByDay[$ref]; $y[] = $sev; }
            }
            if (count($x) < 8) continue;
            $st = _pearson($x, $y);
            if ($best === null || abs($st['r']) > abs($best['r'])) {
                $best = $st + ['lag_hours' => $lagDays * 24];
            }
        }
        if ($best === null || $best['p'] >= 0.05 || abs($best['r']) < 0.3) return null;

        $lagH = (int)$best['lag_hours'];
        $dir  = $best['r'] > 0 ? "s'aggravent" : "s'améliorent";
        $narr = $lagH === 0
            ? "Vos symptômes $dir le jour même d'un pic de pollution dans votre zone."
            : "Vos symptômes $dir ~{$lagH}h après un pic de pollution dans votre zone.";

        // Persiste (best-effort).
        try {
            $pdo->prepare('UPDATE personal_patterns SET active=0 WHERE user_id=?')->execute([$userId]);
            $pdo->prepare(
                "INSERT INTO personal_patterns
                 (user_id, detected_at, pollutant, lag_hours, correlation, p_value, narrative, active)
                 VALUES (?, NOW(), 'global', ?, ?, ?, ?, 1)"
            )->execute([$userId, $lagH, round($best['r'], 3), round($best['p'], 4), $narr]);
        } catch (Throwable $e) { /* table absente: on renvoie quand même le résultat */ }

        return [
            'lag_hours'   => $lagH,
            'correlation' => round($best['r'], 3),
            'p_value'     => round($best['p'], 4),
            'n'           => (int)$best['n'],
            'narrative'   => $narr,
        ];
    } catch (Throwable $e) {
        return null;
    }
}
