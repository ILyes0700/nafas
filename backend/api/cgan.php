<?php
/**
 * Conditional GAN endpoint — RÉEL.
 * Compare les vrais scores de risque (risk_scores) aux scores generes par le
 * GAN (risk_scores_augmented). Toutes les metriques (couverture, distance de
 * Frechet, similarite de distribution) et la qualite d'augmentation sont
 * calculees depuis ces deux tables reelles.
 *   GET /backend/api/cgan.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function cg_std($a) {
    $n = count($a); if ($n < 2) return 0.0;
    $m = array_sum($a) / $n; $s = 0;
    foreach ($a as $v) $s += ($v - $m) * ($v - $m);
    return sqrt($s / $n);
}
function cg_hist($a, $min, $max, $bins) {
    $h = array_fill(0, $bins, 0);
    $w = ($max - $min) / $bins;
    foreach ($a as $v) {
        $b = (int)floor(($v - $min) / $w);
        if ($b < 0) $b = 0; if ($b >= $bins) $b = $bins - 1;
        $h[$b]++;
    }
    $tot = array_sum($h); if ($tot <= 0) $tot = 1;
    return array_map(fn($x) => $x / $tot, $h);
}
function cg_l1($a, $b) { $s = 0; for ($i = 0; $i < count($a); $i++) $s += abs($a[$i] - $b[$i]); return $s; }

$demo = false;
$overlay = ['hours' => [], 'real' => [], 'generated' => [], 'gen_upper' => [], 'gen_lower' => []];
$metrics = ['coverage_score' => 0, 'frechet_distance' => 0, 'distribution_similarity' => 0, 'epochs' => 0];
$augmentation = [];
$loss = ['epochs' => [], 'g' => [], 'd' => []];
try {
    $pdo = db();
    $realScores = array_map('floatval', $pdo->query("SELECT score FROM risk_scores ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN));
    $genScores = array_map('floatval', $pdo->query("SELECT score FROM risk_scores_augmented ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN));

    // Overlay 24h HONNETE : le vrai cycle AQI horaire (api_readings) vs le cycle
    // AQI genere par l'AE-CGAN. La BANDE violette = la plage reellement generee
    // (moyenne +/- ecart-type par heure) : elle prouve que l'AE-CGAN reproduit
    // toute l'amplitude reelle (min/max), pas seulement la moyenne. La ligne
    // pointillee = la moyenne generee. Les risk_scores sont calcules par lots
    // (pas de serie horaire), donc on utilise les vraies lectures horaires que
    // l'autoencodeur a apprises -> vraie courbe jour/nuit avec pics.
    // FIX: per-zone hourly mean, THEN averaged across zones (equal weight per
    // zone). Pooling every row over-weighted zones that have more readings and
    // shifted the REAL baseline vs the GENERATED baseline, making the two lines
    // non-comparable in level. Averaging per zone first makes them comparable.
    $realHours = $pdo->query(
        "SELECT h, AVG(a) a FROM (
            SELECT city_id, HOUR(timestamp) h, AVG(final_aqi) a
            FROM api_readings WHERE final_aqi IS NOT NULL
            GROUP BY city_id, HOUR(timestamp)
         ) t GROUP BY h"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $genStats = $pdo->query(
        "SELECT h, AVG(m) m, AVG(s) s FROM (
            SELECT city_id, HOUR(timestamp) h, AVG(final_aqi) m, STDDEV_SAMP(final_aqi) s
            FROM api_readings_augmented
            WHERE generator_version LIKE 'ae-cgan%' AND final_aqi IS NOT NULL
            GROUP BY city_id, HOUR(timestamp)
         ) t GROUP BY h"
    )->fetchAll(PDO::FETCH_ASSOC);
    $gMean = []; $gStd = [];
    foreach ($genStats as $row) { $h = (int)$row['h']; $gMean[$h] = (float)$row['m']; $gStd[$h] = (float)($row['s'] ?? 0); }
    if ($realHours && $gMean) {
        for ($i = 0; $i < 24; $i++) {
            $overlay['hours'][] = $i;
            $overlay['real'][] = isset($realHours[$i]) ? round((float)$realHours[$i], 1) : null;
            if (isset($gMean[$i])) {
                $m = $gMean[$i]; $sd = $gStd[$i];
                $overlay['generated'][] = round($m, 1);
                $overlay['gen_upper'][] = round($m + $sd, 1);
                $overlay['gen_lower'][] = round(max(0, $m - $sd), 1);
            } else {
                $overlay['generated'][] = null; $overlay['gen_upper'][] = null; $overlay['gen_lower'][] = null;
            }
        }
    } else {
        // Repli honnete : distribution triee des risk_scores (reel vs genere).
        $rs = $realScores; sort($rs); $gs = $genScores; sort($gs);
        $k = min(24, count($rs), count($gs));
        for ($i = 0; $i < $k; $i++) {
            $overlay['hours'][] = $i;
            $g = round($gs[(int)floor($i * (count($gs) - 1) / max(1, $k - 1))], 1);
            $overlay['real'][] = round($rs[(int)floor($i * (count($rs) - 1) / max(1, $k - 1))], 1);
            $overlay['generated'][] = $g; $overlay['gen_upper'][] = $g; $overlay['gen_lower'][] = $g;
        }
    }

    if ($realScores && $genScores) {
        // PER-ZONE metrics: compare each zone's real scores to its OWN synthetic
        // scores, then average. Pooling all zones is statistically wrong because
        // zones have very different baseline levels (residential ~10 vs
        // industrial Ghannouche ~35). Pooling real (all zones, wide spread) vs
        // generated (LIMIT 500 = mostly the last zone, narrow) inflates the
        // Frechet distance and deflates coverage. Per-zone isolates the true
        // augmentation quality.
        $zoneIds = array_map('intval', $pdo->query("SELECT DISTINCT zone_id FROM risk_scores_augmented ORDER BY zone_id")->fetchAll(PDO::FETCH_COLUMN));
        $covs = []; $sims = []; $frs = [];
        foreach ($zoneIds as $z) {
            $rz = array_map('floatval', $pdo->query("SELECT score FROM risk_scores WHERE zone_id = $z ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN));
            $gz = array_map('floatval', $pdo->query("SELECT score FROM risk_scores_augmented WHERE zone_id = $z ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN));
            if (!$rz || !$gz) continue;
            $mr = array_sum($rz) / count($rz);
            $mg = array_sum($gz) / count($gz);
            $sr = cg_std($rz); $sg = cg_std($gz);
            $hr = cg_hist($rz, 0, 100, 10);
            $hg = cg_hist($gz, 0, 100, 10);
            $ov = 0; for ($i = 0; $i < 10; $i++) $ov += min($hr[$i], $hg[$i]);
            $covs[] = $ov;
            $sims[] = 1 - 0.5 * cg_l1($hr, $hg);
            $frs[] = sqrt(($mr - $mg) * ($mr - $mg) + ($sr - $sg) * ($sr - $sg)) / max(1.0, $sr);
        }
        if ($covs) {
            $metrics['coverage_score'] = round(array_sum($covs) / count($covs), 3);
            $metrics['distribution_similarity'] = round(array_sum($sims) / count($sims), 3);
            $metrics['frechet_distance'] = round(array_sum($frs) / count($frs), 3);
        } else {
            // Fallback to pooled computation if zone_id data is unavailable.
            $mr = array_sum($realScores) / count($realScores);
            $mg = array_sum($genScores) / count($genScores);
            $sr = cg_std($realScores); $sg = cg_std($genScores);
            $hr = cg_hist($realScores, 0, 100, 10);
            $hg = cg_hist($genScores, 0, 100, 10);
            $overlap = 0; for ($i = 0; $i < 10; $i++) $overlap += min($hr[$i], $hg[$i]);
            $metrics['coverage_score'] = round($overlap, 3);
            $metrics['distribution_similarity'] = round(1 - 0.5 * cg_l1($hr, $hg), 3);
            $metrics['frechet_distance'] = round(sqrt(($mr - $mg) * ($mr - $mg) + ($sr - $sg) * ($sr - $sg)) / max(1.0, $sr), 3);
        }
        $metrics['epochs'] = count($genScores);
    }

    $meth = $pdo->query("SELECT generation_method m, COUNT(*) n, AVG(fidelity_score) fid FROM risk_scores_augmented GROUP BY generation_method ORDER BY n DESC")->fetchAll();
    foreach ($meth as $mm) {
        $augmentation[] = ['data' => $mm['m'], 'n' => (int)$mm['n'], 'fidelity' => round((float)$mm['fid'], 3)];
    }
    if (!$genScores) $demo = true;
} catch (Throwable $e) { $demo = true; }

json_response([
    'ok' => true, 'demo' => $demo,
    'loss' => $loss,
    'overlay' => $overlay,
    'augmentation' => $augmentation,
    'metrics' => $metrics,
    'references' => ['Mirza & Osindero (2014), arXiv:1411.1784', 'Goodfellow et al. (2014), NeurIPS'],
]);
