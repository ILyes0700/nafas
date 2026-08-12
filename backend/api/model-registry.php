<?php
/**
 * PART 43 — Model Registry / Versioning (admin only).
 *
 *   GET /backend/api/model-registry.php                 → toutes les versions
 *   GET /backend/api/model-registry.php?model=bilstm     → par modèle
 *   GET /backend/api/model-registry.php?ab=1             → derniers runs A/B (Part 44)
 *
 * Lecture seule côté PHP : l'écriture des versions se fait dans train_all.py
 * (model_registry_manager.py). Dégradation gracieuse si les tables manquent.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_only'], 403);
}

$pdo = db();

try {
    if (!empty($_GET['ab'])) {
        $rows = $pdo->query(
            'SELECT * FROM ab_test_runs ORDER BY id DESC LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);
        json_response(['ok' => true, 'ab_runs' => $rows]);
    }

    $model = isset($_GET['model']) ? (string)$_GET['model'] : '';
    if ($model !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM model_versions WHERE model_name = ? ORDER BY id DESC'
        );
        $stmt->execute([$model]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $pdo->query(
            'SELECT * FROM model_versions ORDER BY model_name, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    json_response(['ok' => true, 'versions' => $rows]);
} catch (Throwable $e) {
    json_response(['ok' => true, 'versions' => [], 'ab_runs' => [],
                   'note' => 'registry indisponible (migration v6 requise): ' . $e->getMessage()]);
}
