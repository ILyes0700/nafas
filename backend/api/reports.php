<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/notify.php';
require_once __DIR__ . '/../lib/groq_vision.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/rate_limit.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    /* ---------- B9 rate limit : 5 bilans / heure ---------- */
    $scope = rate_limit_scope_key('reports');
    if (!rate_limit_check($pdo, $scope, 'reports', 5, 3600)) {
        json_response([
            'ok'    => false,
            'error' => 'Trop de signalements en peu de temps. Merci de patienter.',
            'code'  => 'rate_limited',
        ], 429);
    }

    // Détecte multipart (formulaire avec image) vs JSON pur
    $isMultipart = !empty($_POST) || !empty($_FILES);

    if ($isMultipart) {
        $in = $_POST;
    } else {
        $in = read_json_input();
    }

    $zoneId = isset($in['zone_id']) && $in['zone_id'] !== '' ? (int)$in['zone_id'] : null;

    /* ---------- gestion image (optionnelle) ---------- */
    $imagePath  = null;
    $absImage   = null;
    if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        [$imagePath, $absImage] = save_uploaded_image($_FILES['image']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO reports (zone_id, citizen_name, category, description, image_path)
         VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $zoneId,
        $in['citizen_name'] ?? 'Anonyme',
        $in['category']     ?? 'other',
        $in['description']  ?? '',
        $imagePath,
    ]);
    $reportId = (int)$pdo->lastInsertId();

    /* ---------- UPGRADE v8 — Part 49.1/49.2 : déduplication + classification NLP ---------- */
    $dupCluster  = null;
    $nlpCategory = null;
    try {
        require_once __DIR__ . '/../lib/report_dedup.php';
        $dupCluster = find_duplicate_cluster($pdo, [
            'id'      => $reportId,
            'zone_id' => (string)($zoneId ?? ''),
            'lat'     => isset($in['lat']) ? (float)$in['lat'] : null,
            'lng'     => isset($in['lng']) ? (float)$in['lng'] : null,
        ]);
    } catch (Throwable $e) { $dupCluster = null; }
    try {
        require_once __DIR__ . '/../lib/report_nlp_classifier.php';
        $descTxt = (string)($in['description'] ?? '');
        if ($descTxt !== '') { $nlpCategory = classify_report_text($descTxt); }
    } catch (Throwable $e) { $nlpCategory = null; }

    /* ---------- C1+C2+C3 — analyse structurée de l'image (si fournie) ---------- */
    $aiAnalysis = null;
    $aiStruct   = null;
    $imageHash  = null;
    $duplicate  = null;

    if ($absImage) {
        // SHA-256 de l'image — sert à la déduplication (C3).
        $imageHash = @hash_file('sha256', $absImage) ?: null;
        if ($imageHash) {
            $dup = $pdo->prepare(
                "SELECT id, reported_at FROM reports
                 WHERE image_hash = ? AND id <> ?
                 ORDER BY reported_at DESC LIMIT 1"
            );
            $dup->execute([$imageHash, $reportId]);
            $duplicate = $dup->fetch() ?: null;
        }

        // Contexte zone
        $zoneName = '';
        if ($zoneId) {
            $z = $pdo->prepare("SELECT name FROM zones WHERE id = ?");
            $z->execute([$zoneId]);
            $zoneName = (string)($z->fetchColumn() ?: '');
        }

        $aiStruct = analyze_report_image_structured(
            $absImage,
            (string)($in['description'] ?? ''),
            (string)($in['category']    ?? 'other'),
            $zoneName
        );

        if ($aiStruct !== null) {
            $aiAnalysis = $aiStruct['text'] ?? null;
            $upd = $pdo->prepare(
                'UPDATE reports SET
                    ai_analysis    = ?,
                    ai_category    = ?,
                    ai_severity    = ?,
                    ai_intensity   = ?,
                    ai_fake_score  = ?,
                    image_hash     = ?,
                    ai_analysis_at = NOW()
                 WHERE id = ?'
            );
            $upd->execute([
                $aiAnalysis,
                $aiStruct['category']  ?? null,
                $aiStruct['severity']  ?? null,
                $aiStruct['intensity'] ?? null,
                $aiStruct['fake_score']?? null,
                $imageHash,
                $reportId,
            ]);
        } elseif ($imageHash) {
            $upd = $pdo->prepare('UPDATE reports SET image_hash = ? WHERE id = ?');
            $upd->execute([$imageHash, $reportId]);
        }
    }

    $auto = null;
    if ($zoneId) {
        compute_risk_score($zoneId);
        $auto = notify_check_threshold($zoneId, 'reports');
    }

    json_response([
        'ok'             => true,
        'id'             => $reportId,
        'image_path'     => $imagePath,
        'ai_analysis'    => $aiAnalysis,
        'ai_struct'      => $aiStruct,           // {category,intensity,severity,color,fake_score}
        'image_hash'     => $imageHash,
        'duplicate_of'   => $duplicate,          // {id, reported_at} si déjà vu
        'dup_cluster'    => $dupCluster,         // UPGRADE v8 — cluster de doublons
        'nlp_category'   => $nlpCategory,        // UPGRADE v8 — classification NLP
        'ai_error'       => $aiStruct === null && $imagePath
                            ? (function_exists('groq_vision_last_error') ? groq_vision_last_error() : null)
                            : null,
        'auto_alert_id'  => $auto,
    ]);
}

$rows = $pdo->query("
    SELECT r.*, z.name AS zone_name
    FROM reports r LEFT JOIN zones z ON z.id = r.zone_id
    ORDER BY r.reported_at DESC LIMIT 7
")->fetchAll();
json_response(['reports' => $rows]);


/* ====================================================================
   Helpers locaux : sauvegarde sécurisée d'une image envoyée par le form
   - taille max : 4 Mo (alignée sur la limite base64 de Groq Vision)
   - mimes acceptés : jpeg, png, webp, gif
   - retourne [chemin_relatif, chemin_absolu] ou [null, null]
   ==================================================================== */

function save_uploaded_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, null];
    if ($file['size'] > 4 * 1024 * 1024) return [null, null]; // 4 Mo (Groq Vision)

    // détection MIME
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($allowed[$mime])) return [null, null];
    $ext = $allowed[$mime];

    // Dossier physique : <projet>/uploads/reports/
    $root  = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
    $dest  = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports';
    if (!is_dir($dest)) {
        @mkdir($dest, 0775, true);
    }

    $name = 'report-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $abs  = $dest . DIRECTORY_SEPARATOR . $name;

    if (!@move_uploaded_file($file['tmp_name'], $abs)) return [null, null];
    @chmod($abs, 0644);

    // Chemin renvoyé relatif à la racine projet (servi par WAMP)
    return ['uploads/reports/' . $name, $abs];
}
