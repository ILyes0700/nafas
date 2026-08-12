<?php
/**
 * GET  /backend/api/iqair-refresh.php?zone_id=N → actualise une zone
 * GET  /backend/api/iqair-refresh.php           → actualise toutes les zones
 *      &force=1                                 → ignore le cache 1h
 *
 * Réservé aux rôles admin et health.
 *
 * Bulletproof: this endpoint ALWAYS returns JSON, even if a PHP warning,
 * notice, fatal error or exception fires deep in the IQAir / WAQI stack.
 * The frontend was crashing on "Unexpected token '<'" when Apache served an
 * HTML error page; the shutdown handler below guarantees a JSON payload.
 */

// 1) Silence PHP from emitting HTML notices/warnings into the response body.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

// 2) Start output buffering — we'll discard anything that leaks before json.
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
ob_start();

// 3) Catch fatal errors and convert to JSON.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'php-fatal: ' . $e['message'] . ' in ' . basename($e['file']) . ':' . $e['line'],
        ]);
    }
});

try {
    require_once __DIR__ . '/../lib/helpers.php';
    require_once __DIR__ . '/../lib/auth.php';
    require_once __DIR__ . '/../lib/iqair.php';

    $me = auth_user();
    if (!$me) {
        json_response(['ok' => false, 'error' => 'auth required (open this URL while logged in as admin or use scripts/refresh_pollution.php)'], 401);
    }
    if (!in_array($me['role'], ['admin', 'health', 'super_admin'], true)) {
        json_response(['ok' => false, 'error' => 'permission denied (admin or health role required)'], 403);
    }

    $pdo    = db();
    $force  = isset($_GET['force']) && $_GET['force'] === '1';
    $zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

    if ($zoneId > 0) {
        $res = iqair_refresh_zone($pdo, $zoneId, $force);
        json_response($res);
    }

    $results = iqair_refresh_all($pdo, $force);

    // Use traditional closures so this works on PHP 7.0+ (no arrow funcs).
    $ok      = array_filter($results, function ($r) { return !empty($r['ok']); });
    $failed  = array_filter($results, function ($r) { return empty($r['ok']); });
    $changed = array_filter($ok,      function ($r) { return !empty($r['changed']); });
    $cached  = array_filter($ok,      function ($r) { return !empty($r['cached']); });

    json_response([
        'ok'         => true,
        'total'      => count($results),
        'success'    => count($ok),
        'failed'     => count($failed),
        'changed'    => count($changed),
        'cached'     => count($cached),
        'results'    => array_values($results),
        'last_error' => iqair_last_error(),
    ]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok'    => false,
        'error' => 'exception: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
    ]);
}

