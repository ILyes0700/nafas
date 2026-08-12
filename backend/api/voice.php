<?php
/**
 * C4 — Speech-to-text via Groq Whisper (`whisper-large-v3-turbo`).
 *
 * POST multipart/form-data, champ « audio » (audio/webm, audio/wav, audio/mpeg, audio/m4a).
 *   Optionnel : champ « language » (fr | ar | en | auto).
 *
 * Réponse :
 *   { ok:true, text:"...", language:"fr", duration:3.7 }
 *   { ok:false, error:"..." }    en cas de skip ou panne réseau.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/groq.php';
require_once __DIR__ . '/../lib/rate_limit.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
if (empty($_FILES['audio']) || ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'audio file missing'], 400);
}

/* Rate limit : 30 transcriptions/heure (utilisé pour BR/dictée) */
$pdo = db();
$scope = rate_limit_scope_key('voice');
if (!rate_limit_check($pdo, $scope, 'voice', 30, 3600)) {
    json_response(['ok' => false, 'error' => 'Trop de transcriptions. Patientez.', 'code' => 'rate_limited'], 429);
}

$audio = $_FILES['audio'];

/* Limite 25 Mo (Whisper accepte jusqu'à 25 Mo) — on reste prudent à 8 Mo */
if ($audio['size'] > 8 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'audio_too_large (max 8 Mo)'], 413);
}

/* MIME accepté par Groq Whisper API.
   Note : webm/mp4/ogg sont des CONTENEURS qui peuvent contenir audio seul
   (MediaRecorder produit souvent video/webm même quand on capture audio:true).
   On accepte donc aussi les variantes "video/" et on les remappe en "audio/". */
$allowed = [
    'audio/webm', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/wave',
    'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/m4a',
    'audio/flac',
];
$videoToAudio = [
    'video/webm' => 'audio/webm',
    'video/ogg'  => 'audio/ogg',
    'video/mp4'  => 'audio/mp4',
];
$mime = $audio['type'] ?? '';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) { $mime = finfo_file($f, $audio['tmp_name']) ?: $mime; finfo_close($f); }
}
if (isset($videoToAudio[$mime])) {
    $mime = $videoToAudio[$mime];
}
if (!in_array($mime, $allowed, true)) {
    json_response(['ok' => false, 'error' => "unsupported audio mime ({$mime})"], 400);
}

if (!function_exists('curl_init')) {
    json_response(['ok' => false, 'error' => 'php_curl manquant'], 500);
}
if (GROQ_API_KEY === '' || stripos(GROQ_API_KEY, 'gsk_') !== 0) {
    json_response(['ok' => false, 'error' => 'groq_key absente'], 500);
}

$language = strtolower(trim((string)($_POST['language'] ?? 'auto')));
if (!in_array($language, ['fr', 'ar', 'en', 'auto'], true)) $language = 'auto';

$cfile = curl_file_create($audio['tmp_name'], $mime, $audio['name'] ?: 'audio.webm');

$post = [
    'file'           => $cfile,
    'model'          => 'whisper-large-v3-turbo',
    'response_format'=> 'verbose_json',
    'temperature'    => '0',
];
if ($language !== 'auto') $post['language'] = $language;

$ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . GROQ_API_KEY],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$body  = curl_exec($ch);
$errno = curl_errno($ch);
$cErr  = curl_error($ch);
$http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
    json_response(['ok' => false, 'error' => "curl_errno=$errno $cErr"], 502);
}
if ($http >= 400 || !$body) {
    json_response(['ok' => false, 'error' => "http=$http " . mb_substr((string)$body, 0, 200)], 502);
}

$data = json_decode($body, true);
$text = $data['text'] ?? '';
if ($text === '') json_response(['ok' => false, 'error' => 'empty_transcription'], 502);

json_response([
    'ok'        => true,
    'text'      => trim($text),
    'language'  => $data['language'] ?? $language,
    'duration'  => $data['duration'] ?? null,
]);
