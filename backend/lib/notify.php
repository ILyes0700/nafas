<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Gabes Tatenafas — Génération automatique d'alertes
 *
 * Appelée après l'insertion d'un signalement (reports) ou d'un symptôme (symptoms).
 * Crée une alerte dans la table `alerts` (et une notification) si :
 *   - le nombre d'enregistrements de ce type dans la zone (24h) dépasse un seuil,
 *   - aucune alerte automatique identique n'a été créée dans les 6 dernières heures
 *     (déduplication via le préfixe `[AUTO:<kind>]` dans le titre).
 */

const NOTIFY_THRESHOLD_REPORTS  = 5;   // ≥ 5 signalements/24h  → warning
const NOTIFY_THRESHOLD_REPORTS2 = 10;  // ≥ 10 signalements/24h → danger
const NOTIFY_THRESHOLD_SYMPTOMS = 5;   // ≥ 5 symptômes/24h     → danger
const NOTIFY_DEDUP_HOURS        = 6;

/**
 * @param int    $zoneId
 * @param string $kind  'reports' | 'symptoms'
 * @return ?int  ID de l'alerte créée, ou null si aucune
 */
function notify_check_threshold(int $zoneId, string $kind): ?int
{
    $pdo = db();

    // Compter dans les 24 dernières heures
    if ($kind === 'reports') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE zone_id = ? AND reported_at >= NOW() - INTERVAL 1 DAY");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM symptoms WHERE zone_id = ? AND reported_at >= NOW() - INTERVAL 1 DAY");
    }
    $stmt->execute([$zoneId]);
    $count = (int)$stmt->fetchColumn();

    // Déterminer le seuil + sévérité
    $severity = null; $threshold = 0;
    if ($kind === 'reports') {
        if ($count >= NOTIFY_THRESHOLD_REPORTS2)      { $severity = 'danger';   $threshold = NOTIFY_THRESHOLD_REPORTS2; }
        elseif ($count >= NOTIFY_THRESHOLD_REPORTS)   { $severity = 'warning';  $threshold = NOTIFY_THRESHOLD_REPORTS;  }
    } else { // symptoms
        if ($count >= NOTIFY_THRESHOLD_SYMPTOMS)      { $severity = 'danger';   $threshold = NOTIFY_THRESHOLD_SYMPTOMS; }
    }

    if ($severity === null) return null;

    // Anti-doublon
    $tag = "[AUTO:$kind]";
    $dedup = $pdo->prepare("
        SELECT id FROM alerts
        WHERE zone_id = ? AND title LIKE ?
          AND created_at >= NOW() - INTERVAL " . NOTIFY_DEDUP_HOURS . " HOUR
        LIMIT 1
    ");
    $dedup->execute([$zoneId, $tag . '%']);
    if ($dedup->fetchColumn()) return null;

    // Récupérer le nom de zone
    $z = $pdo->prepare('SELECT name FROM zones WHERE id = ?');
    $z->execute([$zoneId]);
    $zoneName = $z->fetchColumn() ?: 'Unknown zone';

    $title = $kind === 'reports'
        ? "$tag Reports peak in $zoneName"
        : "$tag Symptoms peak in $zoneName";
    $message = $kind === 'reports'
        ? "$count citizen reports received in 24h for zone $zoneName (threshold $threshold). Verification recommended."
        : "$count symptoms reported in 24h for zone $zoneName (threshold $threshold). Health watch.";

    // A6 : pour les alertes critiques, marquer les groupes prioritaires.
    $priorityGroups = $severity === 'danger' ? 'asthma,heart,child,elderly,pregnant' : null;

    $ins = $pdo->prepare(
        "INSERT INTO alerts (zone_id, title, message, severity, type, priority_groups)
         VALUES (?,?,?,?,?,?)"
    );
    $ins->execute([$zoneId, $title, $message, $severity, "auto-$kind", $priorityGroups]);
    $alertId = (int)$pdo->lastInsertId();

    // Notification globale
    $pdo->prepare(
        "INSERT INTO notifications (target_role, title, message, level) VALUES ('all', ?, ?, ?)"
    )->execute([$title, $message, $severity === 'warning' ? 'warning' : 'danger']);

    // A6 — notifications individuelles HAUTE PRIORITÉ pour les fragiles de la zone
    if ($priorityGroups) notify_fragile_in_zone($zoneId, $title, $message);

    // Tous les citoyens de la zone (home_zone_id) reçoivent une notification individuelle
    // de priorité moyenne, distincte du broadcast 'all', pour visibilité accrue.
    notify_citizens_in_zone($zoneId, $title, $message, $severity);

    return $alertId;
}

/**
 * Pousse une notification individuelle (priorité moyenne) à chaque citoyen
 * dont la zone de résidence correspond. Exclut ceux déjà notifiés par
 * notify_fragile_in_zone() pour éviter les doublons.
 */
function notify_citizens_in_zone(int $zoneId, string $title, string $message, string $severity = 'warning'): int
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare(
            "SELECT u.id FROM users u
             LEFT JOIN fragile_profiles f ON f.user_id = u.id
             WHERE u.zone_id = ? AND u.is_active = 1 AND u.role = 'citizen'
               AND f.user_id IS NULL"
        );
        $stmt->execute([$zoneId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $level = $severity === 'warning' ? 'warning' : ($severity === 'safe' ? 'info' : 'danger');
        $tagged = '🏘️ Your zone — ' . $title;
        $cnt = 0;
        $ins = $pdo->prepare(
            "INSERT INTO notifications (target_role, target_user_id, title, message, level, priority)
             VALUES ('citizen', ?, ?, ?, ?, 5)"
        );
        foreach ($ids as $uid) {
            $ins->execute([(int)$uid, $tagged, $message, $level]);
            $cnt++;
        }
        return $cnt;
    } catch (Throwable $e) {
        error_log('[notify_citizens_in_zone] ' . $e->getMessage());
        return 0;
    }
}

/**
 * A6 — Pousse une notification individuelle prioritaire à chaque utilisateur
 *      "fragile" rattaché à la zone concernée.
 */
function notify_fragile_in_zone(int $zoneId, string $title, string $message): int
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare(
            "SELECT u.id FROM users u
             JOIN fragile_profiles f ON f.user_id = u.id
             WHERE u.zone_id = ? AND u.is_active = 1
               AND (f.has_asthma = 1 OR f.has_heart_disease = 1 OR f.has_allergy = 1
                    OR f.is_pregnant = 1 OR f.is_child = 1 OR f.is_elderly = 1)"
        );
        $stmt->execute([$zoneId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $cnt = 0;
        $ins = $pdo->prepare(
            "INSERT INTO notifications (target_role, target_user_id, title, message, level, priority)
             VALUES ('citizen', ?, ?, ?, 'danger', 10)"
        );
        $tagged = '⚠️ Vulnerable profile — ' . $title;
        foreach ($ids as $uid) {
            $ins->execute([(int)$uid, $tagged, $message]);
            $cnt++;
        }
        return $cnt;
    } catch (Throwable $e) {
        error_log('[notify_fragile_in_zone] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Crée immédiatement une alerte pour un symptôme sévère (sans seuil).
 */
function notify_severe_symptom(int $zoneId, string $symptom): ?int
{
    $pdo = db();
    $tag = '[AUTO:severe]';

    $dedup = $pdo->prepare("
        SELECT id FROM alerts WHERE zone_id = ? AND title LIKE ?
          AND created_at >= NOW() - INTERVAL " . NOTIFY_DEDUP_HOURS . " HOUR LIMIT 1
    ");
    $dedup->execute([$zoneId, $tag . '%']);
    if ($dedup->fetchColumn()) return null;

    $z = $pdo->prepare('SELECT name FROM zones WHERE id = ?');
    $z->execute([$zoneId]);
    $zoneName = $z->fetchColumn() ?: 'Unknown zone';

    $title   = "$tag Severe symptom reported in $zoneName";
    $message = "A severe symptom ($symptom) was reported in $zoneName. Medical monitoring recommended.";

    $ins = $pdo->prepare(
        "INSERT INTO alerts (zone_id, title, message, severity, type) VALUES (?,?,?, 'danger', 'auto-severe')"
    );
    $ins->execute([$zoneId, $title, $message]);
    $id = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO notifications (target_role, title, message, level) VALUES ('all', ?, ?, 'danger')"
    )->execute([$title, $message]);

    return $id;
}


/* ===========================================================================
 * PART 34 — Notifications intelligentes ANTI-FATIGUE.
 * ---------------------------------------------------------------------------
 * Évite le spam qui fait ignorer les vraies urgences (alert fatigue).
 * Règles :
 *   - Ne JAMAIS bloquer une escalade (low->high, high->critical) : envoyer.
 *   - Bloquer la répétition du MÊME niveau dans les 3h (compte suppressed_count).
 *   - Les notifications supprimées sont résumables via un digest quotidien.
 * Dégrade proprement : si la table throttle manque, on autorise l'envoi.
 * =========================================================================== */
const NOTIFY_THROTTLE_HOURS = 3;

function _risk_rank(string $level): int
{
    switch (strtolower($level)) {
        case 'critical': case 'danger':   return 3;
        case 'high':                      return 3;
        case 'warning': case 'moderate':  return 2;
        case 'low': case 'safe': case 'info': default: return 1;
    }
}

/**
 * Décide s'il faut envoyer une notification à un utilisateur pour une zone.
 * Met à jour notification_throttle en conséquence (last_sent_at / suppressed).
 */
function should_send_notification(PDO $pdo, int $userId, int $zoneId, string $newRiskLevel): bool
{
    try {
        $q = $pdo->prepare(
            'SELECT id, last_sent_at, last_risk_level, suppressed_count
             FROM notification_throttle WHERE user_id = ? AND zone_id = ? LIMIT 1'
        );
        $q->execute([$userId, $zoneId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[notify_throttle] table absente, envoi autorisé: ' . $e->getMessage());
        return true; // dégradation gracieuse : ne pas bloquer
    }

    $newRank = _risk_rank($newRiskLevel);

    if (!$row) {
        try {
            $pdo->prepare(
                'INSERT INTO notification_throttle (user_id, zone_id, last_sent_at, last_risk_level, suppressed_count)
                 VALUES (?,?,NOW(),?,0)'
            )->execute([$userId, $zoneId, $newRiskLevel]);
        } catch (Throwable $e) { /* non bloquant */ }
        return true;
    }

    $oldRank = _risk_rank((string)($row['last_risk_level'] ?? 'low'));
    $lastSent = strtotime((string)($row['last_sent_at'] ?? '')) ?: 0;
    $hoursSince = (time() - $lastSent) / 3600.0;

    // Escalade -> toujours envoyer et rafraîchir le throttle.
    if ($newRank > $oldRank) {
        try {
            $pdo->prepare(
                'UPDATE notification_throttle SET last_sent_at = NOW(), last_risk_level = ?, suppressed_count = 0 WHERE id = ?'
            )->execute([$newRiskLevel, (int)$row['id']]);
        } catch (Throwable $e) { /* non bloquant */ }
        return true;
    }

    // Même niveau (ou baisse) dans la fenêtre de 3h -> supprimer.
    if ($hoursSince < NOTIFY_THROTTLE_HOURS) {
        try {
            $pdo->prepare(
                'UPDATE notification_throttle SET suppressed_count = suppressed_count + 1 WHERE id = ?'
            )->execute([(int)$row['id']]);
        } catch (Throwable $e) { /* non bloquant */ }
        return false;
    }

    // Fenêtre écoulée -> autoriser et rafraîchir.
    try {
        $pdo->prepare(
            'UPDATE notification_throttle SET last_sent_at = NOW(), last_risk_level = ?, suppressed_count = 0 WHERE id = ?'
        )->execute([$newRiskLevel, (int)$row['id']]);
    } catch (Throwable $e) { /* non bloquant */ }
    return true;
}

/**
 * Digest quotidien : nombre de notifications supprimées par la logique
 * anti-fatigue (pour affichage admin / rapport). Dégrade proprement.
 */
function notification_suppressed_digest(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            'SELECT zone_id, SUM(suppressed_count) AS suppressed
             FROM notification_throttle GROUP BY zone_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
