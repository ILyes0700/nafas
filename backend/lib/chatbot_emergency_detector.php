<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 51.2 & 51.3 : détection d'urgence + registre de langue.
 * Dégradation gracieuse : sans Groq, on retombe sur des heuristiques mots-clés.
 */

require_once __DIR__ . '/groq_client.php';

/**
 * Détecte un signal d'urgence médicale dans le message utilisateur.
 * Politique : 0 faux négatif toléré -> les mots-clés l'emportent (OR), et si le
 * doute persiste on demande confirmation à Groq. En cas d'échec LLM, on garde
 * le résultat mots-clés (côté sûr).
 */
function detect_emergency_signal(string $userMessage): bool
{
    $t = mb_strtolower(trim($userMessage));
    if ($t === '') return false;

    $kw = [
        // FR
        'essouffl', 'peux plus respirer', 'plus respirer', 'du mal à respirer',
        'douleur thoracique', 'douleur à la poitrine', 'poitrine serr', 'oppression',
        'perte de connaissance', 'évanoui', 'evanoui', 'inconscient', 'lèvres bleues',
        'crise d\'asthme', 'suffoque', 'j\'étouffe', 'jetouffe',
        // EN
        'can\'t breathe', 'cannot breathe', 'chest pain', 'passing out', 'unconscious',
        // AR
        'ما نقدرش نتنفس', 'ضيق تنفس', 'ألم في الصدر', 'إغماء',
    ];
    $kwHit = false;
    foreach ($kw as $k) {
        if ($k !== '' && mb_strpos($t, $k) !== false) { $kwHit = true; break; }
    }
    if ($kwHit) return true; // côté sûr : on n'attend pas le LLM

    // Confirmation LLM structurée (uniquement pour rattraper les cas implicites).
    try {
        $out = groq_chat_json(
            [
                ['role' => 'system', 'content' => 'Détecteur d\'urgence médicale respiratoire/cardiaque. Réponds STRICTEMENT en JSON {"emergency":true|false}. En cas de doute, mets true.'],
                ['role' => 'user',   'content' => $userMessage],
            ],
            GROQ_MODEL,
            ['temperature' => 0.0, 'max_tokens' => 20]
        );
        if (is_array($out) && array_key_exists('emergency', $out)) {
            return (bool)$out['emergency'];
        }
    } catch (Throwable $e) { /* dégradation : on garde false (aucun mot-clé détecté) */ }

    return false;
}

/**
 * Détecte le registre de langue pour aligner le system prompt Groq.
 * @return string 'tn_dialect' | 'msa' | 'fr' | 'en'
 */
function detect_language_register(string $text): string
{
    $t = mb_strtolower(trim($text));
    if ($t === '') return 'fr';

    $hasArabic = (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $t);
    if ($hasArabic) {
        // Marqueurs de dialecte tunisien courants.
        $tnMarkers = ['برشا', 'فمّا', 'والو', 'ياسر', 'توّا', 'شنوّة', 'علاش', 'مانيش', 'نحب', 'قاعد'];
        foreach ($tnMarkers as $m) {
            if (mb_strpos($t, $m) !== false) return 'tn_dialect';
        }
        return 'msa';
    }
    // Latin : distingue FR/EN grossièrement.
    $frMarkers = [' le ', ' la ', ' je ', ' vous ', ' bonjour', 'merci', ' est ', ' pollution', ' respir'];
    foreach ($frMarkers as $m) {
        if (mb_strpos(' ' . $t . ' ', $m) !== false) return 'fr';
    }
    $enMarkers = [' the ', ' i ', ' you ', ' hello', ' breath', ' air '];
    foreach ($enMarkers as $m) {
        if (mb_strpos(' ' . $t . ' ', $m) !== false) return 'en';
    }
    return 'fr';
}

/** Libellé humain du registre, pour injection dans le system prompt. */
function language_register_instruction(string $register): string
{
    switch ($register) {
        case 'tn_dialect': return 'Réponds en dialecte tunisien (darija), simple et rassurant.';
        case 'msa':        return 'Réponds en arabe standard moderne, clair et rassurant.';
        case 'en':         return 'Reply in English, clear and reassuring.';
        default:           return 'Réponds en français, clair et rassurant.';
    }
}
