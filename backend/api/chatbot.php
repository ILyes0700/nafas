<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/groq.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ai_reco.php';
require_once __DIR__ . '/../lib/rag_context.php';

$pdo = db();

// ====== LECTURE de l'historique (GET) ======
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $rows = $pdo->query("SELECT * FROM chatbot_logs ORDER BY created_at DESC LIMIT 30")->fetchAll();
    json_response(['logs' => $rows]);
}

// ====== ÉCRITURE (POST) ======
$in      = read_json_input();
$message = trim($in['message'] ?? '');
$user    = $in['user_label'] ?? $in['user_name'] ?? 'Anonymous';

if ($message === '') {
    json_response(['ok' => false, 'error' => 'Empty message'], 400);
}

// 1. Construction du contexte temps réel à partir de la base
$ctx = build_chatbot_context($pdo);

// 1b. AI models FIRST: fetch the AI/ML + Fuzzy Type-2 assessment BEFORE any
//     reply, so Nafass answers from the models' result (never random).
$me = function_exists('auth_user') ? auth_user() : null;
$ctx['ai'] = ai_reco_for_user($pdo, $me ? (int)$me['id'] : 0);
$ctx['fuzzy'] = [
    // Blended hybrid decision (Fuzzy Type-2 + AI model) so the chatbot tag and
    // reply match the unified assessment, not just the Type-1 Mamdani.
    'risk_score'    => $ctx['ai']['risk_score'],
    'urgency_level' => $ctx['ai']['urgency_level'],
    'fired_rules'   => $ctx['ai']['type1']['fired_rules'],
    'explanation'   => $ctx['ai']['explanation'],
];

// 1c. PART 47 — RAG: retrieve concrete facts (alerts, health impact, XAI)
//     and inject them so Groq grounds its answer instead of hallucinating.
if (function_exists('build_rag_context')) {
    $zoneForRag = $ctx['worst_zone_id'] ?? ($me['zone_id'] ?? null);
    $rag = build_rag_context($pdo, $zoneForRag !== null ? (int)$zoneForRag : null, $message);
    $ctx['rag'] = $rag['text'];
    $ctx['rag_sources'] = $rag['sources'];
}

/* ---------- UPGRADE v8 — Part 51 : mémoire persistante, urgence, registre de langue ---------- */
try {
    require_once __DIR__ . '/../lib/chatbot_emergency_detector.php';
    if (function_exists('detect_emergency_signal'))     $ctx['emergency'] = detect_emergency_signal($message);
    if (function_exists('detect_language_register'))    $ctx['lang_instruction'] = language_register_instruction(detect_language_register($message));
} catch (Throwable $e) { /* dégradation gracieuse */ }
if (!empty($me)) {
    // Capture légère d'une condition santé mentionnée -> mémoire persistante.
    try {
        $lm = function_exists('mb_strtolower') ? mb_strtolower($message) : strtolower($message);
        $conds = ['asthme'=>'asthme','asthma'=>'asthme','bpco'=>'BPCO','copd'=>'BPCO','diab'=>'diabète','allerg'=>'allergie','enceinte'=>'grossesse','pregnan'=>'grossesse'];
        foreach ($conds as $needle => $val) {
            if (strpos($lm, $needle) !== false) {
                $up = $pdo->prepare('INSERT INTO chatbot_user_memory (user_id, memory_key, memory_value, updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE memory_value = VALUES(memory_value), updated_at = NOW()');
                $up->execute([(int)$me['id'], 'condition', $val]);
                break;
            }
        }
    } catch (Throwable $e) { /* table optionnelle */ }
    // Relecture de la mémoire pour enrichir le prompt.
    try {
        $mem = $pdo->prepare('SELECT memory_key, memory_value FROM chatbot_user_memory WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20');
        $mem->execute([(int)$me['id']]);
        $lines = [];
        foreach ($mem->fetchAll() as $row) { $lines[] = '- ' . $row['memory_key'] . ' : ' . $row['memory_value']; }
        if ($lines) $ctx['memory'] = implode("\n", $lines);
    } catch (Throwable $e) { /* table optionnelle */ }
}

// 2. Filtre rapide hors-sujet (économise des tokens)
if (!groq_is_topic_relevant($message)) {
    $reply = "I am نفاس (Nafass), the health & environment assistant for Gabès. I can only answer medical, environmental, or local-alert questions. Ask me about air quality, your symptoms, or what to do during an alert.";
    finalize_chat($pdo, $user, $message, $reply, 'off-topic-guard', $ctx);
}

// 3. Tentative d'appel à Groq
[$reply, $intent, $err] = groq_chat($message, $ctx);

// 4. Fallback local si Groq indisponible ou clé manquante
if ($reply === null) {
    [$reply, $intent] = local_fallback_reply($message, $ctx);
    $intent = $intent . ($err ? '|fb:' . substr($err, 0, 20) : '|fb');
}

finalize_chat($pdo, $user, $message, $reply, $intent, $ctx);


/* ================================================================
 *  HELPERS
 * ================================================================ */

function finalize_chat(PDO $pdo, string $user, string $msg, string $reply, string $intent, array $ctx): void
{
    // intent VARCHAR(60) dans le schéma → troncature défensive
    $intent = mb_substr($intent, 0, 60);
    $stmt = $pdo->prepare(
        "INSERT INTO chatbot_logs (user_label, message, response, intent) VALUES (?,?,?,?)"
    );
    $stmt->execute([$user, $msg, $reply, $intent]);
    if (!empty($ctx['rag_sources'])) {
        try {
            $pdo->prepare('UPDATE chatbot_logs SET rag_sources = ? WHERE id = ?')
                ->execute([json_encode($ctx['rag_sources']), (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) { /* rag_sources column optional */ }
    }

    $resp = [
        'ok'            => true,
        'response'      => $reply,                 // ← clé attendue par chatbot.js
        'reply'         => $reply,                 // ← alias rétrocompatible
        'intent'        => $intent,
        'global_status' => $ctx['global_status'] ?? 'safe',
    ];
    if (!empty($ctx['fuzzy']) && isset($ctx['fuzzy']['risk_score'])) {
        $resp['fuzzy'] = [
            'risk_score'    => $ctx['fuzzy']['risk_score'],
            'urgency_level' => $ctx['fuzzy']['urgency_level'],
            'fired_rules'   => array_slice($ctx['fuzzy']['fired_rules'] ?? [], 0, 3),
            'explanation'   => $ctx['fuzzy']['explanation'] ?? '',
        ];
    }
    json_response($resp);
}

function build_chatbot_context(PDO $pdo): array
{
    $worst = $pdo->query("
        SELECT z.name, z.status, COALESCE(rs.score, z.pollution_level) AS score
        FROM zones z LEFT JOIN risk_scores rs ON rs.zone_id = z.id
        ORDER BY (z.status='critical') DESC, score DESC LIMIT 1
    ")->fetch();
    $avg = (float)$pdo->query("
        SELECT AVG(COALESCE(rs.score, z.pollution_level))
        FROM zones z LEFT JOIN risk_scores rs ON rs.zone_id = z.id
    ")->fetchColumn();
    $alerts = (int)$pdo->query("
        SELECT COUNT(*) FROM alerts WHERE created_at >= NOW() - INTERVAL 1 DAY
    ")->fetchColumn();
    $globalStatus = function_exists('global_status') ? global_status() : ($worst['status'] ?? 'safe');

    return [
        'global_status' => $globalStatus,
        'avg_risk'      => round($avg, 1),
        'worst_zone'    => $worst ? ($worst['name'] . ' (' . $worst['status'] . ')') : '—',
        'alerts_count'  => $alerts,
    ];
}

/**
 * Appel à l'API Groq (compatible OpenAI chat/completions).
 * @return array [reply|null, intent, error_string]
 */
function groq_chat(string $message, array $ctx): array
{
    if (GROQ_API_KEY === '' || stripos(GROQ_API_KEY, 'gsk_') !== 0) {
        return [null, 'no-key', 'no-api-key'];
    }
    if (!function_exists('curl_init')) {
        return [null, 'no-curl', 'php-curl-missing'];
    }

    $payload = [
        'model'       => GROQ_MODEL,
        'temperature' => GROQ_TEMPERATURE,
        'max_tokens'  => GROQ_MAX_TOKENS,
        'messages'    => [
            ['role' => 'system', 'content' => groq_system_prompt($ctx)],
            ['role' => 'user',   'content' => $message],
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
        CURLOPT_TIMEOUT        => GROQ_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        // ── WAMP/XAMPP often ships without a CA bundle on Windows,
        //    which makes the call fail with cURL error 60. Disabling
        //    peer verification is acceptable for local development.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw    = curl_exec($ch);
    $errno  = curl_errno($ch);
    $http   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errStr = curl_error($ch);
    curl_close($ch);

    if ($errno || $http >= 400 || !$raw) {
        // Surface the real cURL/HTTP error in the intent string so it's
        // visible in chatbot_logs — much easier to debug than "unknown".
        $details = $errno ? "curl=$errno:$errStr" : "http=$http";
        return [null, 'groq-error', $details];
    }

    $data = json_decode($raw, true);
    $txt  = $data['choices'][0]['message']['content'] ?? null;
    if (!$txt) return [null, 'groq-empty', 'empty'];

    return [trim($txt), 'groq:' . substr(GROQ_MODEL, 0, 30), ''];
}

/**
 * Fallback local : logique simple par mots-clés.
 */
function local_fallback_reply(string $message, array $ctx): array
{
    $m = mb_strtolower($message);
    $status = $ctx['global_status'];
    $worst  = $ctx['worst_zone'];
    // Ground the fallback on the AI/ML + Fuzzy Type-2 assessment computed above,
    // so even without the LLM the answer reflects the models (not random).
    $riskLine = '';
    if (!empty($ctx['ai']) && isset($ctx['ai']['risk_score'])) {
        $ai = $ctx['ai'];
        $mdl = $ai['model']['best_model'] ?? 'AI model';
        $riskLine = sprintf(
            'AI assessment (%s + Fuzzy Type-2): risk %s (%.0f/100). ',
            $mdl, $ai['urgency_level'], (float)$ai['risk_score']
        );
    }

    if (preg_match('/(cough|asthm|respir|lung|breath|chest|toux|asthm|respir|poumon|souffl|oppress|poitrine|سعال|ربو|صدر)/u', $m)) {
        $reply = $riskLine . "Respiratory symptom noted. With a \"$status\" status in Gabès:"
               . " avoid outdoor physical effort, stay indoors with windows closed."
               . " Stay hydrated. In case of severe breathing difficulty, chest pain, or an asthma attack,"
               . " contact SAMU (190) immediately or see a doctor.";
        return [$reply, 'symptom_check'];
    }
    if (preg_match('/(pollut|air|smoke|smell|odor|chemical|gas|pollu|fumée|fumee|odeur|chimi|gaz|تلوث|هواء|دخان|رائحة)/u', $m)) {
        $reply = $riskLine . "Current air status in Gabès: $status. Most exposed zone: $worst."
               . " Limit prolonged outings if you are sensitive (children, elderly, asthmatics),"
               . " wear a surgical or FFP2 mask if the smell is strong, and report any incident via the Reports page.";
        return [$reply, 'air_query'];
    }
    if (preg_match('/(school|student|class|child|école|ecole|élève|eleve|classe|enfant|طفل|أطفال|مدرسة)/u', $m)) {
        $reply = $riskLine . "For schools: if the level is \"warning\", limit outdoor recess and sports."
               . " If the level is \"critical\", activate school mode in the application,"
               . " suspend physical activities, and notify parents.";
        return [$reply, 'school_query'];
    }
    if (mb_strlen($m) < 12 || preg_match('/(hello|hi |help|bonjour|salut|aide|سلام|اهلا)/u', $m)) {
        return ["Hello 👋 I am نفاس (Nafass). Ask me about air quality in Gabès, your symptoms, or guidance for schools.", 'greeting'];
    }
    return [
        "AI service temporarily unavailable. Rephrase your question (health, pollution, school)"
        . " or check the Help page. For a medical emergency: 190 (SAMU).",
        'general'
    ];
}
