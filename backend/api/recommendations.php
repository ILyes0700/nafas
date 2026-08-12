<?php
/**
 * C9 — Personalized health recommendation (AUTOMATIC).
 *
 * Decision pipeline (deterministic, explainable, offline-capable):
 *   1. Fuzzy Type-1 (Mamdani)      — crisp inputs → risk + action plan
 *   2. Fuzzy Type-2 (Interval, KM) — adds uncertainty handling
 *   3. AI/ML model snapshot        — best trained model / prediction
 *   → blended by ai_reco_for_user()  [backend/lib/ai_reco.php]
 *
 * The blended decision is FINAL. Groq (llama-3.3-70b) only paraphrases it.
 * If Groq is unavailable we still ship the decision with a template message.
 *
 * Endpoint: GET (auth required) → JSON
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/groq_client.php';
require_once __DIR__ . '/../lib/ai_reco.php';

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo = db();

/* ===================================================================
 *  STAGE 1 — UNIFIED AI DECISION (model + fuzzy type-2 + type-1)
 * =================================================================== */
$ai      = ai_reco_for_user($pdo, (int)$me['id']);
$flags   = $ai['context']['flags'] ?? [];
$zoneName = $ai['context']['zone']['name'] ?? null;
$model   = $ai['model'];
$t2      = $ai['type2'];

/* ===================================================================
 *  STAGE 2 — Natural-language wrapping via Groq (optional)
 * =================================================================== */
$flagsLine = $flags ? implode(', ', $flags) : 'no known vulnerabilities';

$system = <<<TXT
You are Nafass, a health assistant for Gabès, Tunisia. A HYBRID AI ENGINE
(an AI/ML model + Fuzzy Type-2 Karnik-Mendel inference) has ALREADY computed
the risk and the action plan. Your role is ONLY to phrase the result naturally
for the citizen — DO NOT change the risk level or invent advice contradicting
the engine.

Write 4 short sentences max, in plain English, ending exactly with:
"Risk for you: {urgency}."
where {urgency} matches the engine output. No emojis, no lists.
TXT;

$actionsTxt = implode("\n", array_map(fn($a) => '- ' . $a, array_slice($ai['actions'], 0, 5)));
$user = sprintf(
    "Hybrid AI decision\n------------------\nAI model: %s%s\nFuzzy Type-2 score: %.0f/100 (uncertainty band ±%.0f), level %s\nBlended risk score: %.0f/100\nUrgency: %s\nProfile: %s\nZone: %s\n\nAction plan:\n%s\n\nWrite the citizen-facing recommendation now.",
    ($model['best_model'] ?: 'AI ensemble'), ($model['trained'] ? '' : ' (not trained yet)'),
    (float)$t2['score'], (float)$t2['uncertainty_band'], strtoupper((string)$t2['risk_level']),
    (float)$ai['risk_score'], $ai['urgency_level'], $flagsLine,
    $zoneName ?: 'n/a', $actionsTxt ?: '- Stay aware of air quality changes.'
);

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $user],
];

$llm = groq_chat_call($messages, 'llama-3.3-70b-versatile', [
    'temperature' => 0.4,
    'max_tokens'  => 300,
    'timeout'     => 12,
]);

/* ===================================================================
 *  STAGE 3 — Local fallback if the LLM is down
 * =================================================================== */
if ($llm['ok']) {
    $reco = trim((string)$llm['content']);
    $source = 'ai+type2+groq';
} else {
    $reco = sprintf(
        '%s combined with Fuzzy Type-2 rates your risk as %s (score %.0f/100, uncertainty ±%.0f). %s Risk for you: %s.',
        ($model['best_model'] ?: 'The AI + Fuzzy engine'), $ai['urgency_level'], (float)$ai['risk_score'],
        (float)$t2['uncertainty_band'],
        $ai['actions'][0] ?? 'Stay aware of changes in air quality.',
        $ai['urgency_level']
    );
    $source = 'ai+type2+template';
}

json_response([
    'ok'             => true,
    'reco'           => $reco,
    'recommendation' => $reco,
    'risk'           => $ai['urgency_level'],
    'risk_level'     => $ai['urgency_level'],
    'risk_score'     => $ai['risk_score'],
    'source'         => $source,
    'flags'          => $flags,
    'zone'           => $zoneName,
    'explanation'    => $ai['explanation'],
    // Type-1 kept under `fuzzy` for backward compatibility with the UI.
    'fuzzy'          => [
        // Displayed score/urgency now reflect the BLENDED hybrid decision
        // (Fuzzy Type-2 dominant + AI model), not just the Type-1 Mamdani.
        'risk_score'  => $ai['risk_score'],
        'urgency'     => $ai['urgency_level'],
        'fired_rules' => $ai['type1']['fired_rules'],
        'explanation' => $ai['explanation'],
        'actions'     => $ai['actions'],
    ],
    'type2'          => $t2,
    'model'          => $model,
]);
