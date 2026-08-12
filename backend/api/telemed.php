<?php
/**
 * H1 — Télémédecine : génère un lien Jitsi unique pour l'utilisateur.
 *
 * Méthode : GET → renvoie l'URL Jitsi à ouvrir dans une nouvelle fenêtre.
 * Le nom de la salle est dérivé de l'ID utilisateur + un sel applicatif,
 * de sorte que :
 *  - chaque utilisateur ait toujours la même salle (continuité possible),
 *  - l'URL ne soit pas devinable sans connaître l'ID + le sel.
 *
 * Le médecin peut être invité par partage du lien ; le système journalise
 * la création de la salle (utile pour audit).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

const TELEMED_SALT   = 'gabes-tatenafas-telemed-2026';
const TELEMED_DOMAIN = 'meet.jit.si';

/* Identifiant non-devinable mais déterministe pour cet utilisateur */
$slug = substr(hash('sha256', TELEMED_SALT . '|' . (int)$me['id']), 0, 16);
$room = 'NafassGabes-' . (int)$me['id'] . '-' . $slug;
$url  = 'https://' . TELEMED_DOMAIN . '/' . rawurlencode($room)
      . '#userInfo.displayName=' . rawurlencode($me['full_name'] ?: $me['username'])
      . '&config.prejoinPageEnabled=true';

/* Trace in notifications (lightweight audit) */
try {
    $pdo = db();
    $pdo->prepare(
        "INSERT INTO notifications (target_role, target_user_id, title, message, level, priority)
         VALUES ('citizen', ?, ?, ?, 'info', 0)"
    )->execute([
        (int)$me['id'],
        'Telemedicine — room ready',
        "Jitsi room created for the consultation: $room. Share the link with your doctor.",
    ]);
} catch (Throwable $e) {
    error_log('[telemed] log notification: ' . $e->getMessage());
}

json_response([
    'ok'   => true,
    'room' => $room,
    'url'  => $url,
    'note' => "Share this link with a healthcare professional. The room is private but accessible to anyone with the URL.",
]);
