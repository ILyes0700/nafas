<?php
/**
 * C10 — Triage IA d'un symptôme (modèle de raisonnement Groq).
 *
 * POST { symptom_id?:int, symptom?:string, severity?:'mild|moderate|severe', notes?:string }
 *
 * Si `symptom_id` est fourni, on lit les données depuis la table `symptoms`.
 * Sinon on utilise les champs `symptom`, `severity`, `notes` envoyés.
 *
 * Le modèle deepseek-r1-distill-llama-70b raisonne et renvoie :
 *   { triage_text:"…", triage_urgency:"low|medium|high|severe" }
 *
 * Disclaimer médico-légal : la réponse N'EST PAS un diagnostic — c'est une
 * orientation. Le texte affiché côté UI le rappelle explicitement.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/groq_client.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/ai_reco.php';   // unified Fuzzy Type-1 + Type-2 + AI engine

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$pdo = db();

/* Rate limit : 20 triages / heure */
$scope = rate_limit_scope_key('triage');
if (!rate_limit_check($pdo, $scope, 'triage', 20, 3600)) {
    json_response(['ok' => false, 'error' => 'Too many requests. Please wait.', 'code' => 'rate_limited'], 429);
}

$in = read_json_input();
$symptomId = isset($in['symptom_id']) ? (int)$in['symptom_id'] : 0;

$symptom  = trim((string)($in['symptom']  ?? ''));
$severity = (string)($in['severity'] ?? 'mild');
$notes    = trim((string)($in['notes']    ?? ''));
$zoneName = '';
$pollution = null;

if ($symptomId > 0) {
    $row = $pdo->prepare(
        "SELECT s.*, z.name AS zone_name, COALESCE(rs.score, z.pollution_level) AS pollution
         FROM symptoms s LEFT JOIN zones z ON z.id = s.zone_id
                         LEFT JOIN risk_scores rs ON rs.zone_id = s.zone_id
         WHERE s.id = ? LIMIT 1"
    );
    $row->execute([$symptomId]);
    $r = $row->fetch();
    if ($r) {
        $symptom   = (string)$r['symptom'];
        $severity  = (string)$r['severity'];
        $notes     = (string)($r['notes'] ?? '');
        $zoneName  = (string)($r['zone_name'] ?? '');
        $pollution = isset($r['pollution']) ? (int)$r['pollution'] : null;
    }
}

if ($symptom === '') {
    json_response(['ok' => false, 'error' => 'symptom_required'], 400);
}

/* ---------- Unified AI recommendation (Fuzzy Type-1 + Type-2 + ML) ---------- */
$ai = ai_reco_for_user($pdo, (int)$me['id'], [
    'pollution'     => $pollution,
    'extra_symptom' => $severity,
]);
$fz = [
    'risk_score'    => $ai['risk_score'],
    'urgency_level' => $ai['urgency_level'],
    'fired_rules'   => $ai['type1']['fired_rules'],
    'explanation'   => $ai['explanation'],
    'inputs'        => $ai['inputs'],
];

$ctx = ai_reco_prompt_block($ai) . "\n"
     . "Symptom: $symptom\nReported severity: $severity";
if ($notes !== '')      $ctx .= "\nNotes: $notes";
if ($zoneName !== '')   $ctx .= "\nZone: $zoneName";
if ($pollution !== null)$ctx .= "\nLocal pollution level: {$pollution}/100";

$system = <<<TXT
You are a health-orientation assistant for the Nafass platform — Gabès.
You receive a symptom reported by a citizen and the context (possible local
industrial pollution). You MUST:

1. Think about possible causes (pollution, common viral infections, stress,
   seasonal allergies, etc.).
2. Provide ONE orientation text in English (≤ 130 words), structured as:
   - "Likely causes:" (max 3, ranked by likelihood)
   - "Immediate advice:" (steps to take at home)
   - "When to consult:" (warning signs requiring a doctor)
   - "Disclaimer:" exact text:
     "This orientation is not a medical diagnosis. Please consult a
      healthcare professional if in doubt."
3. Choose an urgency level from: low | medium | high | severe.
4. Return EXACTLY this JSON object:
   { "triage_text": "...", "triage_urgency": "..." }
   No text before or after. No markdown.
TXT;

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $ctx],
];

$obj = groq_chat_json($messages, 'deepseek-r1-distill-llama-70b', [
    'temperature' => 0.3,
    'max_tokens'  => 700,
    'timeout'     => 25,
]);

if (!$obj) {
    /* Fallback si le modèle de raisonnement renvoie du texte non-JSON :
       on relance en llama-3.3-70b qui obéit mieux au format JSON. */
    $obj = groq_chat_json($messages, 'llama-3.3-70b-versatile', [
        'temperature' => 0.3,
        'max_tokens'  => 600,
        'timeout'     => 20,
    ]);
}

if (!$obj || empty($obj['triage_text'])) {
    json_response(['ok' => false, 'error' => 'triage_unavailable'], 502);
}

$urgency = $obj['triage_urgency'] ?? 'low';
if (!in_array($urgency, ['low','medium','high','severe'], true)) $urgency = 'low';

/* Escalate triage to match the fuzzy urgency if the LLM under-estimated */
$rankT = ['low'=>0,'medium'=>1,'high'=>2,'severe'=>3];
$rankF = ['low'=>0,'moderate'=>1,'high'=>2,'critical'=>3];
$fzUrg = $fz['urgency_level'];
if (($rankF[$fzUrg] ?? 0) > ($rankT[$urgency] ?? 0)) {
    $urgency = ($fzUrg === 'moderate') ? 'medium' : (($fzUrg === 'critical') ? 'severe' : $fzUrg);
}

$text = trim((string)$obj['triage_text']);
if (mb_strlen($text) > 2400) $text = mb_substr($text, 0, 2397) . '…';

if ($symptomId > 0) {
    try {
        $upd = $pdo->prepare(
            "UPDATE symptoms SET triage_text = ?, triage_urgency = ?, triage_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([$text, $urgency, $symptomId]);
    } catch (Throwable $e) {
        error_log('[triage] persist: ' . $e->getMessage());
    }
}

json_response([
    'ok'             => true,
    'triage_text'    => $text,
    'triage_urgency' => $urgency,
    'symptom_id'     => $symptomId ?: null,
    'fuzzy'          => [
        'risk_score'    => $fz['risk_score'],
        'urgency_level' => $fz['urgency_level'],
        'fired_rules'   => $fz['fired_rules'],
        'explanation'   => $fz['explanation'],
        'inputs'        => $fz['inputs'],
    ],
    'type2'          => $ai['type2'],
    'model'          => $ai['model'],
]);
