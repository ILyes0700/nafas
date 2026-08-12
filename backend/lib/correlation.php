<?php
declare(strict_types=1);

/**
 * A2 — Moteur de corrélation pollution ↔ symptômes (Pearson).
 *
 * Pour une zone donnée et une fenêtre temporelle, on construit deux séries
 * journalières alignées :
 *   X[d] = pollution_level moyen ce jour (depuis risk_scores ou zones)
 *   Y[d] = nombre de symptômes signalés ce jour pour la zone
 *
 * Puis on calcule le coefficient de corrélation de Pearson :
 *   r = cov(X,Y) / (σx · σy)
 *
 * Renvoie un tableau analytique consommable par le frontend.
 */

require_once __DIR__ . '/../config/database.php';

function correlation_pearson(array $x, array $y): float
{
    $n = min(count($x), count($y));
    if ($n < 3) return 0.0;
    $mx = array_sum($x) / $n;
    $my = array_sum($y) / $n;
    $num = 0.0;
    $dx2 = 0.0;
    $dy2 = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $dx = $x[$i] - $mx;
        $dy = $y[$i] - $my;
        $num += $dx * $dy;
        $dx2 += $dx * $dx;
        $dy2 += $dy * $dy;
    }
    if ($dx2 == 0.0 || $dy2 == 0.0) return 0.0;
    return $num / sqrt($dx2 * $dy2);
}

function correlation_label(float $r): string
{
    $abs = abs($r);
    if ($abs >= 0.7) return 'strong';
    if ($abs >= 0.4) return 'moderate';
    if ($abs >= 0.2) return 'weak';
    return 'negligible';
}

/**
 * Analyse une zone sur N jours (par défaut 30) et renvoie un dictionnaire :
 *   { zone_id, days, n, x_pollution, y_symptoms, r, abs_r, label, trend, examples }
 */
function correlation_analyze_zone(int $zoneId, int $days = 30): array
{
    $pdo = db();
    $days = max(7, min(180, $days));

    /* Jours alignés sur risk_scores (X) — si disponibles, sinon zones.pollution_level */
    $stmtX = $pdo->prepare(
        "SELECT DATE(rs.computed_at) AS d, AVG(rs.score) AS x
         FROM risk_scores rs
         WHERE rs.zone_id = ? AND rs.computed_at >= NOW() - INTERVAL ? DAY
         GROUP BY DATE(rs.computed_at) ORDER BY d ASC"
    );
    $stmtX->execute([$zoneId, $days]);
    $rowsX = $stmtX->fetchAll();
    $byDayX = [];
    foreach ($rowsX as $r) $byDayX[$r['d']] = (float)$r['x'];

    /* Y[d] = nb symptômes ce jour-là pour la zone */
    $stmtY = $pdo->prepare(
        "SELECT DATE(reported_at) AS d, COUNT(*) AS y
         FROM symptoms WHERE zone_id = ? AND reported_at >= NOW() - INTERVAL ? DAY
         GROUP BY DATE(reported_at) ORDER BY d ASC"
    );
    $stmtY->execute([$zoneId, $days]);
    $rowsY = $stmtY->fetchAll();
    $byDayY = [];
    foreach ($rowsY as $r) $byDayY[$r['d']] = (float)$r['y'];

    /* Si X est vide (pas d'historique), on prend la valeur courante de la zone */
    if (empty($byDayX)) {
        $now = $pdo->prepare('SELECT pollution_level FROM zones WHERE id = ?');
        $now->execute([$zoneId]);
        $level = (int)$now->fetchColumn();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $byDayX[$d] = $level;
        }
    }

    /* Aligner X et Y sur l'union des jours */
    $allDays = array_unique(array_merge(array_keys($byDayX), array_keys($byDayY)));
    sort($allDays);
    $X = []; $Y = [];
    foreach ($allDays as $d) {
        $X[] = $byDayX[$d] ?? 0.0;
        $Y[] = $byDayY[$d] ?? 0.0;
    }

    $r = correlation_pearson($X, $Y);
    $label = correlation_label($r);

    /* Qualitative trend for the frontend */
    $trend = 'No visible effect.';
    if ($r >= 0.4) {
        $pct = (int)round($r * 100);
        $trend = "As pollution rises, reported symptoms also rise (positive {$label} correlation, {$pct}%).";
    } elseif ($r <= -0.4) {
        $pct = (int)round(abs($r) * 100);
        $trend = "Surprisingly, symptoms decrease as pollution rises ({$label}, {$pct}%). To be investigated.";
    } elseif (abs($r) >= 0.2) {
        $trend = "Weak but detectable link between pollution and symptoms ($label).";
    }

    /* Exemples concrets : 2 dates où la pollution était la plus haute */
    arsort($byDayX);
    $examples = [];
    foreach (array_slice($byDayX, 0, 2, true) as $d => $px) {
        $examples[] = [
            'date'       => $d,
            'pollution'  => round($px, 1),
            'symptoms'   => (int)($byDayY[$d] ?? 0),
        ];
    }

    return [
        'zone_id'      => $zoneId,
        'days'         => $days,
        'n_points'     => count($X),
        'x_pollution'  => array_map(fn($v) => round($v, 2), $X),
        'y_symptoms'   => $Y,
        'days_axis'    => $allDays,
        'r'            => round($r, 3),
        'abs_r'        => round(abs($r), 3),
        'label'        => $label,
        'trend'        => $trend,
        'examples'     => $examples,
    ];
}
