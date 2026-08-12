<?php
declare(strict_types=1);

/**
 * PART 32 — Corrélation anomalies (modèle) × signalements citoyens.
 *
 * Relie chaque anomalie récente (`anomaly_events`) aux signalements citoyens
 * (`reports`, table réelle du projet — il n'y a pas de table `citizen_reports`)
 * proches dans le TEMPS et dans l'ESPACE. Quand le modèle ET les citoyens
 * signalent la même chose au même endroit, on applique un `confidence_boost`
 * et on enrichit l'alerte associée.
 *
 * Dégradation gracieuse : si une table/colonne manque, on log et on renvoie 0
 * sans jamais casser l'appelant.
 */

require_once __DIR__ . '/../config/database.php';

if (!function_exists('acorr_haversine_km')) {
    function acorr_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

/**
 * Parcourt les anomalies récentes et cherche les signalements citoyens dans la
 * fenêtre temporelle + rayon GPS. Chaque correspondance est journalisée dans
 * `anomaly_citizen_links` (idempotent) et déclenche un boost de confiance.
 *
 * @return int nombre de liens créés lors de cet appel
 */
function link_anomalies_to_reports(PDO $pdo, int $windowMinutes = 60, float $radiusKm = 3.0): int
{
    $created = 0;
    try {
        // Anomalies des dernières 24 h. On tolère l'absence de colonnes.
        $anoms = $pdo->query(
            "SELECT * FROM anomaly_events
             WHERE COALESCE(detected_at, created_at, NOW()) >= NOW() - INTERVAL 1 DAY
             ORDER BY id DESC LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[anomaly_correlation] anomaly_events indisponible: ' . $e->getMessage());
        return 0;
    }

    // Coordonnées des zones (pour la distance).
    $zoneGeo = [];
    try {
        foreach ($pdo->query('SELECT id, lat, lng FROM zones')->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $zoneGeo[(int)$z['id']] = ['lat' => (float)$z['lat'], 'lng' => (float)$z['lng']];
        }
    } catch (Throwable $e) {
        // pas de lat/lng -> on retombe sur une corrélation purement temporelle
    }

    foreach ($anoms as $a) {
        $anomId  = (int)($a['id'] ?? 0);
        $zoneId  = (int)($a['zone_id'] ?? 0);
        $anomTs  = $a['detected_at'] ?? ($a['created_at'] ?? null);
        if (!$anomId || !$anomTs) continue;

        try {
            $q = $pdo->prepare(
                "SELECT id, zone_id, reported_at FROM reports
                 WHERE reported_at BETWEEN (? - INTERVAL ? MINUTE) AND (? + INTERVAL ? MINUTE)"
            );
            $q->execute([$anomTs, $windowMinutes, $anomTs, $windowMinutes]);
            $reports = $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[anomaly_correlation] reports query: ' . $e->getMessage());
            continue;
        }

        foreach ($reports as $r) {
            $rId    = (int)$r['id'];
            $rZone  = (int)($r['zone_id'] ?? 0);

            // Distance spatiale (si géo dispo). Même zone => 0 km.
            $distKm = 0.0;
            if ($zoneId && $rZone && $zoneId !== $rZone) {
                if (isset($zoneGeo[$zoneId], $zoneGeo[$rZone])) {
                    $distKm = acorr_haversine_km(
                        $zoneGeo[$zoneId]['lat'], $zoneGeo[$zoneId]['lng'],
                        $zoneGeo[$rZone]['lat'], $zoneGeo[$rZone]['lng']
                    );
                } else {
                    $distKm = $radiusKm + 1; // inconnu -> hors rayon, on saute
                }
            }
            if ($distKm > $radiusKm) continue;

            $timeDist = (int) round(abs(strtotime((string)$anomTs) - strtotime((string)$r['reported_at'])) / 60);
            // Boost décroissant avec la distance temps/espace.
            $boost = round(0.25 * (1 - $timeDist / max(1, $windowMinutes))
                         + 0.15 * (1 - $distKm / max(0.001, $radiusKm)), 3);
            $boost = max(0.0, min(0.4, $boost));

            // Idempotence : ne pas recréer un lien identique.
            try {
                $exists = $pdo->prepare(
                    'SELECT id FROM anomaly_citizen_links WHERE anomaly_id = ? AND report_id = ? LIMIT 1'
                );
                $exists->execute([$anomId, $rId]);
                if ($exists->fetchColumn()) continue;

                $pdo->prepare(
                    'INSERT INTO anomaly_citizen_links
                     (anomaly_id, report_id, time_distance_minutes, spatial_distance_km, confidence_boost, linked_at)
                     VALUES (?,?,?,?,?,NOW())'
                )->execute([$anomId, $rId, $timeDist, round($distKm, 2), $boost]);
                $created++;
            } catch (Throwable $e) {
                error_log('[anomaly_correlation] link insert: ' . $e->getMessage());
                continue;
            }
        }

        // Combien de citoyens ont confirmé cette anomalie ?
        try {
            $c = $pdo->prepare('SELECT COUNT(*) FROM anomaly_citizen_links WHERE anomaly_id = ?');
            $c->execute([$anomId]);
            $confirmations = (int)$c->fetchColumn();
        } catch (Throwable $e) {
            $confirmations = 0;
        }

        if ($confirmations > 0) {
            // Enrichir l'alerte smart_alerts liée à la zone (best effort).
            try {
                $note = "Confirmé par {$confirmations} signalement(s) citoyen(s).";
                $upd = $pdo->prepare(
                    "UPDATE smart_alerts
                     SET description = CONCAT(COALESCE(description,''), ' ', ?)
                     WHERE zone_id = ? AND created_at >= NOW() - INTERVAL 1 DAY
                       AND (description IS NULL OR description NOT LIKE '%signalement(s) citoyen%')"
                );
                $upd->execute([$note, $zoneId]);
            } catch (Throwable $e) {
                // smart_alerts peut avoir un schéma différent — non bloquant
            }
        }
    }

    return $created;
}
