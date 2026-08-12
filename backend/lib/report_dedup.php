<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 49.1 : détection de doublons / signalements suspects.
 * Dégradation gracieuse : si la table report_duplicate_clusters manque ou
 * qu'aucune position GPS n'est fournie, la fonction renvoie null (aucun cluster)
 * et le signalement suit son flux normal.
 */

require_once __DIR__ . '/helpers.php';

if (!function_exists('_dedup_haversine_km')) {
    function _dedup_haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

/**
 * Cherche (ou crée) un cluster de doublons pour un nouveau signalement.
 * @param array $newReport doit contenir zone_id ; lat/lng optionnels ; id optionnel.
 * @return int|null id du cluster si le signalement est un doublon regroupé, sinon null.
 */
function find_duplicate_cluster(PDO $pdo, array $newReport, int $windowMinutes = 30, float $radiusKm = 0.5): ?int
{
    try {
        $zoneId = (string)($newReport['zone_id'] ?? '');
        if ($zoneId === '') return null;
        $lat = isset($newReport['lat']) ? (float)$newReport['lat'] : null;
        $lng = isset($newReport['lng']) ? (float)$newReport['lng'] : null;
        $reportId = isset($newReport['id']) ? (int)$newReport['id'] : 0;
        $now = date('Y-m-d H:i:s');

        // Cherche un cluster récent dans la même zone.
        $st = $pdo->prepare(
            "SELECT id, report_ids, merged_count, last_report_at
             FROM report_duplicate_clusters
             WHERE zone_id = ?
               AND last_report_at >= (NOW() - INTERVAL ? MINUTE)
             ORDER BY last_report_at DESC LIMIT 5"
        );
        $st->execute([$zoneId, $windowMinutes]);
        $rows = $st->fetchAll();

        foreach ($rows as $c) {
            // Si GPS disponible des deux côtés, filtre par rayon ; sinon on regroupe par zone+fenêtre.
            $sameSpot = true;
            if ($lat !== null && $lng !== null && !empty($c['cluster_key'])) {
                $parts = explode(',', (string)$c['cluster_key']);
                if (count($parts) === 2) {
                    $sameSpot = _dedup_haversine_km($lat, $lng, (float)$parts[0], (float)$parts[1]) <= $radiusKm;
                }
            }
            if (!$sameSpot) continue;

            // Doublon : incrémente merged_count au lieu de créer un signalement isolé.
            $ids = trim((string)$c['report_ids']);
            if ($reportId) $ids = $ids === '' ? (string)$reportId : $ids . ',' . $reportId;
            $up = $pdo->prepare(
                "UPDATE report_duplicate_clusters
                 SET merged_count = merged_count + 1, last_report_at = ?, report_ids = ?
                 WHERE id = ?"
            );
            $up->execute([$now, $ids, (int)$c['id']]);
            return (int)$c['id'];
        }

        // Aucun cluster : on en crée un nouveau (ce signalement en est la tête).
        $key = ($lat !== null && $lng !== null) ? sprintf('%.5f,%.5f', $lat, $lng) : ('zone:' . $zoneId);
        $ins = $pdo->prepare(
            "INSERT INTO report_duplicate_clusters
             (cluster_key, zone_id, report_ids, first_report_at, last_report_at, merged_count)
             VALUES (?,?,?,?,?,1)"
        );
        $ins->execute([$key, $zoneId, (string)$reportId, $now, $now]);
        return null; // pas un doublon : premier de son cluster
    } catch (Throwable $e) {
        return null; // dégradation gracieuse (table absente, etc.)
    }
}
