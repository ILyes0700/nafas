<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 50.2 : prédiction personnelle d'aggravation.
 * Croise personal_patterns (Part 50.1) avec forecast_metrics/pollution_forecast
 * pour estimer un risque individuel à +24h/+48h.
 * Dégradation gracieuse : renvoie un niveau 'inconnu' si données insuffisantes.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/symptom_pattern_detector.php';

/**
 * @return array {level, level_24h, level_48h, base_pattern, note, source}
 */
function personal_symptom_forecast(PDO $pdo, int $userId): array
{
    $fallback = [
        'level' => 'inconnu', 'level_24h' => 'inconnu', 'level_48h' => 'inconnu',
        'base_pattern' => null, 'note' => 'Pas assez de données personnelles pour une prédiction.',
        'source' => 'none',
    ];
    try {
        // Zone utilisateur.
        $zst = $pdo->prepare('SELECT zone_id FROM users WHERE id = ?');
        $zst->execute([$userId]);
        $zoneId = (int)($zst->fetchColumn() ?: 0);
        if (!$zoneId) return $fallback;

        // Motif personnel actif (ou tente une détection à la volée).
        $pat = null;
        try {
            $q = $pdo->prepare('SELECT lag_hours, correlation, narrative FROM personal_patterns WHERE user_id=? AND active=1 ORDER BY detected_at DESC LIMIT 1');
            $q->execute([$userId]);
            $pat = $q->fetch() ?: null;
        } catch (Throwable $e) { $pat = null; }
        if (!$pat) {
            $d = detect_personal_pattern($pdo, $userId);
            if ($d) $pat = ['lag_hours' => $d['lag_hours'], 'correlation' => $d['correlation'], 'narrative' => $d['narrative']];
        }

        // Pollution prévue pour la zone à +24h / +48h.
        $fc = _forecast_pollution_for_zone($pdo, $zoneId);
        if ($fc['h24'] === null && $fc['h48'] === null) return $fallback;

        // Facteur de sensibilité personnel (corrélation du motif, sinon neutre 0.5).
        $sens = $pat ? min(1.0, abs((float)$pat['correlation'])) : 0.5;

        $mk = function (?float $pol) use ($sens) {
            if ($pol === null) return 'inconnu';
            $risk = $pol * (0.6 + 0.4 * $sens); // amplifié par la sensibilité perso
            if ($risk >= 70) return 'élevé';
            if ($risk >= 40) return 'modéré';
            return 'faible';
        };

        $l24 = $mk($fc['h24']);
        $l48 = $mk($fc['h48']);
        $note = $pat && !empty($pat['narrative'])
            ? $pat['narrative'] . ' Risque d\'aggravation demain : ' . $l24 . '.'
            : 'Risque d\'aggravation demain : ' . $l24 . ' (basé sur la prévision de votre zone).';

        return [
            'level' => $l24, 'level_24h' => $l24, 'level_48h' => $l48,
            'base_pattern' => $pat['narrative'] ?? null, 'note' => $note,
            'source' => $fc['source'],
        ];
    } catch (Throwable $e) {
        return $fallback;
    }
}

if (!function_exists('_forecast_pollution_for_zone')) {
    function _forecast_pollution_for_zone(PDO $pdo, int $zoneId): array
    {
        // 1) Table pollution_forecast si dispo.
        try {
            $q = $pdo->prepare(
                "SELECT horizon_hours, predicted_level FROM pollution_forecast
                 WHERE zone_id = ? AND horizon_hours IN (24,48)
                 ORDER BY created_at DESC LIMIT 10"
            );
            $q->execute([$zoneId]);
            $h24 = $h48 = null;
            foreach ($q->fetchAll() as $r) {
                $h = (int)$r['horizon_hours'];
                if ($h === 24 && $h24 === null) $h24 = (float)$r['predicted_level'];
                if ($h === 48 && $h48 === null) $h48 = (float)$r['predicted_level'];
            }
            if ($h24 !== null || $h48 !== null) {
                return ['h24' => $h24, 'h48' => $h48, 'source' => 'pollution_forecast'];
            }
        } catch (Throwable $e) { /* table absente */ }

        // 2) Repli : persistance sur la dernière valeur connue de la zone.
        try {
            $cur = (float)$pdo->query("SELECT pollution_level FROM zones WHERE id = " . (int)$zoneId)->fetchColumn();
            return ['h24' => $cur, 'h48' => $cur, 'source' => 'persistence'];
        } catch (Throwable $e) {
            return ['h24' => null, 'h48' => null, 'source' => 'none'];
        }
    }
}
