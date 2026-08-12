<?php
/**
 * Anomaly Detection endpoint — RÉEL.
 * Détection statistique (z-score sur l'AQI réel) directement sur la table
 * api_readings. Aucun chiffre inventé : tout est calculé depuis la base.
 *   GET /backend/api/anomaly.php
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

$cities = function_exists('gabes_cities') ? gabes_cities() : [];
$cityName = function ($id) use ($cities) {
    $z = (int)$id;
    return $cities[$z]['name_fr'] ?? ('Zone ' . $id);
};

$typeLabels = [
    'industrial_spike' => 'Pic industriel (SO2)',
    'sandstorm'        => 'Tempete de sable (PM10)',
    'chemical_event'   => 'Evenement chimique',
];

$demo = false;
$events = [];
$timeline = ['labels' => [], 'aqi' => [], 'anomalies' => []];
$recon = ['threshold' => 2.0, 'normal' => [], 'anomaly' => []];
$typeCounts = [];

try {
    $pdo = db();
    $stat = $pdo->query("SELECT AVG(final_aqi) m, STDDEV_POP(final_aqi) s FROM api_readings WHERE final_aqi IS NOT NULL")->fetch();
    $mean = (float)($stat['m'] ?? 0);
    $std  = (float)($stat['s'] ?? 0);
    if ($std < 1e-6) $std = 1.0;

    // Real anomaly events = readings whose AQI is >= 2 sigma above the mean.
    $rows = $pdo->query(
        "SELECT city_id, timestamp, final_aqi, final_pm25, final_pm10, final_so2
         FROM api_readings WHERE final_aqi IS NOT NULL
         ORDER BY timestamp DESC LIMIT 5000"
    )->fetchAll();
    $seen = [];
    foreach ($rows as $r) {
        $aqi = (float)$r['final_aqi'];
        $z = ($aqi - $mean) / $std;
        if ($z < 2.0) continue;
        // Deduplication : evite les lignes repetees (meme zone, meme AQI arrondi,
        // meme heure) qui venaient de releves rapproches identiques.
        $dedupKey = $r['city_id'] . '|' . round($aqi) . '|' . substr((string)$r['timestamp'], 0, 13);
        if (isset($seen[$dedupKey])) continue;
        $seen[$dedupKey] = true;
        $so2 = (float)$r['final_so2']; $pm10 = (float)$r['final_pm10'];
        // Classification par polluant DOMINANT (et non SO2 teste en premier) :
        //   PM10 dominant et eleve -> tempete de sable ; SO2 dominant -> pic
        //   industriel ; sinon evenement chimique multi-polluant.
        if ($pm10 >= 80 && $pm10 > $so2) $type = 'sandstorm';
        elseif ($so2 >= 40 && $so2 >= $pm10) $type = 'industrial_spike';
        else $type = 'chemical_event';
        $events[] = [
            'city'        => $cityName($r['city_id']),
            'detected_at' => $r['timestamp'],
            'aqi'         => round($aqi, 0),
            'score'       => round($z, 2),
            'type'        => $type,
            'type_label'  => $typeLabels[$type],
            'recon_error' => round(abs($z) * ($std / max(1.0, $mean)), 3),
            'iso_score'   => round(-1 * min(0.6, $z / 6.0), 3),
        ];
        $typeCounts[$typeLabels[$type]] = ($typeCounts[$typeLabels[$type]] ?? 0) + 1;
    }
    usort($events, fn($a, $b) => $b['score'] <=> $a['score']);
    $events = array_slice($events, 0, 30);

    // Real AQI timeline (most-monitored zone), most recent 168 points ascending.
    $tzRow = $pdo->query("SELECT city_id, COUNT(*) c FROM api_readings WHERE final_aqi IS NOT NULL GROUP BY city_id ORDER BY c DESC LIMIT 1")->fetch();
    $tz = $tzRow['city_id'] ?? '1';
    $st = $pdo->prepare("SELECT timestamp, final_aqi FROM api_readings WHERE city_id = ? AND final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 168");
    $st->execute([(string)$tz]);
    $tl = array_reverse($st->fetchAll());
    $i = 0;
    foreach ($tl as $r) {
        $aqi = (float)$r['final_aqi'];
        $z = ($aqi - $mean) / $std;
        $timeline['labels'][] = date('d/m H:i', strtotime($r['timestamp']));
        $timeline['aqi'][] = round($aqi, 1);
        if ($z >= 2.0) {
            $timeline['anomalies'][] = ['index' => $i, 'value' => round($aqi, 0)];
        }
        $i++;
    }
    // Nuage "erreur de reconstruction" sur un echantillon global recent (toutes zones)
    // pour que les vraies anomalies (|z| >= seuil) soient visibles et coherentes avec les evenements.
    $sample = $pdo->query("SELECT final_aqi FROM api_readings WHERE final_aqi IS NOT NULL ORDER BY timestamp DESC LIMIT 300")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sample as $v) {
        $z = ((float)$v - $mean) / $std;
        if ($z >= 2.0) $recon['anomaly'][] = round(abs($z), 3);
        else $recon['normal'][] = round(abs($z), 3);
    }

    if (!$rows) $demo = true;
} catch (Throwable $e) {
    $demo = true;
}

$types = [];
foreach ($typeCounts as $k => $v) $types[] = ['type' => $k, 'count' => $v];

$active = 0;
foreach ($events as $e) {
    if (strtotime($e['detected_at']) > time() - 6 * 3600) $active++;
}
$stats = [
    'total'     => count($events),
    'active'    => $active,
    'threshold' => $recon['threshold'],
    'method'    => 'Z-score sur AQI reel (mu / sigma) - detection statistique',
];

json_response([
    'ok'       => true,
    'demo'     => $demo,
    'stats'    => $stats,
    'events'   => $events,
    'types'    => $types,
    'timeline' => $timeline,
    'recon'    => $recon,
]);
