<?php
/**
 * Granger causality endpoint — RÉEL (aucune donnée inventée).
 *
 * Vrai test F de causalité de Granger calculé sur les SÉRIES HORAIRES RÉELLES de
 * api_readings (zone la plus surveillée). Les séries sont différenciées (1er
 * ordre) pour la stationnarité. Pour chaque relation X->Y et chaque décalage L :
 *   - modèle restreint   : Y[t] = a0 + Σ a_i Y[t-i]
 *   - modèle non restreint: Y[t] = a0 + Σ a_i Y[t-i] + Σ b_i X[t-i]
 *   - F = ((RSSr - RSSu)/q) / (RSSu/(n-k))    ;   p = P(F_{q,n-k} > F)
 * La p-value provient de la vraie distribution F (fonction bêta incomplète).
 *   GET /backend/api/granger.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

/* ================= routines numériques réelles ================= */
function gr_gammln($xx) {
    $cof = [76.18009172947146, -86.50532032941677, 24.01409824083091,
            -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
    $x = $xx; $y = $xx; $tmp = $x + 5.5; $tmp -= ($x + 0.5) * log($tmp);
    $ser = 1.000000000190015;
    for ($j = 0; $j < 6; $j++) { $y += 1.0; $ser += $cof[$j] / $y; }
    return -$tmp + log(2.5066282746310005 * $ser / $x);
}
function gr_betacf($a, $b, $x) {
    $MAXIT = 200; $EPS = 3.0e-12; $FPMIN = 1.0e-30;
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
function gr_betai($a, $b, $x) {
    if ($x <= 0.0) return 0.0;
    if ($x >= 1.0) return 1.0;
    $bt = exp(gr_gammln($a + $b) - gr_gammln($a) - gr_gammln($b)
              + $a * log($x) + $b * log(1.0 - $x));
    if ($x < ($a + 1.0) / ($a + $b + 2.0)) return $bt * gr_betacf($a, $b, $x) / $a;
    return 1.0 - $bt * gr_betacf($b, $a, 1.0 - $x) / $b;
}
/* p-value = P(F_{d1,d2} > f) via la vraie distribution F */
function gr_fpval($f, $d1, $d2) {
    if ($f <= 0 || $d1 <= 0 || $d2 <= 0) return 1.0;
    return gr_betai($d2 / 2.0, $d1 / 2.0, $d2 / ($d2 + $d1 * $f));
}
/* OLS par équations normales (élimination de Gauss) ; renvoie le RSS réel */
function gr_ols_rss($X, $y) {
    $n = count($X); if ($n === 0) return null; $p = count($X[0]);
    $A = array_fill(0, $p, array_fill(0, $p, 0.0)); $g = array_fill(0, $p, 0.0);
    for ($i = 0; $i < $n; $i++) {
        $xi = $X[$i]; $yi = $y[$i];
        for ($a = 0; $a < $p; $a++) {
            $g[$a] += $xi[$a] * $yi;
            for ($b = $a; $b < $p; $b++) $A[$a][$b] += $xi[$a] * $xi[$b];
        }
    }
    for ($a = 0; $a < $p; $a++) for ($b = 0; $b < $a; $b++) $A[$a][$b] = $A[$b][$a];
    for ($a = 0; $a < $p; $a++) $A[$a][$a] += 1e-6; // ridge minuscule (stabilité)
    for ($col = 0; $col < $p; $col++) {
        $piv = $col; $mx = abs($A[$col][$col]);
        for ($r = $col + 1; $r < $p; $r++) { if (abs($A[$r][$col]) > $mx) { $mx = abs($A[$r][$col]); $piv = $r; } }
        if ($mx < 1e-12) return null;
        if ($piv !== $col) { $t = $A[$piv]; $A[$piv] = $A[$col]; $A[$col] = $t; $tg = $g[$piv]; $g[$piv] = $g[$col]; $g[$col] = $tg; }
        $dd = $A[$col][$col];
        for ($r = $col + 1; $r < $p; $r++) {
            $f = $A[$r][$col] / $dd; if ($f == 0.0) continue;
            for ($c = $col; $c < $p; $c++) $A[$r][$c] -= $f * $A[$col][$c];
            $g[$r] -= $f * $g[$col];
        }
    }
    $beta = array_fill(0, $p, 0.0);
    for ($r = $p - 1; $r >= 0; $r--) {
        $s = $g[$r];
        for ($c = $r + 1; $c < $p; $c++) $s -= $A[$r][$c] * $beta[$c];
        $beta[$r] = $s / $A[$r][$r];
    }
    $rss = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $pred = 0.0; for ($a = 0; $a < $p; $a++) $pred += $X[$i][$a] * $beta[$a];
        $e = $y[$i] - $pred; $rss += $e * $e;
    }
    return $rss;
}
/* Test de Granger X->Y au décalage L ; renvoie [F, p] ou null */
function gr_test($Y, $Xc, $L) {
    $n = count($Y);
    if ($n <= 2 * $L + 8) return null;
    $yv = []; $Xr = []; $Xu = [];
    for ($t = $L; $t < $n; $t++) {
        $ylags = []; $xlags = [];
        for ($i = 1; $i <= $L; $i++) { $ylags[] = $Y[$t - $i]; $xlags[] = $Xc[$t - $i]; }
        $yv[] = $Y[$t];
        $Xr[] = array_merge([1.0], $ylags);
        $Xu[] = array_merge([1.0], $ylags, $xlags);
    }
    $m = count($yv); $ku = 1 + 2 * $L; $df2 = $m - $ku;
    if ($df2 <= 0) return null;
    $rssR = gr_ols_rss($Xr, $yv); $rssU = gr_ols_rss($Xu, $yv);
    if ($rssR === null || $rssU === null || $rssU <= 0) return null;
    $F = (($rssR - $rssU) / $L) / ($rssU / $df2);
    if ($F < 0) $F = 0.0;
    return [$F, gr_fpval($F, $L, $df2)];
}

/* ================= séries horaires réelles ================= */
$lags = [1, 2, 3, 6, 12, 24];
$demo = false;
$pairs = [];
$interpretation = '';
try {
    $pdo = db();
    $tz = $pdo->query("SELECT city_id, COUNT(*) c FROM api_readings WHERE final_aqi IS NOT NULL GROUP BY city_id ORDER BY c DESC LIMIT 1")->fetch();
    $zone = $tz['city_id'] ?? null;
    $cols = ['final_aqi','final_pm25','final_pm10','final_so2','final_no2','final_o3','final_wind_speed','final_temperature'];
    $data = [];
    if ($zone !== null) {
        // les 1100 mesures les plus récentes (ASC) -> test rapide et pertinent
        $st = $pdo->prepare("SELECT " . implode(',', $cols) . " FROM (
            SELECT " . implode(',', $cols) . ", timestamp FROM api_readings
            WHERE city_id = ? AND final_aqi IS NOT NULL
            ORDER BY timestamp DESC LIMIT 1100) t ORDER BY t.timestamp ASC");
        $st->execute([(string)$zone]);
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $series = [];
    foreach ($cols as $c) $series[$c] = [];
    foreach ($data as $r) foreach ($cols as $c) $series[$c][] = (float)$r[$c];

    // différenciation 1er ordre (stationnarité)
    $S = [];
    foreach ($cols as $c) {
        $a = $series[$c]; $o = [];
        for ($i = 1; $i < count($a); $i++) $o[] = $a[$i] - $a[$i - 1];
        $S[$c] = $o;
    }

    $candidates = [
        ['SO2 → PM2.5',      'final_so2',         'final_pm25'],
        ['SO2 → PM10',       'final_so2',         'final_pm10'],
        ['Vent → AQI',       'final_wind_speed',  'final_aqi'],
        ['Température → O3', 'final_temperature', 'final_o3'],
        ['NO2 → AQI',        'final_no2',         'final_aqi'],
        ['NO2 → PM2.5',      'final_no2',         'final_pm25'],
        ['O3 → AQI',         'final_o3',          'final_aqi'],
    ];
    $n = count($S['final_aqi']);
    if ($n >= 60) {
        foreach ($candidates as $cd) {
            list($label, $xcol, $ycol) = $cd;
            $Y = $S[$ycol]; $Xc = $S[$xcol];
            $best = null; $plags = [];
            foreach ($lags as $L) {
                $res = gr_test($Y, $Xc, $L);
                if ($res === null) { $plags[] = null; continue; }
                list($F, $p) = $res; $plags[] = round($p, 4);
                if ($best === null || $p < $best['p']) $best = ['lag' => $L, 'p' => $p, 'f' => $F];
            }
            if ($best === null) continue;
            $pairs[] = [
                'relation' => $label,
                'best_lag' => $best['lag'],
                'p'        => round($best['p'], 4),
                'f'        => round($best['f'], 2),
                'causal'   => $best['p'] < 0.05,
                'plags'    => $plags,
            ];
        }
        // causal d'abord, puis p croissant
        usort($pairs, function ($a, $b) {
            if ($a['causal'] !== $b['causal']) return $a['causal'] ? -1 : 1;
            return $a['p'] <=> $b['p'];
        });
    }

    $sig = array_values(array_filter($pairs, function ($p) { return $p['causal']; }));
    if ($sig) {
        $parts = [];
        foreach (array_slice($sig, 0, 3) as $s) {
            $parts[] = $s['relation'] . ' (décalage ' . $s['best_lag'] . 'h, p=' . ($s['p'] < 0.001 ? '<0.001' : $s['p']) . ')';
        }
        $interpretation = 'Relations de causalité significatives (p<0.05) détectées sur les vraies séries de Gabès : ' . implode(' ; ', $parts) . '.';
    } elseif ($pairs) {
        $interpretation = "Aucune relation n'atteint le seuil p<0.05 sur l'historique réel actuel. Davantage de données horaires renforcera le test.";
    } else {
        $interpretation = "Pas assez d'historique réel pour calculer le test de Granger.";
        $demo = true;
    }
} catch (Throwable $e) {
    $demo = true;
    $interpretation = "Test indisponible : " . $e->getMessage();
}

json_response([
    'ok' => true, 'demo' => $demo,
    'lags' => $lags,
    'pairs' => $pairs,
    'interpretation' => $interpretation,
    'reference' => 'Granger (1969). Econometrica 37(3).',
]);
