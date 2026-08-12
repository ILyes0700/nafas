<?php
declare(strict_types=1);
/**
 * UPGRADE v8 — Part 49.2 : classification automatique du texte libre (NLP léger).
 * Utilise le client Groq déjà configuré (groq_client.php). Dégrade vers un
 * classifieur par mots-clés, puis 'autre'/'non_classe' si tout échoue.
 */

require_once __DIR__ . '/groq_client.php';

if (!function_exists('_nlp_keyword_category')) {
    function _nlp_keyword_category(string $text): array
    {
        $t = mb_strtolower($text);
        $map = [
            'odeur'     => ['odeur', 'pue', 'puanteur', 'senteur', 'nauséabond', 'gaz', 'رائحة'],
            'fumee'     => ['fumée', 'fumee', 'smoke', 'noir', 'cheminée', 'دخان'],
            'poussiere' => ['poussière', 'poussiere', 'dust', 'sable', 'غبار'],
            'bruit'     => ['bruit', 'noise', 'son', 'vacarme', 'ضجيج'],
        ];
        foreach ($map as $cat => $kw) {
            foreach ($kw as $k) {
                if ($k !== '' && mb_strpos($t, $k) !== false) {
                    return ['category' => $cat, 'confidence' => 0.55, 'source' => 'keywords'];
                }
            }
        }
        return ['category' => 'autre', 'confidence' => 0.30, 'source' => 'keywords'];
    }
}

/**
 * @return array {category: 'odeur'|'fumee'|'poussiere'|'bruit'|'autre'|'non_classe', confidence: float, source: string}
 */
function classify_report_text(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return ['category' => 'non_classe', 'confidence' => 0.0, 'source' => 'empty'];
    }

    // 1) Tentative LLM structurée.
    try {
        $sys = "Tu es un classifieur de signalements de pollution à Gabès. "
             . "Réponds STRICTEMENT en JSON: {\"category\":\"odeur|fumee|poussiere|bruit|autre\",\"confidence\":0..1}. "
             . "Aucune autre clé, aucun texte hors JSON.";
        $out = groq_chat_json(
            [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user',   'content' => $text],
            ],
            GROQ_MODEL,
            ['temperature' => 0.0, 'max_tokens' => 60]
        );
        if (is_array($out) && !empty($out['category'])) {
            $cat = strtolower((string)$out['category']);
            $allowed = ['odeur', 'fumee', 'poussiere', 'bruit', 'autre'];
            if (in_array($cat, $allowed, true)) {
                $conf = isset($out['confidence']) ? (float)$out['confidence'] : 0.7;
                return ['category' => $cat, 'confidence' => max(0.0, min(1.0, $conf)), 'source' => 'groq'];
            }
        }
    } catch (Throwable $e) { /* degradation */ }

    // 2) Repli mots-clés.
    return _nlp_keyword_category($text);
}
