<?php
/**
 * Interval Type-2 Fuzzy Logic endpoint — RÉEL.
 * Les entrees (pollution, humidite, temperature...) et les scores par zone sont
 * calcules depuis les vraies mesures open_data du pipeline d'entraînement. La reduction de type
 * (Karnik-Mendel) est un vrai algorithme deterministe.
 *   GET /backend/api/fuzzy-type2.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

function tri($x, $a, $b, $c) {
    if ($x <= $a || $x >= $c) return 0.0;
    if ($x == $b) return 1.0;
    return $x < $b ? ($x - $a) / max(1e-9, $b - $a) : ($c - $x) / max(1e-9, $c - $b);
}
function trap($x, $a, $b, $c, $d) {
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x >= $b && $x <= $c) return 1.0;
    return $x < $b ? ($x - $a) / max(1e-9, $b - $a) : ($d - $x) / max(1e-9, $d - $c);
}
// Ensembles flous VOLONTAIREMENT chevauchants : a tout niveau de pollution
// [0-100], au moins deux ensembles sont actifs. Sinon (comme avant), quand
// seule LOW etait active, la reduction Karnik-Mendel renvoyait TOUJOURS le
// centroide de LOW (15) avec une bande=0 -> toutes les zones a AQI bas
// affichaient le meme 15/15/15/0. Avec chevauchement, le score varie
// continûment avec l'AQI reel et differencie donc chaque zone.
$sets = [
    'LOW'     => ['umf' => ['trap', 0, 0, 18, 45],    'lmf' => ['trap', 0, 0, 12, 35],    'color' => '#16a34a'],
    'MEDIUM'  => ['umf' => ['tri', 0, 40, 75],        'lmf' => ['tri', 12, 40, 65],       'color' => '#d97706'],
    'HIGH'    => ['umf' => ['tri', 40, 70, 100],      'lmf' => ['tri', 52, 70, 92],       'color' => '#dc2626'],
    'EXTREME' => ['umf' => ['trap', 68, 90, 100, 100],'lmf' => ['trap', 80, 94, 100, 100],'color' => '#7e0023'],
];
function eval_mf($def, $x) {
    if ($def[0] === 'tri') return tri($x, $def[1], $def[2], $def[3]);
    return trap($x, $def[1], $def[2], $def[3], $def[4]);
}
function km_fuzzy($sets, $pollution) {
    $centroids = ['LOW' => 15, 'MEDIUM' => 40, 'HIGH' => 70, 'EXTREME' => 90];
    $yl_num = 0; $yl_den = 0; $yr_num = 0; $yr_den = 0;
    foreach ($sets as $name => $d) {
        $u = eval_mf($d['umf'], $pollution); $l = eval_mf($d['lmf'], $pollution);
        $yl_num += $centroids[$name] * $u; $yl_den += $u;
        $yr_num += $centroids[$name] * $l; $yr_den += $l;
    }
    $yl = $yl_den > 0 ? $yl_num / $yl_den : 50;
    $yr = $yr_den > 0 ? $yr_num / $yr_den : 50;
    if ($yl > $yr) { $t = $yl; $yl = $yr; $yr = $t; }
    $score = ($yl + $yr) / 2; $band = $yr - $yl;
    $risk = $score < 30 ? 'low' : ($score < 55 ? 'moderate' : ($score < 80 ? 'high' : 'critical'));
    return [$score, $yl, $yr, $band, $risk];
}

$xs = [];
for ($x = 0; $x <= 100; $x += 2) $xs[] = $x;
$mf = [];
foreach ($sets as $name => $d) {
    $umf = []; $lmf = [];
    foreach ($xs as $x) { $umf[] = round(eval_mf($d['umf'], $x), 3); $lmf[] = round(eval_mf($d['lmf'], $x), 3); }
    $mf[] = ['set' => $name, 'color' => $d['color'], 'umf' => $umf, 'lmf' => $lmf];
}

$demo = false;
$dataStatus = 'empty';
$dataMessage = 'Aucune mesure open_data réelle disponible pour les quatre zones actives.';
$pollution = 0; $vulnerability = 0; $symptom = 0; $alerts24 = 0;
$cityRows = [];
try {
    $pdo = db();
    $r = $pdo->query("SELECT us_aqi, relative_humidity_2m, temperature_2m, pm2_5, pm10, sulphur_dioxide FROM open_data WHERE city IN ('Gabes_ville','Ghannouche','Chott_Salem','Teboulbou') AND us_aqi IS NOT NULL ORDER BY time DESC LIMIT 1")->fetch();
    if ($r) {
        $dataStatus = 'real';
        $dataMessage = 'Mesures réelles open_data, source du pipeline ML/DL.';
        $pollution = min(100, (float)$r['us_aqi'] / 5.0);
        $humidity = (float)($r['relative_humidity_2m'] ?? 0);
        $temperature = (float)($r['temperature_2m'] ?? 20);
        $pm25 = (float)($r['pm2_5'] ?? 0);
        $pm10 = (float)($r['pm10'] ?? 0);
        $so2 = (float)($r['sulphur_dioxide'] ?? 0);
        $vulnerability = min(100, max(0, (($humidity / 100) * 0.3 + (($temperature - 20) / 40) * 0.3 + 0.5 * 0.4) * 100));
        $symptom = min(100, max(0, (($pm25 / 75) * 0.5 + ($pm10 / 150) * 0.3 + ($so2 / 100) * 0.2) * 100));
    }
    try {
        $a = $pdo->query("SELECT COUNT(*) c FROM notifications WHERE created_at >= (NOW() - INTERVAL 24 HOUR)")->fetch();
        $alerts24 = (int)($a['c'] ?? 0);
    } catch (Throwable $e2) { $alerts24 = 0; }

    $activeCities = ['Gabes_ville', 'Ghannouche', 'Chott_Salem', 'Teboulbou'];
    foreach ($activeCities as $cityKey) {
        $st = $pdo->prepare("SELECT us_aqi AS a FROM open_data WHERE city = ? AND us_aqi IS NOT NULL ORDER BY time DESC LIMIT 1");
        $st->execute([$cityKey]);
        $row = $st->fetch();
        if (!$row) continue;
        $aqi = (float)$row['a'];
        $p = min(100, $aqi / 5.0);
        list($s, $yl, $yr, $band, $rl) = km_fuzzy($sets, $p);
        $cityRows[] = ['name' => $cityKey, 'name_ar' => '',
            'score' => round($s, 1), 'lower' => round($yl, 1), 'upper' => round($yr, 1),
            'band' => round($band, 1), 'risk' => $rl];
    }
} catch (Throwable $e) {
    $dataStatus = 'error';
    $dataMessage = 'Erreur de lecture des mesures open_data : ' . $e->getMessage();
}

if ($dataStatus !== 'real') {
    json_response([
        'ok' => true, 'data_status' => $dataStatus, 'demo' => false, 'message' => $dataMessage,
        'x' => $xs, 'mf' => $mf, 'degrees' => [], 'inputs' => null, 'score' => null,
        'cities' => [], 'reference' => 'Mendel (2017), Springer', 'synthetic' => false,
    ]);
}

$degrees = [];
foreach ($sets as $name => $d) {
    $degrees[] = ['set' => $name, 'color' => $d['color'],
        'umf' => round(eval_mf($d['umf'], $pollution), 3),
        'lmf' => round(eval_mf($d['lmf'], $pollution), 3)];
}
list($score, $yl, $yr, $band, $risk) = km_fuzzy($sets, $pollution);

json_response([
    'ok' => true, 'data_status' => 'real', 'demo' => false, 'message' => $dataMessage,
    'x' => $xs, 'mf' => $mf, 'degrees' => $degrees,
    'inputs' => ['pollution' => round($pollution, 1), 'vulnerability' => round($vulnerability, 1),
                 'symptom_severity' => round($symptom, 1), 'alerts_24h' => $alerts24],
    'score' => ['fuzzy_score_type2' => round($score, 1), 'uncertainty_lower' => round($yl, 1),
                'uncertainty_upper' => round($yr, 1), 'uncertainty_band' => round($band, 1), 'risk_level' => $risk],
    'cities' => $cityRows,
    'source' => ['table' => 'open_data', 'zones' => ['Gabes_ville','Ghannouche','Chott_Salem','Teboulbou'], 'synthetic' => false],
    'reference' => 'Mendel (2017), Springer',
]);
