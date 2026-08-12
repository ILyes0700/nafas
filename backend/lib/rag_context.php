<?php
declare(strict_types=1);

/**
 * PART 47 — Contexte RAG (retrieval) pour le chatbot Nafass.
 *
 * Avant de répondre, le chatbot récupère des FAITS (retrieval) au lieu de se
 * fier uniquement au bloc fuzzy injecté dans le system prompt : dernières
 * lectures de la zone, alertes smart_alerts actives, health_impact courant,
 * explications XAI si disponibles. Réduit les hallucinations de Groq.
 *
 * Ce bloc s'AJOUTE au bloc fuzzy/AI existant — il ne remplace pas
 * fuzzy_prompt_prefix() ni ai_reco_prompt_block(). Dégrade proprement : chaque
 * source manquante est simplement omise.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Construit un résumé court (<~500 tokens) de faits récupérés pour la zone.
 *
 * @return array{text:string, sources:array} texte injecté + liste des sources
 *         (la liste sert à remplir chatbot_logs.rag_sources).
 */
function build_rag_context(PDO $pdo, ?int $zoneId, string $userQuestion): array
{
    $lines   = [];
    $sources = [];

    // 1) Dernière lecture de la zone.
    if ($zoneId) {
        try {
            $z = $pdo->prepare('SELECT name, pollution_level, status FROM zones WHERE id = ?');
            $z->execute([$zoneId]);
            if ($row = $z->fetch(PDO::FETCH_ASSOC)) {
                $lines[] = sprintf('- Zone %s: pollution=%s, statut=%s.',
                    $row['name'], $row['pollution_level'], $row['status']);
                $sources[] = ['type' => 'zone', 'id' => $zoneId];
            }
        } catch (Throwable $e) { /* omit */ }
    }

    // 2) Alertes smart_alerts actives (récentes).
    try {
        $sql = 'SELECT title, severity FROM smart_alerts WHERE created_at >= NOW() - INTERVAL 1 DAY';
        $params = [];
        if ($zoneId) { $sql .= ' AND zone_id = ?'; $params[] = $zoneId; }
        $sql .= ' ORDER BY created_at DESC LIMIT 3';
        $q = $pdo->prepare($sql);
        $q->execute($params);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $lines[] = sprintf('- Alerte active (%s): %s', $a['severity'] ?? '?', $a['title'] ?? '');
            $sources[] = ['type' => 'smart_alert', 'title' => $a['title'] ?? null];
        }
    } catch (Throwable $e) { /* omit */ }

    // 3) Health impact courant.
    try {
        $sql = 'SELECT * FROM health_impact';
        $params = [];
        if ($zoneId) { $sql .= ' WHERE zone_id = ?'; $params[] = $zoneId; }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $q = $pdo->prepare($sql);
        $q->execute($params);
        if ($h = $q->fetch(PDO::FETCH_ASSOC)) {
            $risk = $h['risk_level'] ?? ($h['impact_level'] ?? null);
            if ($risk) { $lines[] = "- Impact sanitaire estimé: {$risk}."; $sources[] = ['type' => 'health_impact']; }
        }
    } catch (Throwable $e) { /* omit */ }

    // 4) Explications XAI (SHAP interactions ou LIME) si dispo.
    try {
        $q = $pdo->prepare(
            'SELECT feature_a, feature_b, interaction_strength FROM xai_interactions
             WHERE (? IS NULL OR zone_id = ?) ORDER BY computed_at DESC, rank_order ASC LIMIT 2'
        );
        $q->execute([$zoneId, $zoneId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $lines[] = sprintf('- Interaction clé: %s × %s (force %.2f).',
                $x['feature_a'], $x['feature_b'], (float)$x['interaction_strength']);
            $sources[] = ['type' => 'xai_interaction'];
        }
    } catch (Throwable $e) { /* omit */ }

    if (!$lines) {
        return ['text' => '', 'sources' => []];
    }

    $text = "RETRIEVED FACTS (ground your answer on these, do not contradict them):\n"
          . implode("\n", $lines);
    // Garde compact.
    if (strlen($text) > 1600) $text = substr($text, 0, 1597) . '…';

    return ['text' => $text, 'sources' => $sources];
}
