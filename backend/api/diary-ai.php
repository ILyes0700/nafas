<?php
/**
 * Diary AI Insights — personalized recommendations for a citizen, based on
 * their last 30 days of Health Diary entries (chart + history) plus their
 * fragile-profile flags and the current pollution status of their zone.
 *
 * GET → returns JSON:
 *   {
 *     ok: true,
 *     stats: { entries, days_logged, averages, trend_7v30 },
 *     insights: {
 *       summary: "<one paragraph plain-English overview>",
 *       trends:  ["<bullet>", ...],
 *       warnings:["<bullet>", ...],
 *       actions: ["<bullet>", ...],
 *       risk_level: "low|moderate|high"
 *     },
 *     source: "groq" | "fallback",
 *     generated_at: "YYYY-MM-DD HH:MM:SS"
 *   }
 *
 * Cache: 10 minutes per user, via rate_limits table — re-running the call too
 * fast returns the previously generated insight.
 *
 * Strategy:
 *   1. Pull last 30 days of personal_diary
 *   2. Compute averages, trend (last 7 days vs prior 23), worst symptoms
 *   3. Build context (vulnerabilities + zone score + active alerts)
 *   4. Ask Groq for a structured JSON response (system prompt + user payload)
 *   5. Local fallback if no API key / Groq fails
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/groq_client.php';
require_once __DIR__ . '/../lib/ai_reco.php';   // unified Fuzzy Type-1 + Type-2 + AI engine

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo    = db();
$userId = (int)$me['id'];

/* ---------- 0. Cache (10 min) — return last result if fresh ---------- */
$cacheKey  = 'diary-ai:' . $userId;
$cacheFile = sys_get_temp_dir() . '/nafass-diary-ai-' . $userId . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
    $cached = @file_get_contents($cacheFile);
    if ($cached) {
        header('Content-Type: application/json; charset=utf-8');
        echo $cached;
        exit;
    }
}

/* ---------- 1. Pull diary entries (last 30 days) ---------- */
$stmt = $pdo->prepare(
    "SELECT diary_date, mood, cough, breath_diff, eye_irritation, headache, fatigue, notes
       FROM personal_diary
      WHERE user_id = ? AND diary_date >= (CURDATE() - INTERVAL 30 DAY)
      ORDER BY diary_date ASC"
);
$stmt->execute([$userId]);
$entries = $stmt->fetchAll();

if (count($entries) < 1) {
    json_response([
        'ok'           => true,
        'stats'        => ['entries' => 0, 'days_logged' => 0],
        'insights'     => [
            'summary'   => 'You have not logged any diary entries yet. Add a few daily entries (mood, cough, breathing, etc.) and the AI will generate personalized recommendations based on your trends.',
            'trends'    => [],
            'warnings'  => [],
            'actions'   => [
                'Log how you feel today using the form above.',
                'Aim for at least 5 days of entries before requesting an analysis again.',
            ],
            'risk_level'=> 'low',
        ],
        'source'       => 'no-data',
        'generated_at' => date('Y-m-d H:i:s'),
    ]);
}

/* ---------- 2. Compute statistics ---------- */
$keys = ['mood', 'cough', 'breath_diff', 'eye_irritation', 'headache', 'fatigue'];
$avg = $sum7 = $sumOld = $cnt7 = $cntOld = [];
foreach ($keys as $k) { $avg[$k] = 0; $sum7[$k] = 0; $sumOld[$k] = 0; }
$cnt7 = $cntOld = 0;

$cutoff7 = strtotime('-7 days');
foreach ($entries as $e) {
    $ts = strtotime($e['diary_date']);
    foreach ($keys as $k) $avg[$k] += (int)$e[$k];
    if ($ts >= $cutoff7) {
        foreach ($keys as $k) $sum7[$k] += (int)$e[$k];
        $cnt7++;
    } else {
        foreach ($keys as $k) $sumOld[$k] += (int)$e[$k];
        $cntOld++;
    }
}
$N = count($entries);
$avgRound = [];
foreach ($keys as $k) $avgRound[$k] = round($avg[$k] / $N, 2);

$trend = [];
foreach ($keys as $k) {
    $r7   = $cnt7   ? $sum7[$k]   / $cnt7   : null;
    $rOld = $cntOld ? $sumOld[$k] / $cntOld : null;
    if ($r7 !== null && $rOld !== null) {
        $delta = round($r7 - $rOld, 2);
        if (abs($delta) >= 0.4) {
            $trend[$k] = ['recent' => round($r7, 2), 'previous' => round($rOld, 2), 'delta' => $delta];
        }
    }
}

/* worst symptoms (highest 30-day average among non-mood) */
$worst = [];
foreach (['cough', 'breath_diff', 'eye_irritation', 'headache', 'fatigue'] as $k) {
    if ($avgRound[$k] >= 1.5) $worst[] = ['symptom' => $k, 'avg' => $avgRound[$k]];
}
usort($worst, fn($a, $b) => $b['avg'] <=> $a['avg']);

/* ---------- 3. Profile + zone + alerts ---------- */
$flags = [];
try {
    $fp = $pdo->prepare('SELECT * FROM fragile_profiles WHERE user_id = ?');
    $fp->execute([$userId]);
    $f = $fp->fetch();
    if ($f) {
        if ((int)($f['has_asthma']        ?? 0)) $flags[] = 'asthma';
        if ((int)($f['has_heart_disease'] ?? 0)) $flags[] = 'cardiovascular condition';
        if ((int)($f['has_allergy']       ?? 0)) $flags[] = 'respiratory allergy';
        if ((int)($f['is_pregnant']       ?? 0)) $flags[] = 'pregnancy';
        if ((int)($f['is_child']          ?? 0)) $flags[] = 'child (<12)';
        if ((int)($f['is_elderly']        ?? 0)) $flags[] = 'senior (>65)';
    }
} catch (Throwable $e) { /* table may not exist */ }

$zoneCtx = '';
try {
    if (!empty($me['zone_id'])) {
        $z = $pdo->prepare('SELECT name, status, pollution_level FROM zones WHERE id = ? LIMIT 1');
        $z->execute([(int)$me['zone_id']]);
        $zr = $z->fetch();
        if ($zr) {
            $zoneCtx = "Zone: {$zr['name']} — status \"{$zr['status']}\" ({$zr['pollution_level']}/100).";
        }
    }
} catch (Throwable $e) {}

/* ---------- 4. Build context summary for the LLM ---------- */
$labels = [
    'mood' => 'mood (1=low, 5=great)', 'cough' => 'cough', 'breath_diff' => 'breathing difficulty',
    'eye_irritation' => 'eye irritation', 'headache' => 'headache', 'fatigue' => 'fatigue',
];

$avgLines = [];
foreach ($keys as $k) $avgLines[] = "  - {$labels[$k]}: {$avgRound[$k]}/5 (30-day avg)";

$trendLines = [];
foreach ($trend as $k => $t) {
    $arrow = $t['delta'] > 0 ? 'UP' : 'DOWN';
    $trendLines[] = "  - {$labels[$k]}: last 7 days {$t['recent']}/5 vs prior {$t['previous']}/5 ({$arrow} {$t['delta']})";
}

/* ---------- 4b. Unified AI recommendation (Fuzzy Type-1 + Type-2 + ML) ---------- */
$ai = ai_reco_for_user($pdo, $userId);
$fz = [
    'risk_score'    => $ai['risk_score'],
    'urgency_level' => $ai['urgency_level'],
    'actions'       => $ai['actions'],
    'fired_rules'   => $ai['type1']['fired_rules'],
    'explanation'   => $ai['explanation'],
    'inputs'        => $ai['inputs'],
];
$fuzzyCtx = ai_reco_prompt_block($ai);

$ctx = $fuzzyCtx . "\n"
     . "Citizen: {$me['full_name']} — vulnerabilities: " . ($flags ? implode(', ', $flags) : 'none reported') . ".\n"
     . ($zoneCtx ? $zoneCtx . "\n" : '')
     . "Diary entries on record: {$N} over the last 30 days.\n"
     . "Averages:\n" . implode("\n", $avgLines) . "\n"
     . ($trendLines ? "Notable trends (≥0.4 point change):\n" . implode("\n", $trendLines) . "\n" : "Trends: stable.\n");

$lastNotes = [];
foreach (array_slice($entries, -5) as $e) {
    if (!empty($e['notes'])) $lastNotes[] = '- ' . trim($e['notes']);
}
if ($lastNotes) {
    $ctx .= "Recent free notes from the citizen:\n" . implode("\n", $lastNotes) . "\n";
}

/* ---------- 5. Ask Groq for structured insights ---------- */
$system = <<<TXT
You are Nafass, a personalized health coach for Gabès residents (Tunisia).
You receive a citizen's 30-day Health Diary stats and any vulnerabilities they
have. Produce a short, friendly, plain-English analysis of their trends and
ONE actionable plan tailored to them.

Rules:
- Never invent a diagnosis. Stay coach-like, not clinical.
- If averages are low and stable → reassure.
- If symptoms are climbing or vulnerabilities apply → escalate the recommendation accordingly.
- If "breath_diff" or asthma flag is high → suggest checking the air quality and contacting the in-app telemedicine.
- Output STRICT JSON, no markdown, with this schema:
  {
    "summary":   "<2-3 sentence overview, second person ('you')>",
    "trends":    ["<short bullet>", ...],
    "warnings":  ["<short bullet>", ...],
    "actions":   ["<short, concrete action>", ...],
    "risk_level":"low" | "moderate" | "high"
  }
- Each list ≤ 4 items. Each bullet ≤ 18 words. No emojis.
- Use the same language as the citizen's notes (default English).
TXT;

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $ctx . "\nAnalyse and answer with JSON only."],
];

$insights = null;
$source   = 'fallback';
$json = groq_chat_json($messages, GROQ_MODEL, [
    'temperature' => 0.35,
    'max_tokens'  => 600,
    'timeout'     => 18,
]);
if ($json && isset($json['summary'])) {
    /* Light sanitisation */
    $clean = function ($v) {
        $v = trim((string)$v);
        return mb_substr($v, 0, 800);
    };
    $cleanList = function ($arr) use ($clean) {
        $out = [];
        if (is_array($arr)) foreach ($arr as $i) {
            $s = $clean($i);
            if ($s !== '') $out[] = $s;
            if (count($out) >= 4) break;
        }
        return $out;
    };
    $level = strtolower((string)($json['risk_level'] ?? 'low'));
    if (!in_array($level, ['low','moderate','high'], true)) $level = 'low';
    /* Promote risk_level to the fuzzy result when the LLM under-estimates */
    $fuzzyLevel = $fz['urgency_level'];
    $rank = ['low'=>0,'moderate'=>1,'high'=>2,'critical'=>3];
    $llmRank   = $rank[$level] ?? 0;
    $fuzzyRank = $rank[$fuzzyLevel] ?? 0;
    if ($fuzzyRank > $llmRank) {
        $level = $fuzzyLevel === 'critical' ? 'high' : $fuzzyLevel;
    }

    $insights = [
        'summary'    => $clean($json['summary']),
        'trends'     => $cleanList($json['trends']  ?? []),
        'warnings'   => $cleanList($json['warnings']?? []),
        'actions'    => $cleanList($json['actions'] ?? []),
        'risk_level' => $level,
    ];
    $source = 'groq';
}

/* ---------- 6. Local fallback if Groq is unavailable ---------- */
if (!$insights) {
    $trends = [];
    foreach ($trend as $k => $t) {
        $direction = $t['delta'] > 0 ? 'increasing' : 'decreasing';
        $trends[] = ucfirst(str_replace('_', ' ', $k)) . " is {$direction} ({$t['previous']}/5 → {$t['recent']}/5 over the last week).";
    }
    if (!$trends) $trends[] = 'Your symptoms are roughly stable over the last 30 days.';

    $warnings = [];
    if ($avgRound['breath_diff'] >= 2.5) $warnings[] = 'Breathing difficulty has been moderate to high — consider a medical follow-up.';
    if ($avgRound['cough']       >= 2.5) $warnings[] = 'Persistent cough — keep an eye on it, especially during pollution peaks.';
    if (in_array('asthma', $flags, true) && $avgRound['breath_diff'] >= 1.8) {
        $warnings[] = 'You have asthma and breathing scores are elevated — keep your reliever inhaler at hand.';
    }
    if ($avgRound['mood'] <= 2.5) $warnings[] = 'Reported mood is consistently low — track stressors and rest more.';

    $actions = [
        'Check the daily air-quality status before going outside.',
        'Stay hydrated and avoid intense outdoor exercise on critical-status days.',
    ];
    if (in_array('asthma', $flags, true) || $avgRound['breath_diff'] >= 2) {
        $actions[] = 'Open the in-app telemedicine if breathing worsens for 2+ days in a row.';
    }
    if ($avgRound['fatigue'] >= 2.5) $actions[] = 'Aim for 7-8 hours of sleep and a 20-minute walk in cleaner-air zones.';
    if (count($actions) > 4) $actions = array_slice($actions, 0, 4);

    /* The fuzzy engine is the deterministic backbone: take its level
       and only escalate further if the diary clearly disagrees. */
    $level = $fz['urgency_level'] === 'critical' ? 'high' : $fz['urgency_level'];
    if (!in_array($level, ['low','moderate','high'], true)) $level = 'low';
    if (count($warnings) >= 2 || $avgRound['breath_diff'] >= 3) $level = 'high';
    foreach ($fz['actions'] as $a) {
        if (count($actions) >= 4) break;
        if (!in_array($a, $actions, true)) $actions[] = $a;
    }

    $insights = [
        'summary'    => $N >= 5
            ? "Based on your {$N} diary entries, your overall mood averages {$avgRound['mood']}/5. The system flagged "
              . count($warnings) . ' warning' . (count($warnings) === 1 ? '' : 's') . ' and ' . count($trends) . ' trend.'
            : "You have {$N} entries — log a few more days for a richer analysis.",
        'trends'     => $trends,
        'warnings'   => $warnings,
        'actions'    => $actions,
        'risk_level' => $level,
    ];
}

/* ---------- 7. Response + cache ---------- */
$payload = [
    'ok' => true,
    'stats' => [
        'entries'      => $N,
        'days_logged'  => $N,
        'averages'     => $avgRound,
        'trend_7v30'   => $trend,
        'top_symptoms' => array_slice($worst, 0, 3),
        'flags'        => $flags,
    ],
    'insights'     => $insights,
    'fuzzy'        => [
        'risk_score'    => $fz['risk_score'],
        'urgency_level' => $fz['urgency_level'],
        'fired_rules'   => $fz['fired_rules'],
        'explanation'   => $fz['explanation'],
        'inputs'        => $fz['inputs'],
    ],
    'type2'        => $ai['type2'],
    'model'        => $ai['model'],
    'source'       => $source,
    'generated_at' => date('Y-m-d H:i:s'),
];
$body = json_encode($payload, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $body);

header('Content-Type: application/json; charset=utf-8');
echo $body;
