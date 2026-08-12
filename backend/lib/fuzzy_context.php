<?php
declare(strict_types=1);

/**
 * Unified helper that builds the crisp inputs for the fuzzy engine from a
 * user_id (vulnerability + zone pollution + active alerts + recent symptoms +
 * age hint) and returns the full fuzzy recommendation.
 *
 * This lets EVERY recommendation endpoint (dashboard reco, diary AI, triage,
 * tips, weekly summary, chatbot) reuse the exact same fuzzy reasoning — so the
 * fuzzy logic is no longer confined to the dashboard.
 *
 * Returns:
 *   [
 *     'inputs'        => ['pollution'=>…, 'vulnerability'=>…, …],
 *     'risk_score'    => 0..100,
 *     'urgency_level' => 'low'|'moderate'|'high'|'critical',
 *     'fired_rules'   => [{id,activation,consequent,label}, ...],
 *     'explanation'   => string,
 *     'actions'       => string[],
 *     'context'       => ['zone'=>…, 'flags'=>[...], 'alerts'=>n],
 *   ]
 *
 * If the user id is unknown or the database is empty, defaults are used so
 * the call NEVER throws — the caller can still build a useful response.
 */

require_once __DIR__ . '/fuzzy.php';

/**
 * Build the fuzzy inputs for a given user, then run fuzzy_recommend().
 *
 * @param PDO $pdo      PDO connection (db())
 * @param int $userId   Internal users.id (0 = anonymous / no profile)
 * @param array $opts   Optional overrides:
 *                        - pollution     int 0..100
 *                        - zone_id       int
 *                        - extra_symptom 'mild'|'moderate'|'severe' (e.g. one
 *                                        being triaged right now)
 *                        - age           int (overrides profile age)
 * @return array        See header.
 */
function fuzzy_for_user(PDO $pdo, int $userId, array $opts = []): array
{
    /* --- 1. Vulnerability + flags from fragile_profiles ---------------- */
    $flags = [];
    $vulnerability = 0.0;
    $ageHint = (int)($opts['age'] ?? 30);
    $userRow = null;
    if ($userId > 0) {
        try {
            $u = $pdo->prepare('SELECT id, age, zone_id FROM users WHERE id = ? LIMIT 1');
            $u->execute([$userId]);
            $userRow = $u->fetch() ?: null;
            if ($userRow && !empty($userRow['age'])) $ageHint = (int)$userRow['age'];
        } catch (Throwable $e) { /* age column might not exist */ }

        try {
            $stmt = $pdo->prepare('SELECT * FROM fragile_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $fp = $stmt->fetch() ?: null;
            if ($fp) {
                if (!empty($fp['has_asthma']))        { $flags[] = 'asthma';                      $vulnerability += 2.0; }
                if (!empty($fp['has_heart_disease'])) { $flags[] = 'heart disease';               $vulnerability += 2.0; }
                if (!empty($fp['has_allergy']))       { $flags[] = 'chronic respiratory allergy'; $vulnerability += 1.0; }
                if (!empty($fp['is_pregnant']))       { $flags[] = 'pregnancy';                   $vulnerability += 2.0; }
                if (!empty($fp['is_child']))          { $flags[] = 'child (<12 years)';           $vulnerability += 2.0; $ageHint = 8; }
                if (!empty($fp['is_elderly']))        { $flags[] = 'senior (>65 years)';          $vulnerability += 1.5; $ageHint = 72; }
            }
        } catch (Throwable $e) { /* table missing — vulnerability stays 0 */ }
    }
    $vulnerability = min(10.0, $vulnerability);

    /* --- 2. Zone + pollution -------------------------------------------- */
    $zoneId = (int)($opts['zone_id'] ?? ($userRow['zone_id'] ?? 0));
    $zone = null;
    $pollutionLevel = isset($opts['pollution']) ? (int)$opts['pollution'] : 0;
    if ($zoneId > 0) {
        try {
            $z = $pdo->prepare('SELECT z.*, COALESCE(rs.score, z.pollution_level) AS score
                                  FROM zones z LEFT JOIN risk_scores rs ON rs.zone_id = z.id
                                 WHERE z.id = ? LIMIT 1');
            $z->execute([$zoneId]);
            $zone = $z->fetch() ?: null;
            if ($zone && !isset($opts['pollution'])) $pollutionLevel = (int)$zone['score'];
        } catch (Throwable $e) { /* zones table missing — keep defaults */ }
    }
    if ($pollutionLevel <= 0 && isset($opts['pollution'])) {
        $pollutionLevel = (int)$opts['pollution'];
    }

    /* --- 3. Active alerts on the zone (last 24h) ------------------------ */
    $alertsCount = 0;
    if ($zone) {
        try {
            $a = $pdo->prepare("SELECT COUNT(*) FROM alerts
                                  WHERE zone_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR
                                    AND resolved = 0");
            $a->execute([(int)$zone['id']]);
            $alertsCount = (int)$a->fetchColumn();
        } catch (Throwable $e) { /* ignore */ }
    }

    /* --- 4. Recent symptom severity (last 24h, weighted) ---------------- */
    $sympSev = 0.0;
    if ($userId > 0) {
        try {
            $s = $pdo->prepare("SELECT severity FROM symptoms
                                  WHERE citizen_id = ? AND reported_at >= NOW() - INTERVAL 24 HOUR");
            $s->execute([$userId]);
            foreach ($s->fetchAll() as $row) {
                $w = ['mild' => 1.0, 'moderate' => 2.0, 'severe' => 4.0][$row['severity']] ?? 1.0;
                $sympSev += $w;
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    if (!empty($opts['extra_symptom'])) {
        $w = ['mild' => 1.0, 'moderate' => 2.0, 'severe' => 4.0][$opts['extra_symptom']] ?? 1.0;
        $sympSev += $w;
    }
    $sympSev = min(10.0, $sympSev);

    /* --- 5. Fuzzy inference --------------------------------------------- */
    $inputs = [
        'pollution'     => $pollutionLevel,
        'vulnerability' => $vulnerability,
        'symptom_sev'   => $sympSev,
        'alerts_24h'    => $alertsCount,
        'age'           => $ageHint,
    ];
    $fuzzy = fuzzy_recommend($inputs);

    /* --- 6. Optional audit log ------------------------------------------ */
    try {
        $log = $pdo->prepare(
            "INSERT INTO fuzzy_reco_logs
             (user_id, zone_id, pollution, vulnerability, symptom_sev,
              alerts_24h, age, risk_fuzzy, urgency_level, fired_rules)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $log->execute([
            $userId ?: null,
            $zone['id'] ?? null,
            (int)$pollutionLevel,
            (int)round($vulnerability),
            (int)round($sympSev),
            $alertsCount,
            $ageHint,
            $fuzzy['risk_score'],
            $fuzzy['urgency_level'],
            json_encode(array_map(fn($r) => [
                'id' => $r['id'], 'activation' => $r['activation'], 'consequent' => $r['consequent'],
            ], $fuzzy['fired_rules'])),
        ]);
    } catch (Throwable $e) { /* table missing — silently skip */ }

    return [
        'inputs'        => $inputs,
        'risk_score'    => $fuzzy['risk_score'],
        'urgency_level' => $fuzzy['urgency_level'],
        'fired_rules'   => $fuzzy['fired_rules'],
        'explanation'   => $fuzzy['explanation'],
        'actions'       => $fuzzy['actions'],
        'context'       => [
            'zone'   => $zone ? ['id' => (int)$zone['id'], 'name' => (string)$zone['name']] : null,
            'flags'  => $flags,
            'alerts' => $alertsCount,
        ],
    ];
}

/**
 * Short human-readable summary of the fuzzy result, useful as a Groq prompt
 * prefix so the LLM stays consistent with the deterministic engine.
 */
function fuzzy_prompt_prefix(array $fz): string
{
    $rules = '';
    foreach (array_slice($fz['fired_rules'] ?? [], 0, 3) as $r) {
        $rules .= sprintf("  • R%d (%d%%): %s\n",
            (int)$r['id'], (int)round(($r['activation'] ?? 0) * 100), (string)($r['label'] ?? ''));
    }
    $ctx = sprintf(
        "Fuzzy-logic risk assessment for this citizen (Mamdani inference):\n"
        . "  - Crisp risk score: %.1f / 100\n  - Urgency level: %s\n"
        . "  - Top fired rules:\n%s"
        . "  - Vulnerability flags: %s\n",
        (float)$fz['risk_score'],
        (string)$fz['urgency_level'],
        $rules ?: "  (none)\n",
        empty($fz['context']['flags']) ? 'none' : implode(', ', $fz['context']['flags'])
    );
    return $ctx;
}
