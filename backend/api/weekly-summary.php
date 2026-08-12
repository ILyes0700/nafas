<?php
/**
 * C6 — Résumé hebdomadaire IA pour les autorités sanitaires.
 *
 * GET                       → renvoie le dernier résumé (ou génère si absent)
 * GET ?regenerate=1 (admin) → force la régénération de la semaine courante
 * GET ?week_start=YYYY-MM-DD → renvoie une semaine archivée précise
 *
 * Le modèle est `llama-3.3-70b-versatile`. La génération coûte ~3-5s.
 * Cache : 1 ligne par semaine ISO dans `weekly_summaries`.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/groq_client.php';
require_once __DIR__ . '/../lib/ai_reco.php';   // unified Fuzzy Type-1 + Type-2 + AI engine

$me = auth_user();
if (!$me) json_response(['ok' => false, 'error' => 'auth_required'], 401);

$pdo = db();

/* Calcule lundi de la semaine en cours (week_start) — week_end = dimanche */
function _current_week(): array
{
    $today = strtotime('today');
    $dow   = (int)date('N', $today); // 1..7 (lundi=1)
    $monday = strtotime('-' . ($dow - 1) . ' days', $today);
    return [date('Y-m-d', $monday), date('Y-m-d', strtotime('+6 days', $monday))];
}

[$wStart, $wEnd] = _current_week();
if (!empty($_GET['week_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['week_start'])) {
    $wStart = $_GET['week_start'];
    $wEnd   = date('Y-m-d', strtotime('+6 days', strtotime($wStart)));
}

/* Vérifier le cache sauf regenerate=1 */
$forceRegen = !empty($_GET['regenerate']) && $me['role'] === 'admin';

if (!$forceRegen) {
    $stmt = $pdo->prepare(
        "SELECT * FROM weekly_summaries WHERE week_start = ? AND week_end = ? LIMIT 1"
    );
    $stmt->execute([$wStart, $wEnd]);
    $cached = $stmt->fetch();
    if ($cached) {
        json_response([
            'ok'           => true,
            'cached'       => true,
            'week_start'   => $wStart,
            'week_end'     => $wEnd,
            'summary_md'   => $cached['summary_md'],
            'metrics'      => json_decode((string)$cached['metrics_json'], true),
            'generated_at' => $cached['generated_at'],
            'model'        => $cached['model'],
        ]);
    }
}

/* Agrégat hebdo : compter symptômes/reports/alertes/zones critiques */
$alerts = $pdo->prepare("SELECT severity, COUNT(*) AS c FROM alerts
                         WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
                         GROUP BY severity");
$alerts->execute([$wStart, $wStart]);
$alertCounts = ['info'=>0,'warning'=>0,'danger'=>0,'critical'=>0];
foreach ($alerts->fetchAll() as $r) $alertCounts[$r['severity']] = (int)$r['c'];

$repCount = (int)$pdo->prepare(
    "SELECT COUNT(*) FROM reports WHERE reported_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)"
)->execute([$wStart, $wStart]) ?: 0;
$tmp = $pdo->prepare(
    "SELECT COUNT(*) FROM reports WHERE reported_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)"
);
$tmp->execute([$wStart, $wStart]); $repCount = (int)$tmp->fetchColumn();

$tmp = $pdo->prepare(
    "SELECT COUNT(*) FROM symptoms WHERE reported_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)"
);
$tmp->execute([$wStart, $wStart]); $sympCount = (int)$tmp->fetchColumn();

/* Zones les plus critiques */
$tmp = $pdo->prepare(
    "SELECT z.name, AVG(rs.score) AS avg_s, COUNT(*) AS n
     FROM risk_scores rs JOIN zones z ON z.id = rs.zone_id
     WHERE rs.computed_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
     GROUP BY z.id ORDER BY avg_s DESC LIMIT 5"
);
$tmp->execute([$wStart, $wStart]);
$topZones = $tmp->fetchAll();

/* Top symptômes */
$tmp = $pdo->prepare(
    "SELECT symptom, COUNT(*) AS n FROM symptoms
     WHERE reported_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
     GROUP BY symptom ORDER BY n DESC LIMIT 5"
);
$tmp->execute([$wStart, $wStart]);
$topSymptoms = $tmp->fetchAll();

$metrics = [
    'alerts'         => $alertCounts,
    'reports'        => $repCount,
    'symptoms'       => $sympCount,
    'top_zones'      => $topZones,
    'top_symptoms'   => $topSymptoms,
];

/* Construire le contexte pour Groq */
/* ---------- Fuzzy risk for the worst zone of the week --------------- */
$worstFz = null;
if (!empty($topZones)) {
    /* topZones rows are {name, avg_s, n}; we need the id for fuzzy lookup */
    $tz = $pdo->prepare(
        "SELECT z.id, z.name, COALESCE(AVG(rs.score), z.pollution_level) AS sc
           FROM zones z LEFT JOIN risk_scores rs ON rs.zone_id = z.id
          WHERE rs.computed_at BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) OR rs.computed_at IS NULL
          GROUP BY z.id ORDER BY sc DESC LIMIT 1"
    );
    try { $tz->execute([$wStart, $wStart]); $worstFz = $tz->fetch() ?: null; }
    catch (Throwable $e) { $worstFz = null; }
}
$ai = ai_reco_for_user($pdo, (int)$me['id'], [
    'pollution' => $worstFz ? (int)$worstFz['sc'] : 0,
    'zone_id'   => $worstFz ? (int)$worstFz['id'] : 0,
]);
$fz = [
    'risk_score'    => $ai['risk_score'],
    'urgency_level' => $ai['urgency_level'],
    'fired_rules'   => $ai['type1']['fired_rules'],
    'explanation'   => $ai['explanation'],
];
$fuzzyPrefix = ai_reco_prompt_block($ai);

$ctx = $fuzzyPrefix . "\n"
     . "Period: from $wStart to $wEnd (Gabès, Tunisia)\n\n"
     . "Alerts issued: {$alertCounts['critical']} critical, {$alertCounts['danger']} danger, "
     . "{$alertCounts['warning']} watch, {$alertCounts['info']} info.\n"
     . "Citizen reports: $repCount\n"
     . "Reported symptoms: $sympCount\n"
     . "Top zones (avg score / n. observations):\n";
foreach ($topZones as $z) {
    $ctx .= "  - {$z['name']}: " . round((float)$z['avg_s'], 1) . "/100 over {$z['n']} measurements\n";
}
$ctx .= "Top symptoms:\n";
foreach ($topSymptoms as $s) {
    $ctx .= "  - {$s['symptom']} ({$s['n']})\n";
}

$system = <<<TXT
You are the weekly analyst for Nafass — the health/air surveillance platform
of Gabès, Tunisia. You produce a report for the Regional Health Directorate.
Style: clear, factual, scientific. Markdown format mandatory, in English,
≤ 500 words. EXACT STRUCTURE:

# Weekly Summary — {{week}}

## In Brief
(2–3 sentences)

## Key Indicators
- bullet list (alerts, reports, symptoms)

## Zones to Monitor
- name — average score — observation

## Health Trends
(2–3 sentences on dominant symptoms and possible links to pollution)

## Operational Recommendations
(max 3, actionable)

No emoji. No tables. No endless paragraphs.
TXT;
$system = str_replace('{{week}}', "$wStart → $wEnd", $system);

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $ctx],
];

$r = groq_chat_call($messages, 'llama-3.3-70b-versatile', [
    'temperature' => 0.4,
    'max_tokens'  => 900,
    'timeout'     => 30,
]);

if (!$r['ok']) {
    json_response([
        'ok'         => false,
        'error'      => $r['error'],
        'metrics'    => $metrics,
    ], 502);
}

$summaryMd = trim((string)$r['content']);

/* Persistence */
try {
    $ins = $pdo->prepare(
        "INSERT INTO weekly_summaries (week_start, week_end, model, summary_md, metrics_json)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           summary_md = VALUES(summary_md),
           metrics_json = VALUES(metrics_json),
           model = VALUES(model),
           generated_at = CURRENT_TIMESTAMP"
    );
    $ins->execute([
        $wStart,
        $wEnd,
        'llama-3.3-70b-versatile',
        $summaryMd,
        json_encode($metrics, JSON_UNESCAPED_UNICODE),
    ]);
} catch (Throwable $e) {
    error_log('[weekly-summary] persist: ' . $e->getMessage());
}

json_response([
    'ok'          => true,
    'cached'      => false,
    'week_start'  => $wStart,
    'week_end'    => $wEnd,
    'summary_md'  => $summaryMd,
    'metrics'     => $metrics,
    'fuzzy'       => [
        'risk_score'    => $fz['risk_score'],
        'urgency_level' => $fz['urgency_level'],
        'fired_rules'   => array_slice($fz['fired_rules'], 0, 3),
        'explanation'   => $fz['explanation'],
    ],
    'type2'       => $ai['type2'],
    'ai_model'    => $ai['model'],
    'model'       => 'llama-3.3-70b-versatile',
]);
