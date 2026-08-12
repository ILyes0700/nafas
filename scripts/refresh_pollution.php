<?php
/**
 * scripts/refresh_pollution.php
 * ─────────────────────────────────────────────────────────────────
 * CLI counterpart of backend/api/iqair-refresh.php — does NOT require
 * an HTTP session, so you can run it directly from a terminal:
 *
 *     php scripts/refresh_pollution.php           # all zones, respect cache
 *     php scripts/refresh_pollution.php --force   # ignore cache
 *     php scripts/refresh_pollution.php --zone=3  # one zone only
 *
 * It pulls live IQAir + WAQI for each zone (using the lat/lng from the
 * zones table), runs the multi-source verifier (IQR + Z-score), fuses the
 * sources by median, writes the result into `zones.pollution_level`, and
 * recomputes `risk_scores`. Every step is journalled in
 * `api_verification_log` (visible from the admin "Verify Data" page).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/lib/helpers.php';
require_once __DIR__ . '/../backend/lib/iqair.php';

$force = false;
$zone  = 0;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--force')              $force = true;
    elseif (preg_match('/^--zone=(\d+)$/', $arg, $m)) $zone = (int)$m[1];
}

$pdo = db();

if ($zone > 0) {
    echo "Refreshing zone {$zone} …\n";
    $r = iqair_refresh_zone($pdo, $zone, $force);
    print_result($r);
    exit($r['ok'] ? 0 : 2);
}

echo "Refreshing all zones (" . ($force ? 'force' : 'cache-aware') . ") …\n\n";
$results = iqair_refresh_all($pdo, $force);
foreach ($results as $r) print_result($r);

// Traditional closures — compatible PHP 7.0+.
$ok      = count(array_filter($results, function ($r) { return !empty($r['ok']); }));
$changed = count(array_filter($results, function ($r) { return !empty($r['changed']); }));
$cached  = count(array_filter($results, function ($r) { return !empty($r['cached']); }));

echo "\nDone. {$ok} ok / " . count($results) . " total — {$changed} changed, {$cached} cached.\n";
exit(0);

function print_result(array $r): void
{
    if (!empty($r['ok'])) {
        printf("  zone %d : pollution = %d (was %d, station=%s, trust=%.2f)%s\n",
            (int)($r['zone_id'] ?? 0),
            (int)($r['pollution_level'] ?? 0),
            (int)($r['previous_level'] ?? 0),
            (string)($r['station'] ?? '—'),
            (float)($r['trust'] ?? 1.0),
            !empty($r['cached']) ? '  [cached]' : ''
        );
    } else {
        printf("  zone %d : FAIL — %s\n",
            (int)($r['zone_id'] ?? 0),
            (string)($r['error'] ?? 'unknown'));
    }
}
