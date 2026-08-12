<?php
declare(strict_types=1);

/**
 * Client Groq partagé (LLM texte uniquement).
 *
 * Utilisé par :
 *  • api/recommendations.php  (C9 — recommandations santé personnalisées)
 *  • api/triage.php           (C10 — triage IA des symptômes — reasoning model)
 *  • api/tips.php             (C12 — conseils environnementaux du jour)
 *  • api/weekly-summary.php   (C6 — résumé hebdomadaire pour les autorités)
 *
 * Pour la vision → backend/lib/groq_vision.php
 * Pour la voix  → backend/lib/groq_voice.php
 */

require_once __DIR__ . '/../config/groq.php';

const GROQ_CHAT_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

// Active si tu n'as pas configuré cacert.pem côté php.ini (WAMP local).
const GROQ_CLIENT_INSECURE = true;

/**
 * Appel chat/completions générique.
 *
 * @param array  $messages   tableau de messages OpenAI: [['role'=>..,'content'=>..], ...]
 * @param string $model      ex: 'llama-3.3-70b-versatile'
 * @param array  $opts       {temperature?:float, max_tokens?:int, response_format?:array, timeout?:int}
 * @return array { ok:bool, content:?string, error:?string, raw:?array }
 */
function groq_chat_call(array $messages, string $model = GROQ_MODEL, array $opts = []): array
{
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '' || stripos(GROQ_API_KEY, 'gsk_') !== 0) {
        return ['ok' => false, 'content' => null, 'error' => 'no-api-key', 'raw' => null];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'content' => null, 'error' => 'php-curl-missing', 'raw' => null];
    }

    $payload = [
        'model'       => $model,
        'temperature' => $opts['temperature'] ?? 0.3,
        'max_tokens'  => $opts['max_tokens']  ?? 700,
        'messages'    => $messages,
    ];
    if (!empty($opts['response_format'])) {
        $payload['response_format'] = $opts['response_format'];
    }

    $ch = curl_init(GROQ_CHAT_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => $opts['timeout'] ?? 30,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    if (GROQ_CLIENT_INSECURE) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw    = curl_exec($ch);
    $errno  = curl_errno($ch);
    $http   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errStr = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'content' => null, 'error' => "curl_errno=$errno $errStr", 'raw' => null];
    }
    if ($http >= 400 || !$raw) {
        return ['ok' => false, 'content' => null, 'error' => "http=$http", 'raw' => null];
    }

    $data = json_decode($raw, true);
    $txt  = $data['choices'][0]['message']['content'] ?? null;
    if (!$txt) {
        return ['ok' => false, 'content' => null, 'error' => 'empty-response', 'raw' => $data];
    }

    return ['ok' => true, 'content' => $txt, 'error' => null, 'raw' => $data];
}

/**
 * Variante qui force une réponse JSON parsée (response_format=json_object).
 * Renvoie un tableau associatif, ou null en cas d'échec.
 */
function groq_chat_json(array $messages, string $model = GROQ_MODEL, array $opts = []): ?array
{
    $opts['response_format'] = ['type' => 'json_object'];
    $r = groq_chat_call($messages, $model, $opts);
    if (!$r['ok'] || !$r['content']) return null;
    $parsed = json_decode($r['content'], true);
    if (!is_array($parsed)) return null;
    return $parsed;
}
