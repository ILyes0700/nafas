<?php
/**
 * PART 0 — Endpoint des données de pollution multi-API (fusion).
 *
 * GET                       → liste fusionnée de TOUTES les villes (carte + dropdown)
 * GET ?city_id=N            → détail complet d'une ville (3 API + prévisions + historique)
 * POST {force:true}         → recalcule + rafraîchit toutes les villes (bouton "Actualiser")
 * POST {force:true, city_id} → rafraîchit une seule ville
 */
 
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/fusion.php';
 
auth_start();
$pdo = db();
 
/* ---------- POST : forcer le rafraîchissement ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = read_json_input();
    $cityId = isset($in['city_id']) ? (int)$in['city_id'] : 0;
    if ($cityId > 0) {
        $r = fusion_get_city($pdo, $cityId, true);
        if (!$r) json_response(['ok' => false, 'error' => 'city_not_found'], 404);
        json_response(['ok' => true, 'refreshed' => 1, 'city' => api_data_pack_summary($r)]);
    }
    $all = fusion_refresh_all($pdo);
    json_response(['ok' => true, 'refreshed' => count($all)]);
}
 
/* ---------- GET ?city_id=N : détail complet ---------- */
$cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
if ($cityId > 0) {
    $r = fusion_get_city($pdo, $cityId, false);
    if (!$r) json_response(['ok' => false, 'error' => 'city_not_found'], 404);
 
    // Historique (12 dernières lectures) pour les graphiques.
    $history = [];
    if (fusion_table_exists($pdo)) {
        $q = $pdo->prepare('SELECT timestamp, final_aqi FROM api_readings WHERE city_id = ? ORDER BY timestamp DESC LIMIT 12');
        $q->execute([(string)$cityId]);
        foreach (array_reverse($q->fetchAll()) as $row) {
            $history[] = ['t' => $row['timestamp'], 'aqi' => (float)$row['final_aqi']];
        }
    }
    $fv = fusion_feature_vector($pdo, $cityId, $r);
 
    json_response([
        'ok'        => true,
        'who_limits'=> WHO_LIMITS,
        'city'      => api_data_pack_detail($r),
        'history'   => $history,
        'feature_vector' => ['labels' => $fv['labels'], 'values' => $fv['features'], 'target_aqi' => $fv['target_aqi']],
    ]);
}
 
/* ---------- GET : toutes les villes ---------- */
$cities = [];
foreach (gabes_cities() as $zid => $c) {
    $r = fusion_get_city($pdo, (int)$zid, false);
    if ($r) $cities[] = api_data_pack_summary($r);
}
json_response([
    'ok'         => true,
    'count'      => count($cities),
    'factories'  => gabes_factories(),
    'who_limits' => WHO_LIMITS,
    'cities'     => $cities,
]);
 
 
/* ============================ helpers de packing ============================ */
function api_data_pack_summary(array $r): array
{
    $c = $r['city'];
    return [
        'city_id'        => (int)$c['zone_id'],
        'name_fr'        => $c['name_fr'],
        'name_ar'        => $c['name_ar'],
        'lat'            => $c['lat'],
        'lng'            => $c['lng'],
        'type'           => $c['type'],
        'factories'      => $c['factories'],
        'population'     => $c['population'],
        'pollution_factor' => $c['pollution_factor'],
        'final_aqi'      => (float)$r['final_aqi'],
        'final_category' => $r['final_category'],
        'category_color' => $r['category_color'],
        'status'         => $r['status'],
        'final_pm25'     => $r['final_pm25'],
        'final_pm10'     => $r['final_pm10'],
        'final_no2'      => $r['final_no2'],
        'final_so2'      => $r['final_so2'],
        'final_o3'       => $r['final_o3'],
        'final_co'       => $r['final_co'],
        'final_temperature' => $r['final_temperature'],
        'final_humidity' => $r['final_humidity'],
        'final_wind_speed' => $r['final_wind_speed'],
        'final_wind_direction' => $r['final_wind_direction'],
        'data_quality_score' => $r['data_quality_score'],
        'sources_available' => [
            'accuweather' => (int)($r['sources']['accuweather']['available'] ?? 0),
            'iqair'       => (int)($r['sources']['iqair']['available'] ?? 0),
            'waqi'        => (int)($r['sources']['waqi']['available'] ?? 0),
        ],
        'timestamp'      => $r['timestamp'],
    ];
}
 
function api_data_pack_detail(array $r): array
{
    $summary = api_data_pack_summary($r);
    $summary['fusion_method']  = $r['fusion_method'];
    $summary['weights']        = $r['weights'] ?? null;
    $summary['final_pressure'] = $r['final_pressure'];
    $summary['forecast']       = $r['forecast'];
    $summary['sources']        = $r['sources'];
    return $summary;
}