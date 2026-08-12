<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/* ---------------------------------------------------------------------------
 * mbstring polyfill — keeps the API alive on PHP installations where the
 * mbstring extension is not enabled (some minimal Windows / WAMP setups).
 * Each function is only declared if the real one is missing, so a proper
 * mbstring install always wins.
 * ------------------------------------------------------------------------- */
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = 'UTF-8') {
        $s = (string)$str;
        if ($length === null) {
            return preg_match_all('/./us', $s, $m) ? implode('', array_slice($m[0], (int)$start)) : '';
        }
        return preg_match_all('/./us', $s, $m) ? implode('', array_slice($m[0], (int)$start, (int)$length)) : '';
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return preg_match_all('/./us', (string)$str, $m) ? count($m[0]) : 0;
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($str, $encoding = 'UTF-8') {
        return strtolower((string)$str);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($str, $encoding = 'UTF-8') {
        return strtoupper((string)$str);
    }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($str, $to, $from = null) {
        return (string)$str;
    }
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST ?: [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : ($_POST ?: []);
}

/**
 * Calcule le Risk Score d'une zone à partir de:
 *  - pollution_level (0..100)             poids 50 %
 *  - signalements 24h                     poids 25 %
 *  - symptômes 24h pondérés gravité       poids 25 %
 */
function compute_risk_score(int $zoneId): array
{
    $pdo = db();
    $z   = $pdo->prepare('SELECT pollution_level FROM zones WHERE id = ?');
    $z->execute([$zoneId]);
    $pol = (int)($z->fetchColumn() ?: 0);

    $r = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE zone_id=? AND reported_at >= NOW() - INTERVAL 1 DAY');
    $r->execute([$zoneId]);
    $reports = (int)$r->fetchColumn();

    $s = $pdo->prepare("SELECT severity FROM symptoms WHERE zone_id=? AND reported_at >= NOW() - INTERVAL 1 DAY");
    $s->execute([$zoneId]);
    $sympWeight = 0;
    foreach ($s->fetchAll() as $row) {
        // PHP 7.4-compatible (no match expression).
        switch ($row['severity']) {
            case 'severe':   $sympWeight += 4; break;
            case 'moderate': $sympWeight += 2; break;
            default:         $sympWeight += 1; break;
        }
    }

    // UPGRADE v8 — Part 49.3 : pondération par trust_score citoyen (dégradation gracieuse).
    $trustFactor = 1.0;
    try {
        $tf = $pdo->prepare(
            "SELECT AVG(u.trust_score) FROM reports r
             JOIN users u ON (u.username = r.citizen_name OR u.full_name = r.citizen_name)
             WHERE r.zone_id = ? AND r.reported_at >= NOW() - INTERVAL 1 DAY"
        );
        $tf->execute([$zoneId]);
        $avgTrust = $tf->fetchColumn();
        if ($avgTrust !== null && $avgTrust !== false) {
            $trustFactor = max(0.5, min(1.5, 0.5 + (float)$avgTrust));
        }
    } catch (Throwable $e) { $trustFactor = 1.0; }
    $reportsScore  = min(100, (int)round($reports * 12 * $trustFactor));   // 8+ signalements -> 100
    $symptomsScore = min(100, $sympWeight * 8); // ~12 unités -> 100

    $score = (int) round($pol * 0.5 + $reportsScore * 0.25 + $symptomsScore * 0.25);
    $score = max(0, min(100, $score));

    $level = 'safe';
    if ($score >= 70) $level = 'critical';
    elseif ($score >= 40) $level = 'warning';

    return ['score' => $score, 'level' => $level];
}

function recompute_all_scores(): array
{
    $pdo = db();
    $zones = $pdo->query('SELECT id FROM zones')->fetchAll();
    $out = [];
    foreach ($zones as $z) {
        $r = compute_risk_score((int)$z['id']);
        $ins = $pdo->prepare('INSERT INTO risk_scores (zone_id, score, level) VALUES (?,?,?)');
        $ins->execute([$z['id'], $r['score'], $r['level']]);
        $up = $pdo->prepare('UPDATE zones SET status=? WHERE id=?');
        $up->execute([$r['level'], $z['id']]);
        $out[] = ['zone_id' => (int)$z['id']] + $r;
    }
    return $out;
}

function global_status(): string
{
    $pdo = db();
    $row = $pdo->query("SELECT status, COUNT(*) c FROM zones GROUP BY status")->fetchAll();
    $map = ['safe' => 0, 'warning' => 0, 'critical' => 0];
    foreach ($row as $r) $map[$r['status']] = (int)$r['c'];
    if ($map['critical'] > 0) return 'critical';
    if ($map['warning'] > 0)  return 'warning';
    return 'safe';
}
