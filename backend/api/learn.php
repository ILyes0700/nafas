<?php
/**
 * Learn & Prevent API — serves educational resources (articles, videos, quizzes,
 * infographics) for the in-app awareness hub.
 *
 * GET ?list=1                 → list resources (filter by ?category, ?kind, ?lang, ?q)
 * GET ?id=N                   → fetch a single resource (full body)
 * POST {action:'view',id:N}   → increment view counter (best-effort, no auth requirement
 *                                beyond being signed in to the platform)
 * POST {action:'quiz',id:N,answers:[...]} → score a quiz and return result
 *
 * The DB table `learn_resources` is created by migration 2026-05-06.
 * Resources are public to all authenticated users; admins may see drafts via ?all=1.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, slug, kind, category, language, title, summary, body,
                    media_url, thumbnail, duration_min, reading_min, level, views,
                    created_at, updated_at
               FROM learn_resources
              WHERE id = ? AND (is_published = 1 OR ? = 1)
              LIMIT 1"
        );
        $isAdmin = ($me['role'] === 'admin') ? 1 : 0;
        $stmt->execute([$id, $isAdmin]);
        $row = $stmt->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'not_found'], 404);
        json_response(['ok' => true, 'resource' => $row]);
    }

    // Listing with optional filters.
    $where = ['is_published = 1'];
    $args  = [];
    if (!empty($_GET['category'])) { $where[] = 'category = ?'; $args[] = (string)$_GET['category']; }
    if (!empty($_GET['kind']))     { $where[] = 'kind = ?';     $args[] = (string)$_GET['kind']; }
    if (!empty($_GET['lang']))     { $where[] = 'language = ?'; $args[] = (string)$_GET['lang']; }
    if (!empty($_GET['q'])) {
        $where[] = '(title LIKE ? OR summary LIKE ?)';
        $q = '%' . $_GET['q'] . '%';
        $args[] = $q; $args[] = $q;
    }
    $sql = "SELECT id, slug, kind, category, language, title, summary,
                   media_url, thumbnail, duration_min, reading_min, level, views,
                   created_at
              FROM learn_resources
             WHERE " . implode(' AND ', $where) . "
             ORDER BY created_at DESC, id DESC
             LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $items = $stmt->fetchAll();

    // Group categories for the filter UI.
    $cats = $pdo->query("SELECT category, COUNT(*) AS c FROM learn_resources WHERE is_published=1 GROUP BY category ORDER BY category")->fetchAll();

    json_response([
        'ok'         => true,
        'items'      => $items,
        'categories' => $cats,
    ]);
}

if ($method === 'POST') {
    $in = read_json_input();
    $action = (string)($in['action'] ?? '');
    $id     = isset($in['id']) ? (int)$in['id'] : 0;

    if ($action === 'view' && $id > 0) {
        $pdo->prepare("UPDATE learn_resources SET views = views + 1 WHERE id = ?")->execute([$id]);
        json_response(['ok' => true]);
    }

    if ($action === 'create') {
        // Admin-only: create a new educational resource.
        if (($me['role'] ?? '') !== 'admin') {
            json_response(['ok' => false, 'error' => 'admin_only'], 403);
        }
        $kind     = (string)($in['kind']     ?? 'article');
        $category = trim((string)($in['category'] ?? ''));
        $language = (string)($in['language'] ?? 'en');
        $title    = trim((string)($in['title']    ?? ''));
        $summary  = trim((string)($in['summary']  ?? ''));
        $body     = (string)($in['body']     ?? '');
        $mediaUrl = trim((string)($in['media_url'] ?? ''));
        $thumb    = trim((string)($in['thumbnail'] ?? ''));
        $level    = (string)($in['level']    ?? 'beginner');
        $dur      = isset($in['duration_min']) && $in['duration_min'] !== '' ? (int)$in['duration_min'] : null;
        $read     = isset($in['reading_min'])  && $in['reading_min']  !== '' ? (int)$in['reading_min']  : null;

        if (!in_array($kind, ['article','video','quiz','infographic'], true)) {
            json_response(['ok' => false, 'error' => 'bad_kind'], 400);
        }
        if (!in_array($level, ['beginner','intermediate','advanced'], true)) $level = 'beginner';
        if (!in_array($language, ['en','fr','ar'], true)) $language = 'en';
        if ($title === '' || $category === '') {
            json_response(['ok' => false, 'error' => 'missing_required'], 400);
        }
        // Slug: ASCII-friendly, unique by numeric suffix if needed.
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $base = trim($base, '-');
        if ($base === '') $base = 'resource';
        $slug = mb_substr($base, 0, 100);
        $check = $pdo->prepare("SELECT COUNT(*) FROM learn_resources WHERE slug = ?");
        $i = 1;
        while (true) {
            $check->execute([$slug]);
            if ((int)$check->fetchColumn() === 0) break;
            $slug = mb_substr($base, 0, 95) . '-' . (++$i);
            if ($i > 50) break;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO learn_resources
              (slug, kind, category, language, title, summary, body,
               media_url, thumbnail, duration_min, reading_min, level, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $slug, $kind, $category, $language, $title,
            $summary !== '' ? $summary : null,
            $body !== ''    ? $body    : null,
            $mediaUrl !== '' ? $mediaUrl : null,
            $thumb !== ''    ? $thumb    : null,
            $dur, $read, $level,
        ]);
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $slug]);
    }

    if ($action === 'quiz' && $id > 0) {
        $stmt = $pdo->prepare("SELECT body, kind FROM learn_resources WHERE id = ? AND is_published = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || $row['kind'] !== 'quiz') {
            json_response(['ok' => false, 'error' => 'not_a_quiz'], 400);
        }
        $questions = json_decode($row['body'], true);
        if (!is_array($questions)) json_response(['ok' => false, 'error' => 'invalid_quiz'], 500);
        $answers = is_array($in['answers'] ?? null) ? $in['answers'] : [];
        $correct = 0;
        $details = [];
        foreach ($questions as $i => $q) {
            $expected = (int)($q['answer'] ?? -1);
            $got      = isset($answers[$i]) ? (int)$answers[$i] : -1;
            $ok       = ($expected === $got);
            if ($ok) $correct++;
            $details[] = ['q' => $q['q'] ?? '', 'expected' => $expected, 'got' => $got, 'correct' => $ok];
        }
        $total = count($questions);
        json_response([
            'ok'      => true,
            'score'   => $correct,
            'total'   => $total,
            'percent' => $total ? round(100 * $correct / $total) : 0,
            'details' => $details,
        ]);
    }

    json_response(['ok' => false, 'error' => 'unknown_action'], 400);
}

json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
