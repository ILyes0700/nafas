<?php
/**
 * B7 — Journal de santé personnel.
 *
 * GET    → liste les 30 derniers jours pour l'utilisateur connecté
 *          (utilisé par Chart.js).
 * POST   → upsert d'une entrée pour une date donnée.
 * DELETE → supprime l'entrée d'une date donnée  (?date=YYYY-MM-DD).
 *
 * Champs :
 *   diary_date (YYYY-MM-DD), mood (1..5),
 *   cough/breath_diff/eye_irritation/headache/fatigue (0..5),
 *   notes (≤ 500 chars).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo    = db();
$userId = (int)$me['id'];
$method = $_SERVER['REQUEST_METHOD'];

function _diary_clamp($v, int $min, int $max, int $default): int {
    if ($v === null || $v === '') return $default;
    $n = (int)$v;
    return max($min, min($max, $n));
}

if ($method === 'POST') {
    $in = read_json_input();
    $date = trim((string)($in['diary_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

    $row = [
        'mood'           => _diary_clamp($in['mood']           ?? null, 1, 5, 3),
        'cough'          => _diary_clamp($in['cough']          ?? null, 0, 5, 0),
        'breath_diff'    => _diary_clamp($in['breath_diff']    ?? null, 0, 5, 0),
        'eye_irritation' => _diary_clamp($in['eye_irritation'] ?? null, 0, 5, 0),
        'headache'       => _diary_clamp($in['headache']       ?? null, 0, 5, 0),
        'fatigue'        => _diary_clamp($in['fatigue']        ?? null, 0, 5, 0),
    ];
    $notes = trim((string)($in['notes'] ?? ''));
    if (mb_strlen($notes) > 500) $notes = mb_substr($notes, 0, 500);

    $sql = 'INSERT INTO personal_diary
              (user_id, diary_date, mood, cough, breath_diff,
               eye_irritation, headache, fatigue, notes)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              mood = VALUES(mood),
              cough = VALUES(cough),
              breath_diff = VALUES(breath_diff),
              eye_irritation = VALUES(eye_irritation),
              headache = VALUES(headache),
              fatigue = VALUES(fatigue),
              notes = VALUES(notes)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId, $date,
        $row['mood'], $row['cough'], $row['breath_diff'],
        $row['eye_irritation'], $row['headache'], $row['fatigue'],
        $notes ?: null,
    ]);

    json_response(['ok' => true, 'updated' => true, 'diary_date' => $date] + $row + ['notes' => $notes]);
}

if ($method === 'DELETE') {
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'error' => 'date_required'], 400);
    }
    $del = $pdo->prepare('DELETE FROM personal_diary WHERE user_id = ? AND diary_date = ?');
    $del->execute([$userId, $date]);
    json_response(['ok' => true, 'deleted' => $del->rowCount()]);
}

$days = max(7, min(180, (int)($_GET['days'] ?? 30)));
$stmt = $pdo->prepare(
    "SELECT diary_date, mood, cough, breath_diff, eye_irritation, headache,
            fatigue, notes
     FROM personal_diary
     WHERE user_id = ?"
);
$stmt->execute([$userId]);
$entries = $stmt->fetchAll();

/* Stats agrégées rapides : moyenne sur la période */
$avg = ['mood' => 0,'cough'=>0,'breath_diff'=>0,'eye_irritation'=>0,'headache'=>0,'fatigue'=>0];
$n = count($entries);
if ($n > 0) {
    foreach ($entries as $e) {
        foreach ($avg as $k => $_) $avg[$k] += (int)$e[$k];
    }
    foreach ($avg as $k => $v) $avg[$k] = round($v / $n, 2);
}

json_response([
    'ok'      => true,
    'days'    => $days,
    'count'   => $n,
    'entries' => $entries,
    'average' => $avg,
]);
