<?php
/**
 * B10 — Timelapse de la pollution sur les N derniers jours.
 *
 * GET ?days=7   → renvoie pour chaque zone une série journalière
 *                  utilisable par le slider sur la carte.
 *
 * Réponse :
 * {
 *   ok: true, days: 7,
 *   timeline: [ "2026-04-25", "2026-04-26", ... ],
 *   zones: [
 *     { id, name, name_ar, lat, lng, scores: [12, 18, 30, 70, 65, ...] },
 *     ...
 *   ]
 * }
 */

require_once __DIR__ . '/../lib/helpers.php';

$pdo  = db();

/* UPGRADE v9 — Part 54.1 : granularité horaire optionnelle (max 72h). */
$granularity = (($_GET['granularity'] ?? 'day') === 'hour') ? 'hour' : 'day';
if ($granularity === 'hour') {
    $hours = max(6, min(72, (int)($_GET['hours'] ?? 48)));
    $days  = (int)ceil($hours / 24);
    $timeline = [];
    for ($i = $hours - 1; $i >= 0; $i--) {
        $timeline[] = date('Y-m-d H:00:00', strtotime("-$i hours"));
    }
} else {
    $days = max(3, min(30, (int)($_GET['days'] ?? 7)));
    $timeline = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $timeline[] = date('Y-m-d', strtotime("-$i days"));
    }
}

$zones = $pdo->query("SELECT id, name, name_ar, lat, lng FROM zones ORDER BY id ASC")->fetchAll();

/* Récupérer toutes les valeurs en une requête */
if ($granularity === 'hour') {
    $stmt = $pdo->prepare(
        "SELECT zone_id, DATE_FORMAT(computed_at, '%Y-%m-%d %H:00:00') AS d, AVG(score) AS avg_score
         FROM risk_scores
         WHERE computed_at >= (NOW() - INTERVAL ? HOUR)
         GROUP BY zone_id, DATE_FORMAT(computed_at, '%Y-%m-%d %H:00:00')"
    );
    $stmt->execute([$hours]);
} else {
    $stmt = $pdo->prepare(
        "SELECT zone_id, DATE(computed_at) AS d, AVG(score) AS avg_score
         FROM risk_scores
         WHERE computed_at >= CURDATE() - INTERVAL ? DAY
         GROUP BY zone_id, DATE(computed_at)"
    );
    $stmt->execute([$days]);
}
$rows = $stmt->fetchAll();

$byZone = [];
foreach ($rows as $r) {
    $byZone[(int)$r['zone_id']][$r['d']] = (int)round((float)$r['avg_score']);
}

/* Compléter chaque zone — si pas de données pour un jour, on prend la dernière connue */
$out = [];
foreach ($zones as $z) {
    $zid = (int)$z['id'];
    $perDay = $byZone[$zid] ?? [];
    $scores = [];
    $last = (int)$pdo->query("SELECT pollution_level FROM zones WHERE id = $zid")->fetchColumn();
    foreach ($timeline as $d) {
        if (isset($perDay[$d])) {
            $last = $perDay[$d];
        }
        $scores[] = $last;
    }
    $out[] = [
        'id'      => $zid,
        'name'    => $z['name'],
        'name_ar' => $z['name_ar'],
        'lat'     => $z['lat'],
        'lng'     => $z['lng'],
        'scores'  => $scores,
    ];
}

json_response([
    'ok'       => true,
    'days'     => $days,
    'granularity' => $granularity,
    'timeline' => $timeline,
    'zones'    => $out,
]);
