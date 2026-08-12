<?php
/**
 * Groq Vision — analyse d'images pour la page « Signalements citoyens ».
 *
 * Le citoyen joint une photo à son signalement (fumée, odeur, poussière, ...).
 * Cette photo + sa description sont envoyées au modèle multimodal
 * meta-llama/llama-4-scout-17b-16e-instruct pour produire une courte
 * analyse environnementale en français, gardée à côté du texte original
 * (option (c) : on ne réécrit JAMAIS la description du citoyen).
 *
 * Rappels Groq Vision (2026) :
 *   - max 4 Mo en base64 (la limite multipart côté reports.php est alignée à 4 Mo)
 *   - max 33 mégapixels par image
 *   - le modèle accepte une `image_url` au format data URI (data:image/...;base64,...)
 *   - timeout serveur conseillé : 20 s (vision = un peu plus lent que texte pur)
 *
 * Comportement :
 *   - Si la clé Groq est absente / cURL manquant / appel échoue → renvoie null
 *     (le signalement reste créé sans analyse, c'est juste un bonus).
 *   - À chaque échec, la cause est inscrite via error_log() ET stockée dans
 *     groq_vision_last_error() pour que l'appelant puisse la lire.
 */

require_once __DIR__ . '/../config/groq.php';

const GROQ_VISION_MODEL   = 'meta-llama/llama-4-scout-17b-16e-instruct';
const GROQ_VISION_MAX_TOK = 280;
const GROQ_VISION_TEMP    = 0.2;
const GROQ_VISION_TIMEOUT = 20;

/**
 * Mettre à `true` pour désactiver la vérification SSL (typique sur WAMP local
 * qui n'a pas de cacert.pem configuré → erreur cURL 60).
 *
 *   ⚠️ NE JAMAIS LAISSER À `true` EN PRODUCTION.
 *
 * Solution propre (recommandée) :
 *   1. Télécharger https://curl.se/ca/cacert.pem
 *   2. Le mettre dans C:\wamp64\bin\php\php<VERSION>\extras\ssl\cacert.pem
 *   3. Dans php.ini (icône WAMP → PHP → php.ini), ajouter :
 *        curl.cainfo     = "C:/wamp64/bin/php/php<VERSION>/extras/ssl/cacert.pem"
 *        openssl.cafile  = "C:/wamp64/bin/php/php<VERSION>/extras/ssl/cacert.pem"
 *   4. Redémarrer WAMP, et laisser GROQ_VISION_INSECURE à false.
 */
const GROQ_VISION_INSECURE = true;

/**
 * Conserve la dernière erreur (utile pour debug côté UI).
 */
function groq_vision_last_error(?string $set = null): ?string
{
    static $last = null;
    if ($set !== null) $last = $set;
    return $last;
}

/**
 * Log uniformisé : visible dans le php_error.log de WAMP
 * (icône WAMP → PHP → php_error.log).
 */
function groq_vision_log(string $msg): void
{
    groq_vision_last_error($msg);
    error_log('[groq_vision] ' . $msg);
}

/**
 * Construit le prompt système d'analyse environnementale d'image.
 */
function groq_vision_system_prompt(): string
{
    return <<<PROMPT
You are **Nafass-Vision**, an image-analysis agent supporting environmental
and health surveillance for Gabès, Tunisia.

You receive ONE photo taken by a citizen and their written description. Your
mission:

1. Briefly and factually describe what you observe in the image
   (location, time of day if inferable, main element).
2. State whether the image is consistent with the declared category
   (odor / smoke / dust / breathing problem / noise / other) and the citizen's
   description — confirm, nuance, or politely contradict.
3. Estimate the visual intensity of the incident on the scale:
   "low", "moderate", or "high".
4. Identify any useful visual cues: smoke plume, color (white / grey / black),
   opacity, dust fog, industrial vehicle, chimney, waste, colored water, etc.
5. If the photo is unusable (blurry, very dark, unrelated to environment)
   → say so clearly and do not invent anything.

STRICT CONSTRAINTS:
- Reply **in English**, factual and neutral tone, **maximum 100 words**.
- **No medical diagnosis**, no prescription.
- No markdown, no endless lists, no emojis.
- Do **not** identify any recognizable person in the photo.
- End with a line "Estimated intensity: low/moderate/high".
PROMPT;
}

/**
 * Analyse une image locale + sa description via Groq Vision.
 *
 * @param string $absImagePath Chemin absolu vers le fichier image
 * @param string $description  Description rédigée par le citoyen
 * @param string $category     Catégorie (odor/smoke/breathing/dust/noise/other)
 * @param string $zoneName     Nom lisible de la zone (ex: "Ghannouch")
 * @return string|null         Analyse texte (≤100 mots FR) ou null en cas d'échec
 */
function analyze_report_image(string $absImagePath, string $description, string $category, string $zoneName): ?string
{
    groq_vision_last_error(null);

    if (GROQ_API_KEY === '' || stripos(GROQ_API_KEY, 'gsk_') !== 0) {
        groq_vision_log('skip: clé Groq absente ou invalide (config/groq.php)');
        return null;
    }
    if (!function_exists('curl_init')) {
        groq_vision_log('skip: extension PHP cURL non installée — activez php_curl dans php.ini');
        return null;
    }
    if (!is_file($absImagePath) || !is_readable($absImagePath)) {
        groq_vision_log("skip: fichier introuvable ou illisible : {$absImagePath}");
        return null;
    }

    $bytes = @filesize($absImagePath);
    if ($bytes === false || $bytes <= 0) {
        groq_vision_log("skip: taille fichier invalide ({$bytes}) : {$absImagePath}");
        return null;
    }
    // Marge de sécurité : base64 ≈ +33 %, on exige ≤ 3 Mo bruts pour rester sous 4 Mo b64.
    if ($bytes > 3 * 1024 * 1024) {
        groq_vision_log("skip: image trop grande pour Groq base64 ({$bytes} octets > 3 Mo)");
        return null;
    }

    // Détection du MIME — uniquement les types acceptés par Groq Vision.
    $mime = null;
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $f ? finfo_file($f, $absImagePath) : null;
        if ($f) finfo_close($f);
    }
    if (!$mime) {
        // Fallback simple sur l'extension si finfo absent.
        $ext = strtolower(pathinfo($absImagePath, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                 'png' => 'image/png',  'webp' => 'image/webp',
                 'gif' => 'image/gif'][$ext] ?? null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        groq_vision_log("skip: MIME non supporté ({$mime}) — accepté: jpeg/png/webp/gif");
        return null;
    }

    $raw = @file_get_contents($absImagePath);
    if ($raw === false) {
        groq_vision_log("skip: file_get_contents() a échoué sur {$absImagePath}");
        return null;
    }
    $b64 = base64_encode($raw);
    if (!$b64) {
        groq_vision_log('skip: base64_encode() a renvoyé vide');
        return null;
    }
    $dataUri = 'data:' . $mime . ';base64,' . $b64;

    // Texte visible utilisateur — pour orienter le modèle sans détourner la mission.
    $catLabel = [
        'odor'      => 'suspicious odor',
        'smoke'     => 'smoke',
        'breathing' => 'breathing difficulty',
        'dust'      => 'dust',
        'noise'     => 'noise',
        'other'     => 'other',
    ][$category] ?? $category;

    $userText = "Reported category: {$catLabel}\n"
              . "Zone: " . ($zoneName ?: '—') . "\n"
              . "Citizen description:\n\"" . trim($description) . "\"";

    $payload = [
        'model'       => GROQ_VISION_MODEL,
        'temperature' => GROQ_VISION_TEMP,
        'max_tokens'  => GROQ_VISION_MAX_TOK,
        'messages'    => [
            ['role' => 'system', 'content' => groq_vision_system_prompt()],
            ['role' => 'user',   'content' => [
                ['type' => 'text',      'text' => $userText],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
            ]],
        ],
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => GROQ_VISION_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => !GROQ_VISION_INSECURE,
        CURLOPT_SSL_VERIFYHOST => GROQ_VISION_INSECURE ? 0 : 2,
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $cErr  = curl_error($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        groq_vision_log("skip: cURL errno={$errno} — {$cErr}");
        return null;
    }
    if ($http >= 400 || !$body) {
        $excerpt = $body ? mb_substr($body, 0, 220) : '(vide)';
        groq_vision_log("skip: HTTP {$http} de l'API — {$excerpt}");
        return null;
    }

    $data = json_decode($body, true);
    $txt  = $data['choices'][0]['message']['content'] ?? null;
    if (!$txt) {
        $excerpt = mb_substr($body, 0, 220);
        groq_vision_log("skip: réponse Groq sans 'choices[0].message.content' — {$excerpt}");
        return null;
    }

    $txt = trim($txt);
    // Sécurité : tronque dur à 1500 caractères pour rester compact en base.
    if (function_exists('mb_strlen') && mb_strlen($txt) > 1500) {
        $txt = mb_substr($txt, 0, 1497) . '…';
    } elseif (strlen($txt) > 1500) {
        $txt = substr($txt, 0, 1497) . '…';
    }
    groq_vision_last_error(null);
    return $txt;
}

/**
 * C1 + C2 + C3 — Analyse structurée d'image (un seul appel Groq Vision).
 *
 * Renvoie un tableau associatif avec :
 *   - text       (analyse FR ≤ 100 mots — équivalent à analyze_report_image)
 *   - category   (l'une de: odor / smoke / breathing / dust / noise / other)
 *   - intensity  (low | medium | high)
 *   - severity   (1..10 — gravité estimée)
 *   - color      (couleur dominante du panache, ex: 'gris', 'noir', 'blanc', null)
 *   - fake_score (0..100 — probabilité que l'image soit hors-sujet/manipulée)
 *
 * En cas d'échec : null.
 */
function analyze_report_image_structured(
    string $absImagePath,
    string $description,
    string $category,
    string $zoneName
): ?array {
    groq_vision_last_error(null);

    if (GROQ_API_KEY === '' || stripos(GROQ_API_KEY, 'gsk_') !== 0) {
        groq_vision_log('skip: clé Groq absente'); return null;
    }
    if (!function_exists('curl_init')) {
        groq_vision_log('skip: cURL non disponible'); return null;
    }
    if (!is_file($absImagePath) || !is_readable($absImagePath)) {
        groq_vision_log("skip: image illisible : {$absImagePath}"); return null;
    }

    $bytes = @filesize($absImagePath);
    if (!$bytes || $bytes > 3 * 1024 * 1024) {
        groq_vision_log("skip: taille image invalide ({$bytes})"); return null;
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $f ? finfo_file($f, $absImagePath) : null;
        if ($f) finfo_close($f);
    }
    if (!$mime) {
        $ext = strtolower(pathinfo($absImagePath, PATHINFO_EXTENSION));
        $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
                 'webp'=>'image/webp','gif'=>'image/gif'][$ext] ?? null;
    }
    if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
        groq_vision_log("skip: MIME refusé ({$mime})"); return null;
    }

    $raw = @file_get_contents($absImagePath);
    if (!$raw) { groq_vision_log('skip: lecture image vide'); return null; }
    $dataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);

    $catLabel = [
        'odor'=>'suspicious odor','smoke'=>'smoke','breathing'=>'breathing difficulty',
        'dust'=>'dust','noise'=>'noise','other'=>'other',
    ][$category] ?? $category;

    $system = <<<TXT
You are Nafass-Vision, environmental image analyst for Gabès.
You receive ONE photo + a citizen description + a declared category.
Your mission: produce ONE STRICT JSON OBJECT, no markdown, with EXACTLY
these keys:

{
  "text":        "<analysis EN ≤100 words, factual, neutral, no emoji>",
  "category":    "<odor|smoke|breathing|dust|noise|other>",
  "intensity":   "<low|medium|high>",
  "severity":    <integer 1..10>,
  "color":       "<grey|black|white|yellow|brown|null>",
  "fake_score":  <integer 0..100 — 0=consistent image, 100=off-topic or manipulated>
}

Rules:
- If the photo is unrelated to the environment (selfie, ordinary interior,
  computer screen, obvious stock image), set fake_score ≥ 70.
- If the photo is blurry but consistent, fake_score ≤ 30.
- severity: 1=harmless, 5=concerning, 10=emergency.
- intensity: visual only (smoke thickness, dust density…).
- "color" can be null if not relevant.
- Never invent a person's identity.
- Respond ONLY with the JSON object, nothing around it.
TXT;

    $userText = "Reported category: {$catLabel}\nZone: "
              . ($zoneName ?: '—') . "\nDescription:\n\""
              . trim($description) . "\"";

    $payload = [
        'model'       => GROQ_VISION_MODEL,
        'temperature' => 0.15,
        'max_tokens'  => 480,
        'response_format' => ['type' => 'json_object'],
        'messages'    => [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>[
                ['type'=>'text','text'=>$userText],
                ['type'=>'image_url','image_url'=>['url'=>$dataUri]],
            ]],
        ],
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => GROQ_VISION_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => !GROQ_VISION_INSECURE,
        CURLOPT_SSL_VERIFYHOST => GROQ_VISION_INSECURE ? 0 : 2,
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $cErr  = curl_error($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) { groq_vision_log("skip: cURL errno={$errno} — {$cErr}"); return null; }
    if ($http >= 400 || !$body) {
        groq_vision_log("skip: HTTP {$http} — " . mb_substr((string)$body, 0, 200));
        return null;
    }

    $data = json_decode($body, true);
    $rawJson = $data['choices'][0]['message']['content'] ?? null;
    if (!$rawJson) { groq_vision_log('skip: contenu JSON vide'); return null; }

    $obj = json_decode($rawJson, true);
    if (!is_array($obj)) {
        // Tentative : extraire la première accolade
        if (preg_match('/\{.*\}/su', $rawJson, $m)) {
            $obj = json_decode($m[0], true);
        }
    }
    if (!is_array($obj)) {
        groq_vision_log('skip: JSON Groq non parsable — ' . mb_substr($rawJson, 0, 200));
        return null;
    }

    $allowedCat = ['odor','smoke','breathing','dust','noise','other'];
    $allowedInt = ['low','medium','high'];

    $out = [
        'text'       => isset($obj['text']) ? trim((string)$obj['text']) : null,
        'category'   => in_array($obj['category'] ?? null, $allowedCat, true) ? $obj['category'] : 'other',
        'intensity'  => in_array($obj['intensity'] ?? null, $allowedInt, true) ? $obj['intensity'] : 'low',
        'severity'   => max(1, min(10, (int)($obj['severity'] ?? 1))),
        'color'      => isset($obj['color']) && $obj['color'] !== null ? trim((string)$obj['color']) : null,
        'fake_score' => max(0, min(100, (int)($obj['fake_score'] ?? 0))),
    ];

    if (!$out['text']) { $out['text'] = '—'; }
    if (mb_strlen($out['text']) > 1500) $out['text'] = mb_substr($out['text'], 0, 1497) . '…';

    groq_vision_last_error(null);
    return $out;
}


/* ===========================================================================
 * PART 31 — Classification automatique de gravité des photos citoyennes.
 * ---------------------------------------------------------------------------
 * Réutilise analyze_report_image_structured() (client Groq Vision déjà
 * configuré) et TRADUIT sa sortie vers les catégories de pollution demandées :
 *   clear_sky | haze | industrial_smoke | dust | unclear
 * Dégrade proprement : renvoie category='unclear', confidence=0 si l'appel
 * échoue (le signalement reste créé sans classification).
 *
 * @return array{category:string, confidence:float, notes:string, severity:int}
 * =========================================================================== */
function classify_pollution_photo(string $imagePath, string $description = '', string $zoneName = ''): array
{
    $fallback = ['category' => 'unclear', 'confidence' => 0.0, 'notes' => '', 'severity' => 0];

    if (!function_exists('analyze_report_image_structured')) {
        return $fallback;
    }
    $struct = analyze_report_image_structured($imagePath, $description, 'other', $zoneName);
    if (!is_array($struct)) {
        return $fallback;
    }

    $fake      = (int)($struct['fake_score'] ?? 100);   // 100 = hors-sujet
    $intensity = (string)($struct['intensity'] ?? 'low');
    $severity  = (int)($struct['severity'] ?? 0);
    $srcCat    = (string)($struct['category'] ?? 'other');
    $color     = strtolower((string)($struct['color'] ?? ''));

    // Image jugée hors-sujet / non fiable -> unclear.
    if ($fake >= 70) {
        return ['category' => 'unclear',
                'confidence' => round((100 - $fake) / 100, 2),
                'notes' => (string)($struct['text'] ?? ''),
                'severity' => $severity];
    }

    // Traduction vers les catégories de pollution.
    $category = 'clear_sky';
    if ($srcCat === 'smoke' || in_array($color, ['black', 'grey', 'gray', 'noir', 'gris'], true)) {
        $category = 'industrial_smoke';
    } elseif ($srcCat === 'dust') {
        $category = 'dust';
    } elseif ($intensity === 'high' || $intensity === 'medium') {
        $category = 'haze';
    } elseif ($severity >= 4) {
        $category = 'haze';
    }

    // Confiance = (1 - fake) pondérée par l'intensité visuelle.
    $intBonus = ['low' => 0.6, 'medium' => 0.8, 'high' => 1.0][$intensity] ?? 0.6;
    $confidence = round(((100 - $fake) / 100) * $intBonus, 2);

    return [
        'category'   => $category,
        'confidence' => $confidence,
        'notes'      => (string)($struct['text'] ?? ''),
        'severity'   => $severity,
    ];
}
