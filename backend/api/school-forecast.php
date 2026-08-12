<?php
/**
 * PART 35 — Mode école PRÉDICTIF (suggestion uniquement, jamais automatique).
 *
 *   GET /backend/api/school-forecast.php            → suggestion +6h par zone
 *   GET /backend/api/school-forecast.php?zone_id=3  → filtré par zone
 *
 * Décision NON automatique : renvoie une SUGGESTION que le directeur/l'admin
 * valide manuellement. Dégradation gracieuse si school_forecast.php ou les
 * tables de prévision manquent.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/school_forecast.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin', 'school', 'health'], true)) {
    json_response(['ok' => false, 'error' => 'not_allowed'], 403);
}

$pdo = db();
$zone = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

try {
    $zones = [];
    if ($zone) {
        $zones[] = $zone;
    } else {
        foreach ($pdo->query('SELECT id FROM zones ORDER BY id')->fetchAll() as $r) {
            $zones[] = (int)$r['id'];
        }
    }

    $out = [];
    foreach ($zones as $zid) {
        if (function_exists('predict_school_status')) {
            $out[] = predict_school_status($pdo, $zid);
        }
    }
    json_response(['ok' => true, 'mode' => 'suggestion', 'predictions' => $out]);
} catch (Throwable $e) {
    // Dégradation gracieuse : pas de prédiction plutôt qu'une erreur 500.
    json_response(['ok' => true, 'mode' => 'suggestion', 'predictions' => [],
                   'note' => 'forecast indisponible: ' . $e->getMessage()]);
}
