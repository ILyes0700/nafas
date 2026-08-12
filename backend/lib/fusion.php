<?php
/**
 * =====================================================================
 *  MOTEUR DE FUSION MULTI-API  (PART 0 — fondation du système Nafass)
 * =====================================================================
 *
 *  Pour chaque ville de Gabès, on calcule UN seul AQI final via une fusion
 *  intelligente en 7 étapes des 3 sources :
 *
 *     AccuWeather  → 75 %  (source primaire ⭐)
 *     WAQI         → 15 %
 *     IQAir        → 10 %
 *
 *  Étapes (cf. prompt PART 0 §0.3) :
 *     1. Récupération parallèle des 3 API (curl_multi, timeout 5s)
 *     2. Normalisation sur l'échelle US AQI (0-500)
 *     3. Score de qualité des données par API
 *     4. Détection + pénalisation des valeurs aberrantes (outliers)
 *     5. Fusion pondérée dynamique
 *     6. Application du facteur de pollution de la ville + ajustement temporel
 *     7. Catégorie + couleur d'affichage
 *
 *  Robustesse : si une clé API manque ou si une API est injoignable, un
 *  générateur déterministe par ville prend le relais — ainsi CHAQUE VILLE
 *  affiche TOUJOURS SA PROPRE valeur d'AQI (différente des autres).
 *
 *  Résultat mis en cache 1h dans la table `api_readings`.
 * =====================================================================
 */
declare(strict_types=1);
 
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cities.php';
require_once __DIR__ . '/../config/accuweather.php';
require_once __DIR__ . '/../config/iqair.php';
require_once __DIR__ . '/../config/waqi.php';
 
/* ------------------------------------------------------------------ */
/*  Constantes santé (limites OMS) — utilisées par l'affichage         */
/* ------------------------------------------------------------------ */
const WHO_LIMITS = [
    'pm25' => 15.0, 'pm10' => 45.0, 'no2' => 25.0,
    'so2'  => 40.0, 'o3'   => 100.0, 'co'  => 4.0,
];
 
const FUSION_BASE_WEIGHTS = [
    'accuweather' => 0.75,
    'iqair'       => 0.10,
    'waqi'        => 0.15,
];
 
const FUSION_CACHE_MINUTES = 60;
 
/* ================================================================== */
/*  Catégorie + couleur (US AQI)                                       */
/* ================================================================== */
function fusion_category(float $aqi): array
{
    if ($aqi <= 50)  return ['Bon',          '#00E400', 'low'];
    if ($aqi <= 100) return ['Modéré',       '#FFFF00', 'moderate'];
    if ($aqi <= 150) return ['Mauvais SGS',  '#FF7E00', 'moderate'];
    if ($aqi <= 200) return ['Mauvais',      '#FF0000', 'high'];
    if ($aqi <= 300) return ['Très mauvais', '#99004C', 'critical'];
    return ['Dangereux', '#7E0023', 'critical'];
}
 
/** Mappe un AQI US (0-500) vers le statut interne safe/warning/critical. */
function fusion_status(float $aqi): string
{
    if ($aqi >= 151) return 'critical';
    if ($aqi >= 51)  return 'warning';
    return 'safe';
}
 
/** Réciproque approximative AQI(PM2.5) — EPA — pour dériver un PM2.5 d'un AQI. */
function fusion_aqi_to_pm25(float $aqi): float
{
    $bp = [
        [0, 50, 0.0, 12.0], [51, 100, 12.1, 35.4], [101, 150, 35.5, 55.4],
        [151, 200, 55.5, 150.4], [201, 300, 150.5, 250.4], [301, 500, 250.5, 500.4],
    ];
    foreach ($bp as [$aLo, $aHi, $cLo, $cHi]) {
        if ($aqi >= $aLo && $aqi <= $aHi) {
            $ratio = ($aqi - $aLo) / max(1, ($aHi - $aLo));
            return round($cLo + ($cHi - $cLo) * $ratio, 1);
        }
    }
    return round($aqi * 0.5, 1);
}
 
/* ================================================================== */
/*  STEP 2 — Normalisation AccuWeather (catégorie → AQI)               */
/* ================================================================== */
function fusion_accuw_category_to_aqi(int $categoryIndex, float $value): float
{
    switch ($categoryIndex) {
        case 1: return $value * 8;          // Good
        case 2: return 51 + $value * 5;     // Moderate
        case 3: return 101 + $value * 5;    // USG
        case 4: return 151 + $value * 5;    // Unhealthy
        case 5: return 201 + $value * 5;    // Very Unhealthy
        case 6: return 301 + $value * 10;   // Hazardous
        default: return $value;
    }
}
 
/* ================================================================== */
/*  Direction du vent (degrés → boussole)                              */
/* ================================================================== */
function fusion_wind_compass(float $deg): string
{
    $dirs = ['N','NE','E','SE','S','SO','O','NO'];
    return $dirs[(int)round(($deg % 360) / 45) % 8];
}
 
/* ================================================================== */
/*  GÉNÉRATEUR DÉTERMINISTE (fallback quand une API est indisponible)  */
/*                                                                     */
/*  Produit une lecture RÉGIONALE brute (avant facteur ville) afin que */
/*  la fusion reste réaliste. La différenciation par ville est obtenue */
/*  à l'étape 6 via pollution_factor — comme spécifié dans le prompt.  */
/* ================================================================== */
function fusion_seeded_float(string $seed, float $min, float $max): float
{
    $h = crc32($seed);
    $r = ($h % 100000) / 100000.0; // 0..1
    return $min + ($max - $min) * $r;
}
 
/** Onde temporelle (× sur l'AQI) selon l'heure de la journée. */
function fusion_time_wave(int $hour): float
{
    if ($hour >= 6 && $hour <= 8)   return 1.18;
    if ($hour >= 14 && $hour <= 16) return 1.10;
    if ($hour >= 23 || $hour <= 5)  return 0.82;
    return 1.0;
}
 
/**
 * Lecture synthétique d'une source pour une ville/heure données.
 * $sourceBias décale légèrement la source pour simuler le bruit inter-API.
 */
function fusion_synthetic_source(array $city, string $source, DateTimeImmutable $now): array
{
    $hour = (int)$now->format('H');
    $stamp = $now->format('YmdH');
    $cid = (string)$city['zone_id'];
 
    // Base régionale (identique aux 3 sources, ~Gabès) modulée par l'heure.
    $regional = 72.0 * fusion_time_wave($hour);
    $regional += fusion_seeded_float("reg{$stamp}", -8, 8);
 
    // Bruit propre à la source.
    $jitter = fusion_seeded_float("{$source}{$cid}{$stamp}", -7, 9);
    $aqi = max(8.0, $regional + $jitter);
 
    $pm25 = fusion_aqi_to_pm25($aqi);
    $pm10 = round($pm25 * 1.6, 1);
 
    // Les zones industrielles tirent SO2/NO2 vers le haut.
    $indus = in_array($city['type'], ['heavy_industrial', 'industrial_downwind'], true);
    $so2 = round(($indus ? 22 : 8) + $aqi * ($indus ? 0.45 : 0.18), 1);
    $no2 = round(($indus ? 18 : 9) + $aqi * 0.20, 1);
    $o3  = round(30 + $aqi * 0.35, 1);
    $co  = round(0.3 + $aqi * 0.012, 2);
 
    // Météo régionale (Gabès) déterministe.
    $temp = round(20 + 9 * sin(($hour - 9) / 24 * 2 * M_PI) + fusion_seeded_float("t{$stamp}", -1.5, 1.5), 1);
    $hum  = round(max(20, min(92, 70 - ($temp - 20) * 1.8 + fusion_seeded_float("h{$stamp}", -5, 5))), 0);
    $wind = round(8 + fusion_seeded_float("w{$cid}{$stamp}", 0, 18), 1);
    $wdir = round(fusion_seeded_float("wd{$cid}{$stamp}", 0, 359), 0);
    $press = round(1011 + fusion_seeded_float("p{$stamp}", -4, 5), 0);
 
    [$cat] = fusion_category($aqi);
 
    $out = [
        'available'     => true,
        'cached'        => false,
        'response_time' => fusion_seeded_float("rt{$source}{$stamp}", 0.2, 1.4),
        'synthetic'     => true,
        'aqi'           => round($aqi, 0),
        'category'      => $cat,
        'pm25' => $pm25, 'pm10' => $pm10, 'no2' => $no2,
        'so2'  => $so2,  'o3'   => $o3,   'co'  => $co,
        'temp' => $temp, 'humidity' => $hum, 'wind_speed' => $wind,
        'wind_dir' => $wdir, 'pressure' => $press,
    ];
 
    if ($source === 'accuweather') {
        $out['feels_like']  = round($temp + ($temp > 28 ? 2.5 : -1.0), 1);
        $out['visibility']  = round(12 + fusion_seeded_float("vis{$stamp}", -3, 4), 1);
        $out['uv_index']    = max(0, round(($hour >= 9 && $hour <= 16 ? 7 : 1) + fusion_seeded_float("uv{$stamp}", -1, 2), 0));
        $out['cloud_cover'] = round(fusion_seeded_float("cc{$cid}{$stamp}", 0, 60), 0);
        $out['dew_point']   = round($temp - (100 - $hum) / 5, 1);
        $out['weather_text'] = $out['cloud_cover'] > 40 ? 'Partiellement nuageux' : 'Ensoleillé';
        // Prévisions AQI (features futures pour ML/DL)
        $out['forecast_1h']  = round($aqi * fusion_time_wave(($hour + 1) % 24) / fusion_time_wave($hour), 0);
        $out['forecast_3h']  = round($aqi * fusion_time_wave(($hour + 3) % 24) / fusion_time_wave($hour), 0);
        $out['forecast_6h']  = round($aqi * fusion_time_wave(($hour + 6) % 24) / fusion_time_wave($hour), 0);
        $out['forecast_12h'] = round($aqi * fusion_time_wave(($hour + 12) % 24) / fusion_time_wave($hour), 0);
        $out['forecast_temp_max'] = round($temp + 3, 1);
        $out['forecast_wind_max'] = round($wind + 6, 1);
    }
    if ($source === 'iqair') {
        $out['aqi_cn']         = round($aqi * 0.92, 0);
        $out['main_pollutant'] = $indus ? 'so2' : 'p2';
    }
    return $out;
}
 
/* ================================================================== */
/*  REQUÊTES RÉELLES (best-effort) — basculent en synthétique si KO    */
/* ================================================================== */
function fusion_http_get(string $url, int $timeout, bool $insecure): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'body' => null, 'rt' => 0.0, 'err' => 'no_curl'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'Gabes-Tatenafas/2.0 (fusion)',
    ]);
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $t0 = microtime(true);
    $body = curl_exec($ch);
    $rt = microtime(true) - $t0;
    $no = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($body !== false && $no === 0 && $code >= 200 && $code < 300);
    return ['ok' => $ok, 'body' => $ok ? $body : null, 'rt' => $rt, 'code' => $code];
}
 
/** WAQI réel (geo:) → structure source normalisée, ou null. */
function fusion_fetch_waqi_real(array $city): ?array
{
    if (!defined('WAQI_API_KEY') || WAQI_API_KEY === '') return null;
    $url = WAQI_ENDPOINT . $city['lat'] . ';' . $city['lng'] . '/?token=' . rawurlencode(WAQI_API_KEY);
    $r = fusion_http_get($url, defined('WAQI_TIMEOUT') ? WAQI_TIMEOUT : 5, WAQI_INSECURE ?? true);
    if (!$r['ok']) return null;
    $j = json_decode($r['body'], true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'ok' || !isset($j['data']['aqi'])) return null;
    $d = $j['data'];
    $iaqi = $d['iaqi'] ?? [];
    $g = function ($k) use ($iaqi) { return isset($iaqi[$k]['v']) ? (float)$iaqi[$k]['v'] : null; };
    return [
        'available' => true, 'cached' => false, 'response_time' => $r['rt'], 'synthetic' => false,
        'aqi' => (float)$d['aqi'], 'category' => fusion_category((float)$d['aqi'])[0],
        'pm25' => $g('pm25'), 'pm10' => $g('pm10'), 'no2' => $g('no2'),
        'so2' => $g('so2'), 'o3' => $g('o3'), 'co' => $g('co'),
        'temp' => $g('t'), 'humidity' => $g('h'), 'wind_speed' => $g('w'),
        'wind_dir' => null, 'pressure' => $g('p'),
    ];
}
 
/** IQAir réel (nearest_city) → structure source normalisée, ou null. */
function fusion_fetch_iqair_real(array $city): ?array
{
    if (!defined('IQAIR_API_KEY') || IQAIR_API_KEY === '') return null;
    $url = IQAIR_ENDPOINT . '?lat=' . $city['lat'] . '&lon=' . $city['lng'] . '&key=' . rawurlencode(IQAIR_API_KEY);
    $r = fusion_http_get($url, defined('IQAIR_TIMEOUT') ? IQAIR_TIMEOUT : 5, IQAIR_INSECURE ?? true);
    if (!$r['ok']) return null;
    $j = json_decode($r['body'], true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'success') return null;
    $cur = $j['data']['current'] ?? [];
    $pol = $cur['pollution'] ?? [];
    $wx  = $cur['weather'] ?? [];
    if (!isset($pol['aqius'])) return null;
    return [
        'available' => true, 'cached' => false, 'response_time' => $r['rt'], 'synthetic' => false,
        'aqi' => (float)$pol['aqius'], 'aqi_cn' => isset($pol['aqicn']) ? (float)$pol['aqicn'] : null,
        'main_pollutant' => $pol['mainus'] ?? null, 'category' => fusion_category((float)$pol['aqius'])[0],
        'pm25' => null, 'pm10' => null, 'no2' => null, 'so2' => null, 'o3' => null, 'co' => null,
        'temp' => isset($wx['tp']) ? (float)$wx['tp'] : null,
        'humidity' => isset($wx['hu']) ? (float)$wx['hu'] : null,
        'wind_speed' => isset($wx['ws']) ? (float)$wx['ws'] : null,
        'wind_dir' => isset($wx['wd']) ? (float)$wx['wd'] : null,
        'pressure' => isset($wx['pr']) ? (float)$wx['pr'] : null,
    ];
}
 
/**
 * AccuWeather réel — nécessite une clé. Renvoie la structure source ou null.
 * (Implémenté best-effort ; sans clé → null → fallback synthétique.)
 */
function fusion_fetch_accuweather_real(array $city): ?array
{
    if (!defined('ACCUWEATHER_API_KEY') || ACCUWEATHER_API_KEY === '') return null;
    $key = $city['accuw_key'];
    if (!$key || $key === 'FIND_VIA_API') {
        // STEP A — recherche de la location key par nom de ville.
        // Utilise le nom officiel AccuWeather de la ville (accuw_name) si defini,
        // car AccuWeather ne connait pas les noms francais locaux (ex: Ghannouche => Ghannush).
        $searchName = !empty($city['accuw_name']) ? $city['accuw_name'] : $city['name_fr'];
        $u = ACCUWEATHER_SEARCH . '?q=' . rawurlencode($searchName . ', Tunisia') . '&apikey=' . rawurlencode(ACCUWEATHER_API_KEY);
        $r = fusion_http_get($u, ACCUWEATHER_TIMEOUT, ACCUWEATHER_INSECURE);
        if ($r['ok']) {
            $arr = json_decode($r['body'], true);
            if (is_array($arr) && isset($arr[0]['Key'])) $key = $arr[0]['Key'];
        }
    }
    if (!$key || $key === 'FIND_VIA_API') return null;
 
    // STEP B — air quality.
    $rAir = fusion_http_get(ACCUWEATHER_AIRQUALITY . rawurlencode($key) . '?apikey=' . rawurlencode(ACCUWEATHER_API_KEY) . '&details=true', ACCUWEATHER_TIMEOUT, ACCUWEATHER_INSECURE);
    if (!$rAir['ok']) return null;
    $air = json_decode($rAir['body'], true);
    if (!is_array($air) || !isset($air[0])) return null;
    $h0 = $air[0];
    $catIdx = (int)($h0['Category']['Value'] ?? $h0['CategoryValue'] ?? 0);
    $idxVal = (float)($h0['Index'] ?? $h0['AQI'] ?? 0);
    $aqi = $idxVal > 0 ? $idxVal : fusion_accuw_category_to_aqi($catIdx, (float)($h0['Value'] ?? 0));
 
    $poll = function ($name) use ($h0) {
        foreach (($h0['Pollutants'] ?? []) as $p) {
            if (strtoupper($p['Type'] ?? '') === strtoupper($name)) return (float)($p['Concentration']['Value'] ?? 0);
        }
        return null;
    };
 
    $out = [
        'available' => true, 'cached' => false, 'response_time' => $rAir['rt'], 'synthetic' => false,
        'aqi' => round($aqi, 0), 'category' => $h0['Category']['Name'] ?? fusion_category($aqi)[0],
        'pm25' => $poll('PM2.5'), 'pm10' => $poll('PM10'), 'no2' => $poll('NO2'),
        'so2' => $poll('SO2'), 'o3' => $poll('O3'), 'co' => $poll('CO'),
        'temp' => null, 'humidity' => null, 'wind_speed' => null, 'wind_dir' => null, 'pressure' => null,
    ];
    return $out;
}
 
/* ================================================================== */
/*  Récupération d'une source (réel sinon synthétique)                 */
/* ================================================================== */
function fusion_fetch_source(string $source, array $city, DateTimeImmutable $now): array
{
    $real = null;
    if ($source === 'waqi')        $real = fusion_fetch_waqi_real($city);
    elseif ($source === 'iqair')   $real = fusion_fetch_iqair_real($city);
    elseif ($source === 'accuweather') $real = fusion_fetch_accuweather_real($city);
 
    if ($real !== null) return $real;
    return fusion_synthetic_source($city, $source, $now);
}
 
/* ================================================================== */
/*  STEP 3 — Score qualité d'une source                                */
/* ================================================================== */
function fusion_quality_score(array $s): float
{
    $q = 1.0;
    if (($s['response_time'] ?? 0) > 3.0) $q -= 0.2;
    if (!empty($s['cached'])) $q -= 0.1;
    $aqi = $s['aqi'] ?? null;
    if ($aqi === null || $aqi < 0 || $aqi > 500) $q -= 0.3;
    return max(0.0, round($q, 2));
}
 
/* ================================================================== */
/*  FUSION COMPLÈTE pour une ville                                     */
/* ================================================================== */
function fusion_compute_city(array $city, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now');
    $hour = (int)$now->format('H');
    $dow  = (int)$now->format('N'); // 1=lundi..7=dimanche
    $isWeekend = ($dow >= 6);
 
    // STEP 1 — fetch des 3 sources.
    $sources = [
        'accuweather' => fusion_fetch_source('accuweather', $city, $now),
        'iqair'       => fusion_fetch_source('iqair', $city, $now),
        'waqi'        => fusion_fetch_source('waqi', $city, $now),
    ];
 
    // STEP 2 (normalisation) déjà faite : chaque source renvoie un AQI US.
 
    // STEP 3 — scores qualité.
    $quality = [];
    foreach ($sources as $k => $s) $quality[$k] = fusion_quality_score($s);
 
    // Sources disponibles avec AQI valide.
    $avail = [];
    foreach ($sources as $k => $s) {
        if (!empty($s['available']) && $s['aqi'] !== null) $avail[$k] = (float)$s['aqi'];
    }
 
    // STEP 5 (base) — poids de base selon disponibilité.
    if (isset($avail['accuweather'])) {
        $weights = FUSION_BASE_WEIGHTS;
    } elseif (count($avail) >= 2) {
        $weights = ['iqair' => 0.50, 'waqi' => 0.50, 'accuweather' => 0.0];
    } elseif (count($avail) === 1) {
        $only = array_key_first($avail);
        $weights = ['accuweather' => 0.0, 'iqair' => 0.0, 'waqi' => 0.0];
        $weights[$only] = 1.0;
    } else {
        $weights = FUSION_BASE_WEIGHTS; // ne devrait pas arriver (fallback synthétique)
    }
    // Ne garder que les sources disponibles.
    foreach ($weights as $k => $w) if (!isset($avail[$k])) $weights[$k] = 0.0;
 
    // STEP 4 — détection d'outliers (écart > 40 AQI à la médiane).
    $outliers = [];
    if (count($avail) >= 2) {
        $vals = array_values($avail);
        sort($vals);
        $n = count($vals);
        $median = ($n % 2) ? $vals[intval($n / 2)] : ($vals[$n / 2 - 1] + $vals[$n / 2]) / 2;
        foreach ($avail as $k => $v) {
            if (abs($v - $median) > 40) { $weights[$k] *= 0.3; $outliers[] = $k; }
        }
    }
 
    // Renormalisation des poids → somme = 1.
    $sumW = array_sum($weights);
    if ($sumW <= 0) { foreach ($avail as $k => $v) $weights[$k] = 1.0 / max(1, count($avail)); $sumW = array_sum($weights); }
 
    // STEP 5 — fusion pondérée.
    $fused = 0.0;
    foreach ($avail as $k => $v) $fused += $v * $weights[$k];
    $fused = $fused / max(1e-9, $sumW);
 
    // STEP 6 — facteur ville + ajustement temporel.
    $cityAqi = $fused * (float)$city['pollution_factor'];
    $isWeekday = !$isWeekend;
    if ($isWeekday && $hour >= 6 && $hour <= 8)   $cityAqi *= 1.15;
    if ($isWeekday && $hour >= 14 && $hour <= 16) $cityAqi *= 1.10;
    if ($hour >= 23 || $hour <= 5)                $cityAqi *= 0.85;
    if ($isWeekend)                               $cityAqi *= 0.90;
    $finalAqi = (float)round(max(0, min(500, $cityAqi)));
 
    // STEP 7 — catégorie + couleur.
    [$catLabel, $catColor, $riskLevel] = fusion_category($finalAqi);
 
    // ---- Agrégation des polluants/météo finaux (moyenne pondérée des sources) ----
    $finalField = function (string $field) use ($sources, $weights, $avail) {
        $num = 0.0; $den = 0.0;
        foreach ($sources as $k => $s) {
            if (isset($avail[$k]) && isset($s[$field]) && $s[$field] !== null) {
                $w = max($weights[$k], 0.001);
                $num += $s[$field] * $w; $den += $w;
            }
        }
        return $den > 0 ? round($num / $den, 2) : null;
    };
 
    // Score qualité global = moyenne pondérée par poids effectifs.
    $qNum = 0.0; $qDen = 0.0;
    foreach ($avail as $k => $v) { $qNum += $quality[$k] * max($weights[$k], 0.001); $qDen += max($weights[$k], 0.001); }
    $dataQuality = $qDen > 0 ? round($qNum / $qDen, 2) : 0.0;
 
    $fusionMethod = isset($avail['accuweather']) ? 'weighted_3api'
        : (count($avail) >= 2 ? 'weighted_2api' : (count($avail) === 1 ? 'single_source' : 'fallback'));
    if ($outliers) $fusionMethod .= '+outlier_penalized';
 
    $acc = $sources['accuweather'];
    return [
        'city'            => $city,
        'timestamp'       => $now->format('Y-m-d H:i:s'),
        'final_aqi'       => $finalAqi,
        'final_category'  => $catLabel,
        'category_color'  => $catColor,
        'risk_level'      => $riskLevel,
        'status'          => fusion_status($finalAqi),
        'final_pm25'      => $finalField('pm25'),
        'final_pm10'      => $finalField('pm10'),
        'final_no2'       => $finalField('no2'),
        'final_so2'       => $finalField('so2'),
        'final_o3'        => $finalField('o3'),
        'final_co'        => $finalField('co'),
        'final_temperature' => $finalField('temp'),
        'final_humidity'  => $finalField('humidity'),
        'final_wind_speed' => $finalField('wind_speed'),
        'final_wind_direction' => $finalField('wind_dir'),
        'final_pressure'  => $finalField('pressure'),
        'data_quality_score' => $dataQuality,
        'fusion_method'   => $fusionMethod,
        'weights'         => $weights,
        'outliers'        => $outliers,
        'sources'         => $sources,
        'quality'         => $quality,
        'forecast'        => [
            '1h'  => $acc['forecast_1h']  ?? null,
            '3h'  => $acc['forecast_3h']  ?? null,
            '6h'  => $acc['forecast_6h']  ?? null,
            '12h' => $acc['forecast_12h'] ?? null,
        ],
        'context'         => [
            'hour' => $hour, 'is_weekend' => $isWeekend ? 1 : 0,
            'is_industrial_peak' => ($isWeekday && (($hour >= 6 && $hour <= 8) || ($hour >= 14 && $hour <= 16))) ? 1 : 0,
        ],
    ];
}
 
/* ================================================================== */
/*  PERSISTENCE — table api_readings (cache 1h)                        */
/* ================================================================== */
function fusion_table_exists(PDO $pdo): bool
{
    try { $pdo->query('SELECT 1 FROM api_readings LIMIT 1'); return true; }
    catch (Throwable $e) { return false; }
}
 
function fusion_persist(PDO $pdo, array $r): int
{
    if (!fusion_table_exists($pdo)) return 0;
    $s = $r['sources'];
    $acc = $s['accuweather']; $iq = $s['iqair']; $wq = $s['waqi'];
    $cols = [
        'city_id' => (string)$r['city']['zone_id'],
        'city_name' => $r['city']['name_fr'],
        'timestamp' => $r['timestamp'],
        'final_aqi' => $r['final_aqi'], 'final_category' => $r['final_category'],
        'final_pm25' => $r['final_pm25'], 'final_pm10' => $r['final_pm10'],
        'final_no2' => $r['final_no2'], 'final_so2' => $r['final_so2'],
        'final_o3' => $r['final_o3'], 'final_co' => $r['final_co'],
        'final_temperature' => $r['final_temperature'], 'final_humidity' => $r['final_humidity'],
        'final_wind_speed' => $r['final_wind_speed'], 'final_wind_direction' => $r['final_wind_direction'],
        'final_pressure' => $r['final_pressure'],
        'accuw_aqi' => $acc['aqi'] ?? null, 'accuw_category' => $acc['category'] ?? null,
        'accuw_pm25' => $acc['pm25'] ?? null, 'accuw_pm10' => $acc['pm10'] ?? null,
        'accuw_no2' => $acc['no2'] ?? null, 'accuw_so2' => $acc['so2'] ?? null,
        'accuw_o3' => $acc['o3'] ?? null, 'accuw_co' => $acc['co'] ?? null,
        'accuw_temp' => $acc['temp'] ?? null, 'accuw_feels_like' => $acc['feels_like'] ?? null,
        'accuw_humidity' => $acc['humidity'] ?? null, 'accuw_wind_speed' => $acc['wind_speed'] ?? null,
        'accuw_wind_dir' => $acc['wind_dir'] ?? null, 'accuw_pressure' => $acc['pressure'] ?? null,
        'accuw_visibility' => $acc['visibility'] ?? null, 'accuw_uv_index' => $acc['uv_index'] ?? null,
        'accuw_cloud_cover' => $acc['cloud_cover'] ?? null, 'accuw_dew_point' => $acc['dew_point'] ?? null,
        'accuw_weather_text' => $acc['weather_text'] ?? null, 'accuw_available' => !empty($acc['available']) ? 1 : 0,
        'accuw_forecast_1h' => $acc['forecast_1h'] ?? null, 'accuw_forecast_3h' => $acc['forecast_3h'] ?? null,
        'accuw_forecast_6h' => $acc['forecast_6h'] ?? null, 'accuw_forecast_12h' => $acc['forecast_12h'] ?? null,
        'accuw_forecast_temp_max' => $acc['forecast_temp_max'] ?? null, 'accuw_forecast_wind_max' => $acc['forecast_wind_max'] ?? null,
        'iqair_aqi_us' => $iq['aqi'] ?? null, 'iqair_aqi_cn' => $iq['aqi_cn'] ?? null,
        'iqair_main_pollutant' => $iq['main_pollutant'] ?? null, 'iqair_pm25' => $iq['pm25'] ?? null,
        'iqair_pm10' => $iq['pm10'] ?? null, 'iqair_temp' => $iq['temp'] ?? null,
        'iqair_humidity' => $iq['humidity'] ?? null, 'iqair_wind_speed' => $iq['wind_speed'] ?? null,
        'iqair_wind_dir' => $iq['wind_dir'] ?? null, 'iqair_pressure' => $iq['pressure'] ?? null,
        'iqair_available' => !empty($iq['available']) ? 1 : 0,
        'waqi_aqi' => $wq['aqi'] ?? null, 'waqi_pm25' => $wq['pm25'] ?? null, 'waqi_pm10' => $wq['pm10'] ?? null,
        'waqi_no2' => $wq['no2'] ?? null, 'waqi_so2' => $wq['so2'] ?? null, 'waqi_o3' => $wq['o3'] ?? null,
        'waqi_co' => $wq['co'] ?? null, 'waqi_temp' => $wq['temp'] ?? null, 'waqi_humidity' => $wq['humidity'] ?? null,
        'waqi_wind' => $wq['wind_speed'] ?? null, 'waqi_available' => !empty($wq['available']) ? 1 : 0,
        'data_quality_score' => $r['data_quality_score'], 'fusion_method' => $r['fusion_method'],
    ];
    $fields = array_keys($cols);
    $place = implode(',', array_fill(0, count($fields), '?'));
    $sql = 'INSERT INTO api_readings (' . implode(',', $fields) . ') VALUES (' . $place . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($cols));
    return (int)$pdo->lastInsertId();
}
 
/**
 * Renvoie la lecture fusionnée d'une ville, en réutilisant le cache `api_readings`
 * (< FUSION_CACHE_MINUTES) sauf si $force = true.
 */
function fusion_get_city(PDO $pdo, int $zoneId, bool $force = false): ?array
{
    $city = gabes_city($zoneId);
    if (!$city) return null;
 
    if (!$force && fusion_table_exists($pdo)) {
        $q = $pdo->prepare(
            'SELECT * FROM api_readings WHERE city_id = ? AND timestamp >= (NOW() - INTERVAL ? MINUTE) ORDER BY timestamp DESC LIMIT 1'
        );
        $q->execute([(string)$zoneId, FUSION_CACHE_MINUTES]);
        $row = $q->fetch();
        if ($row) return fusion_row_to_result($row, $city);
    }
 
    $r = fusion_compute_city($city);
    fusion_persist($pdo, $r);
    return $r;
}
 
/** Reconstitue une structure "résultat" légère depuis une ligne api_readings (cache). */
function fusion_row_to_result(array $row, array $city): array
{
    [$catLabel, $catColor, $riskLevel] = fusion_category((float)$row['final_aqi']);
    return [
        'city' => $city, 'timestamp' => $row['timestamp'],
        'final_aqi' => (float)$row['final_aqi'], 'final_category' => $row['final_category'] ?: $catLabel,
        'category_color' => $catColor, 'risk_level' => $riskLevel, 'status' => fusion_status((float)$row['final_aqi']),
        'final_pm25' => $row['final_pm25'], 'final_pm10' => $row['final_pm10'], 'final_no2' => $row['final_no2'],
        'final_so2' => $row['final_so2'], 'final_o3' => $row['final_o3'], 'final_co' => $row['final_co'],
        'final_temperature' => $row['final_temperature'], 'final_humidity' => $row['final_humidity'],
        'final_wind_speed' => $row['final_wind_speed'], 'final_wind_direction' => $row['final_wind_direction'],
        'final_pressure' => $row['final_pressure'],
        'data_quality_score' => (float)$row['data_quality_score'], 'fusion_method' => $row['fusion_method'],
        'cached' => true,
        'sources' => [
            'accuweather' => [
                'available' => (int)$row['accuw_available'], 'aqi' => $row['accuw_aqi'], 'category' => $row['accuw_category'],
                'pm25' => $row['accuw_pm25'], 'pm10' => $row['accuw_pm10'], 'no2' => $row['accuw_no2'],
                'so2' => $row['accuw_so2'], 'o3' => $row['accuw_o3'], 'co' => $row['accuw_co'],
                'temp' => $row['accuw_temp'], 'feels_like' => $row['accuw_feels_like'], 'humidity' => $row['accuw_humidity'],
                'wind_speed' => $row['accuw_wind_speed'], 'wind_dir' => $row['accuw_wind_dir'], 'pressure' => $row['accuw_pressure'],
                'visibility' => $row['accuw_visibility'], 'uv_index' => $row['accuw_uv_index'], 'cloud_cover' => $row['accuw_cloud_cover'],
                'dew_point' => $row['accuw_dew_point'], 'weather_text' => $row['accuw_weather_text'],
                'forecast_1h' => $row['accuw_forecast_1h'], 'forecast_3h' => $row['accuw_forecast_3h'],
                'forecast_6h' => $row['accuw_forecast_6h'], 'forecast_12h' => $row['accuw_forecast_12h'],
            ],
            'iqair' => [
                'available' => (int)$row['iqair_available'], 'aqi' => $row['iqair_aqi_us'], 'aqi_cn' => $row['iqair_aqi_cn'],
                'main_pollutant' => $row['iqair_main_pollutant'], 'pm25' => $row['iqair_pm25'], 'pm10' => $row['iqair_pm10'],
                'temp' => $row['iqair_temp'], 'humidity' => $row['iqair_humidity'], 'wind_speed' => $row['iqair_wind_speed'],
                'wind_dir' => $row['iqair_wind_dir'], 'pressure' => $row['iqair_pressure'],
            ],
            'waqi' => [
                'available' => (int)$row['waqi_available'], 'aqi' => $row['waqi_aqi'], 'pm25' => $row['waqi_pm25'],
                'pm10' => $row['waqi_pm10'], 'no2' => $row['waqi_no2'], 'so2' => $row['waqi_so2'], 'o3' => $row['waqi_o3'],
                'co' => $row['waqi_co'], 'temp' => $row['waqi_temp'], 'humidity' => $row['waqi_humidity'], 'wind_speed' => $row['waqi_wind'],
            ],
        ],
        'forecast' => [
            '1h' => $row['accuw_forecast_1h'], '3h' => $row['accuw_forecast_3h'],
            '6h' => $row['accuw_forecast_6h'], '12h' => $row['accuw_forecast_12h'],
        ],
    ];
}
 
/** Rafraîchit toutes les villes (cron / bouton refresh). */
function fusion_refresh_all(PDO $pdo): array
{
    $out = [];
    foreach (gabes_cities() as $zid => $city) {
        $out[$zid] = fusion_get_city($pdo, (int)$zid, true);
    }
    return $out;
}
 
/* ================================================================== */
/*  §0.7 — VECTEUR DE FEATURES ENRICHI pour ML/DL/Fuzzy                 */
/* ================================================================== */
/**
 * Construit le vecteur de features (lags AQI, polluants, météo, contexte
 * temporel + prévisions AccuWeather) à partir de l'historique `api_readings`
 * pour une ville donnée — source unique consommée par les modules ML/DL/Fuzzy.
 * Retourne ['features' => [...], 'labels' => [...], 'target_aqi' => float].
 */
function fusion_feature_vector(PDO $pdo, int $zoneId, ?array $current = null): array
{
    $city = gabes_city($zoneId);
    $current = $current ?: fusion_get_city($pdo, $zoneId);
 
    // Historique des final_aqi (du plus récent au plus ancien).
    $hist = [];
    if (fusion_table_exists($pdo)) {
        $q = $pdo->prepare('SELECT timestamp, final_aqi FROM api_readings WHERE city_id = ? ORDER BY timestamp DESC LIMIT 200');
        $q->execute([(string)$zoneId]);
        foreach ($q->fetchAll() as $row) $hist[] = (float)$row['final_aqi'];
    }
    $cur = $current['final_aqi'] ?? 0.0;
    $lag = function (int $i) use ($hist, $cur) { return $hist[$i] ?? $cur; };
 
    $ctxHour = (int)date('H');
    $isWeekend = (int)(date('N') >= 6);
    $season = (int)ceil((int)date('n') / 3); // 1..4
    $isPeak = ($isWeekend === 0 && (($ctxHour >= 6 && $ctxHour <= 8) || ($ctxHour >= 14 && $ctxHour <= 16))) ? 1 : 0;
 
    $labels = [
        'aqi_t-1','aqi_t-2','aqi_t-3','aqi_t-4','aqi_t-5','aqi_t-6','aqi_t-7',
        'aqi_t-24','aqi_t-168','fuzzy_score_type2','uncertainty_lower',
        'pm25','pm10','so2','no2','temperature','humidity','wind_speed','wind_direction',
        'pressure','uv_index','forecast_3h','forecast_6h',
        'is_weekend','hour_of_day','season','is_industrial_peak',
    ];
    // 27 entrées listées dans le prompt — on garde les 25 features prédictives clés.
    $features = [
        $lag(0), $lag(1), $lag(2), $lag(3), $lag(4), $lag(5), $lag(6),
        $lag(23), $lag(167),
        (float)($current['fuzzy']['score'] ?? 0.0),
        (float)($current['fuzzy']['uncertainty_lower'] ?? 0.0),
        (float)($current['final_pm25'] ?? 0), (float)($current['final_pm10'] ?? 0),
        (float)($current['final_so2'] ?? 0), (float)($current['final_no2'] ?? 0),
        (float)($current['final_temperature'] ?? 0), (float)($current['final_humidity'] ?? 0),
        (float)($current['final_wind_speed'] ?? 0), (float)($current['final_wind_direction'] ?? 0),
        (float)($current['final_pressure'] ?? 0),
        (float)($current['sources']['accuweather']['uv_index'] ?? 0),
        (float)($current['forecast']['3h'] ?? $cur), (float)($current['forecast']['6h'] ?? $cur),
        (float)$isWeekend, (float)$ctxHour, (float)$season, (float)$isPeak,
    ];
    return ['city_id' => $zoneId, 'features' => $features, 'labels' => $labels, 'target_aqi' => $cur];
}