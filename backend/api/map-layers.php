<?php
declare(strict_types=1);
/**
 * UPGRADE v9 — Part 55.3/55.4 : données des nouvelles couches de la carte.
 * GET (défaut) => renvoie schools, safe_points, densité des signalements 24h,
 *                 et confiance des données par zone.
 * Dégradation gracieuse : chaque bloc est isolé ; tables absentes => [].
 */

require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();

function ml_safe(PDO $pdo, string $sql, array $p = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

/* --- Écoles (Part 55.1) --- */
$schools = ml_safe($pdo, "SELECT id, name, zone_id, lat, lng, current_status FROM schools WHERE lat IS NOT NULL");

/* --- Zones sûres / points de refuge (Part 55.2) --- */
$safe = ml_safe($pdo, "SELECT id, name, type, zone_id, lat, lng, has_filtration FROM safe_points WHERE lat IS NOT NULL");

/* --- Densité des signalements citoyens sur 24h, groupé par zone (Part 55.3) --- */
$reports = [];
$rows = ml_safe($pdo,
    "SELECT r.zone_id, COUNT(*) c, z.lat, z.lng, z.name
     FROM reports r LEFT JOIN zones z ON z.id = r.zone_id
     WHERE r.reported_at >= NOW() - INTERVAL 24 HOUR AND z.lat IS NOT NULL
     GROUP BY r.zone_id, z.lat, z.lng, z.name");
foreach ($rows as $r) {
    $reports[] = [
        'zone_id' => (int)$r['zone_id'],
        'name'    => $r['name'],
        'lat'     => (float)$r['lat'],
        'lng'     => (float)$r['lng'],
        'count'   => (int)$r['c'],
    ];
}

/* --- Confiance des données par zone (Part 55.4) --- */
// Combine : score de confiance moyen (api_verification_log) + nb de sources concordantes.
$trust = [];
$zones = ml_safe($pdo, "SELECT id, name, lat, lng FROM zones WHERE lat IS NOT NULL");
foreach ($zones as $z) {
    $zid = (int)$z['id'];
    $agg = ml_safe($pdo,
        "SELECT AVG(trust_score) ts, COUNT(DISTINCT source) srcs
         FROM api_verification_log
         WHERE zone_id = ? AND verified_at >= NOW() - INTERVAL 24 HOUR", [$zid]);
    $ts   = isset($agg[0]['ts']) && $agg[0]['ts'] !== null ? (float)$agg[0]['ts'] : null;
    $srcs = isset($agg[0]['srcs']) ? (int)$agg[0]['srcs'] : 0;
    // Confiance combinée 0..1 : moitié score, moitié diversité des sources (max 3).
    $conf = $ts !== null ? (0.6 * $ts + 0.4 * min(1.0, $srcs / 3.0)) : min(1.0, $srcs / 3.0);
    $trust[] = [
        'zone_id'    => $zid,
        'name'       => $z['name'],
        'lat'        => (float)$z['lat'],
        'lng'        => (float)$z['lng'],
        'confidence' => round($conf, 2),
        'sources'    => $srcs,
    ];
}

json_response([
    'ok'          => true,
    'schools'     => $schools,
    'safe_points' => $safe,
    'reports'     => $reports,
    'trust'       => $trust,
]);
