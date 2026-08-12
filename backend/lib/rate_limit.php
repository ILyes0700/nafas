<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Limiteur de débit basé sur la table `rate_limits`.
 *
 * Modèle : on logge chaque action puis on compte sur la fenêtre glissante.
 * Aucune dépendance Redis/APCu — fonctionne sur n'importe quel WAMP.
 *
 *   if (!rate_limit_check($pdo, "uid:42|reports", 'reports', 5, 3600)) {
 *       json_response(['ok'=>false, 'error'=>'Trop de bilans en peu de temps'], 429);
 *   }
 */

const RATE_LIMIT_DEFAULT_WINDOW = 3600; // 1h

function rate_limit_scope_key(string $action): string
{
    if (function_exists('auth_user')) {
        $u = auth_user();
        if ($u && !empty($u['id'])) return 'uid:' . (int)$u['id'] . '|' . $action;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'ip:' . $ip . '|' . $action;
}

/**
 * Renvoie true si l'action est autorisée, false sinon.
 * Sur autorisation, l'occurrence est enregistrée immédiatement.
 */
function rate_limit_check(
    PDO     $pdo,
    string  $scopeKey,
    string  $action,
    int     $maxOccurrences = 5,
    int     $windowSeconds  = RATE_LIMIT_DEFAULT_WINDOW
): bool {
    // Local development: never rate-limit loopback traffic. All local testing
    // comes from the same IP (127.0.0.1 / ::1), which would otherwise exhaust
    // the per-IP quota after only a few test sign-ups.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE scope_key = ? AND action_type = ?
               AND occurred_at >= NOW() - INTERVAL ? SECOND"
        );
        $stmt->execute([$scopeKey, $action, $windowSeconds]);
        $current = (int)$stmt->fetchColumn();

        if ($current >= $maxOccurrences) return false;

        // Enregistre l'occurrence
        $ins = $pdo->prepare(
            "INSERT INTO rate_limits (scope_key, action_type) VALUES (?, ?)"
        );
        $ins->execute([$scopeKey, $action]);

        // Nettoyage opportuniste (1% des appels) — garde la table petite
        if (mt_rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM rate_limits WHERE occurred_at < NOW() - INTERVAL 7 DAY");
        }
        return true;
    } catch (Throwable $e) {
        // En cas d'erreur SQL (table absente etc.), on n'empêche pas l'utilisateur d'agir.
        error_log('[rate_limit] ' . $e->getMessage());
        return true;
    }
}

/**
 * Helper : nb d'actions effectuées sur la fenêtre (utile pour debug + UI).
 */
function rate_limit_count(
    PDO    $pdo,
    string $scopeKey,
    string $action,
    int    $windowSeconds = RATE_LIMIT_DEFAULT_WINDOW
): int {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE scope_key = ? AND action_type = ?
               AND occurred_at >= NOW() - INTERVAL ? SECOND"
        );
        $stmt->execute([$scopeKey, $action, $windowSeconds]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
