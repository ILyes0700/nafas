<?php
// Génère un rapport HTML imprimable (compatible Ctrl+P -> Enregistrer en PDF dans Electron)
//
// Modes :
//   ?period=daily|weekly|monthly      → page HTML (par défaut)
//   ?period=...&print=1               → idem + window.print() automatique au chargement
//   ?period=...&download=1            → Content-Disposition: attachment (téléchargement)
//   ?history=1                        → JSON de l'historique des rapports générés

require_once __DIR__ . '/../lib/helpers.php';

$pdo = db();

// ---------- HISTORY (JSON) ----------
if (isset($_GET['history'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $rows = $pdo->query(
            "SELECT id, title, period, filename, generated_at AS created_at
             FROM reports_pdf
             ORDER BY generated_at DESC
             LIMIT 12"
        )->fetchAll();
        echo json_encode(['ok' => true, 'history' => $rows]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'history' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// ---------- HTML (rapport) ----------
$period = $_GET['period'] ?? 'daily';
$titles = [
    'daily'   => 'Daily Report',
    'weekly'  => 'Weekly Report',
    'monthly' => 'Monthly Summary',
];
$titlesAr = [
    'daily'   => 'تقرير يومي',
    'weekly'  => 'تقرير أسبوعي',
    'monthly' => 'تقرير شهري',
];
$title   = $titles[$period]   ?? 'Report';
$titleAr = $titlesAr[$period] ?? '';

// Fenêtre temporelle selon la période
$intervalSql = ['daily' => '1 DAY', 'weekly' => '7 DAY', 'monthly' => '30 DAY'][$period] ?? '1 DAY';

$zones         = [];
$alerts        = [];
$reportsCount  = 0;
$symptomsCount = 0;
$pollutionAvg  = 0;
$global        = 'safe';
$loadError     = null;

try {
    // risk_score n'est pas une colonne de zones — c'est une valeur calculée stockée dans risk_scores
    $zones = $pdo->query("
        SELECT z.name, z.name_ar, z.status, z.pollution_level, z.population,
               (SELECT score FROM risk_scores rs WHERE rs.zone_id = z.id ORDER BY id DESC LIMIT 1) AS risk_score
        FROM zones z
        ORDER BY z.pollution_level DESC
    ")->fetchAll();

    $st = $pdo->prepare("SELECT a.title, a.severity, a.created_at, z.name AS zname
                         FROM alerts a LEFT JOIN zones z ON z.id=a.zone_id
                         WHERE a.created_at >= NOW() - INTERVAL $intervalSql
                         ORDER BY a.created_at DESC LIMIT 30");
    $st->execute();
    $alerts = $st->fetchAll();

    // reports et symptoms utilisent `reported_at` (pas `created_at`)
    $st = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE reported_at >= NOW() - INTERVAL $intervalSql");
    $st->execute();
    $reportsCount = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM symptoms WHERE reported_at >= NOW() - INTERVAL $intervalSql");
    $st->execute();
    $symptomsCount = (int)$st->fetchColumn();

    $pollutionAvg = (int)round(array_sum(array_column($zones, 'pollution_level')) / max(1, count($zones)));

    if (function_exists('global_status')) {
        $global = global_status();
    } else {
        $global = $pollutionAvg >= 75 ? 'critical' : ($pollutionAvg >= 50 ? 'warning' : 'safe');
    }

    // Trace de génération (best-effort, on ignore les erreurs)
    try {
        $pdo->prepare('INSERT INTO reports_pdf (title, period, filename) VALUES (?,?,?)')
            ->execute([$title, $period, $period . '-' . date('Ymd-Hi') . '.html']);
    } catch (Throwable $e) { /* ignore */ }

} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

// Nettoie les préfixes [AUTO:...] des titres d'alertes pour l'affichage
function _clean_title(string $t): string {
    return preg_replace('/^\s*\[AUTO:[^\]]+\]\s*/', '', $t);
}

// ---------- En-têtes ----------
$filename = $period . '-' . date('Ymd-Hi') . '.html';
header('Content-Type: text/html; charset=utf-8');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}
$autoPrint = !empty($_GET['print']);

// ---------- Aggregats par sévérité ----------
$sevCount = ['danger' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
foreach ($alerts as $a) {
    $s = $a['severity'] ?? 'info';
    if (isset($sevCount[$s])) $sevCount[$s]++;
}
$alertsCritical = ($sevCount['danger'] ?? 0) + ($sevCount['critical'] ?? 0);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?> — Nafass · نَفَس</title>
<style>
  *{box-sizing:border-box;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
  body{margin:0;padding:40px;background:#fafaf7;color:#1f2a37}
  .brand{display:flex;align-items:center;gap:14px;margin-bottom:22px}
  .brand .logo{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0d3b66,#2f6fb3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800}
  .brand .name{font-size:20px;font-weight:800;color:#0d3b66;letter-spacing:.2px}
  .brand .sub{font-size:12px;color:#6b7280;margin-top:2px}
  h1{font-size:26px;margin:0 0 4px;color:#0d3b66}
  h1 small{font-size:14px;color:#6b7280;font-weight:500;margin-left:8px}
  h2{font-size:17px;margin:30px 0 12px;color:#0d3b66;border-bottom:2px solid #e5e7eb;padding-bottom:6px;display:flex;align-items:center;gap:8px}
  .meta{color:#6b7280;font-size:13px;margin-bottom:18px}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
  th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #f3f4f6;font-size:13.5px}
  th{background:#f8fafc;font-weight:600;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  tr:last-child td{border-bottom:0}
  .pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
  .safe{background:#dcfce7;color:#166534}
  .warning{background:#fef3c7;color:#92400e}
  .critical{background:#fee2e2;color:#991b1b}
  .danger{background:#fee2e2;color:#991b1b}
  .info{background:#dbeafe;color:#1e40af}
  .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
  .card .label{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
  .card .value{font-size:24px;font-weight:800;margin-top:6px;color:#0d3b66}
  .card .value.warn{color:#92400e}
  .card .value.danger{color:#991b1b}
  .card .value.green{color:#166534}
  .bar{display:inline-block;width:120px;height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden;vertical-align:middle;margin-right:6px}
  .bar > span{display:block;height:100%}
  .footer{margin-top:36px;text-align:center;color:#9ca3af;font-size:12px;padding-top:18px;border-top:1px solid #e5e7eb}
  .toolbar{position:sticky;top:0;background:#fafaf7;padding-bottom:14px;margin-bottom:8px;display:flex;gap:8px;justify-content:flex-end;z-index:10}
  .btn{padding:9px 16px;border:none;border-radius:10px;background:#0d3b66;color:#fff;font-weight:600;cursor:pointer;font-size:13px}
  .btn.outline{background:transparent;color:#0d3b66;border:1px solid #0d3b66}
  .btn:hover{opacity:.92}
  .empty{padding:14px;text-align:center;color:#94a3b8;font-style:italic;font-size:13px}
  .err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin:14px 0;font-size:13px}
  @media print{
    .noprint{display:none!important}
    body{padding:18px;background:#fff}
    .toolbar{display:none}
    .card,table{box-shadow:none;border:1px solid #d1d5db}
  }
</style>
</head>
<body>

  <div class="toolbar noprint">
    <button class="btn outline" onclick="history.length>1?history.back():window.close()">← Back</button>
    <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
  </div>

  <div class="brand">
    <div class="logo">ن</div>
    <div>
      <div class="name">Nafass — نَفَس</div>
      <div class="sub">Health &amp; air-quality monitoring in Gabès</div>
    </div>
  </div>

  <h1>
    <?= htmlspecialchars($title) ?>
    <?php if ($titleAr): ?><small dir="rtl"><?= htmlspecialchars($titleAr) ?></small><?php endif; ?>
  </h1>
  <div class="meta">Generated on <?= date('Y-m-d H:i') ?> · Period: <?= htmlspecialchars($period) ?></div>

  <?php if ($loadError): ?>
    <div class="err">⚠️ Could not load all data: <?= htmlspecialchars($loadError) ?></div>
  <?php endif; ?>

  <div class="summary">
    <div class="card">
      <div class="label">Global Status</div>
      <div class="value"><span class="pill <?= $global ?>"><?= $global ?></span></div>
    </div>
    <div class="card">
      <div class="label">Monitored Zones</div>
      <div class="value"><?= count($zones) ?></div>
    </div>
    <div class="card">
      <div class="label">Average Pollution</div>
      <div class="value <?= $pollutionAvg >= 75 ? 'danger' : ($pollutionAvg >= 50 ? 'warn' : 'green') ?>"><?= $pollutionAvg ?>%</div>
    </div>
    <div class="card">
      <div class="label">Critical Alerts</div>
      <div class="value <?= $alertsCritical > 0 ? 'danger' : 'green' ?>"><?= $alertsCritical ?></div>
    </div>
    <div class="card">
      <div class="label">Reports</div>
      <div class="value"><?= $reportsCount ?></div>
    </div>
    <div class="card">
      <div class="label">Symptoms</div>
      <div class="value"><?= $symptomsCount ?></div>
    </div>
    <div class="card">
      <div class="label">Total Alerts</div>
      <div class="value"><?= count($alerts) ?></div>
    </div>
    <div class="card">
      <div class="label">Warnings</div>
      <div class="value warn"><?= $sevCount['warning'] ?? 0 ?></div>
    </div>
  </div>

  <h2>Zone Status</h2>
  <?php if (empty($zones)): ?>
    <div class="empty">No zone available.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Zone</th><th>Status</th><th>Pollution</th><th>Risk Score</th><th>Population</th></tr></thead>
    <tbody>
      <?php foreach ($zones as $z): ?>
      <?php
        $cls = $z['status'] ?? 'safe';
        $pol = (int)$z['pollution_level'];
        $color = $cls === 'critical' ? '#991b1b' : ($cls === 'danger' ? '#dc2626' : ($cls === 'warning' ? '#d97706' : '#16a34a'));
      ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($z['name']) ?></strong>
          <?php if (!empty($z['name_ar'])): ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= htmlspecialchars($z['name_ar']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="pill <?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></span></td>
        <td>
          <span class="bar"><span style="width:<?= $pol ?>%;background:<?= $color ?>"></span></span>
          <strong><?= $pol ?>%</strong>
        </td>
        <td><?= isset($z['risk_score']) && $z['risk_score'] !== null ? (int)$z['risk_score'] . '/100' : '—' ?></td>
        <td><?= number_format((int)($z['population'] ?? 0), 0, ',', ' ') ?></td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h2>Alerts (period: <?= htmlspecialchars($period) ?>)</h2>
  <?php if (empty($alerts)): ?>
    <div class="empty">No alert in this period.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Zone</th><th>Title</th><th>Severity</th></tr></thead>
    <tbody>
      <?php foreach ($alerts as $a): ?>
      <tr>
        <td><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
        <td><?= htmlspecialchars($a['zname'] ?? '—') ?></td>
        <td><?= htmlspecialchars(_clean_title((string)$a['title'])) ?></td>
        <td><span class="pill <?= htmlspecialchars($a['severity'] ?? 'info') ?>"><?= htmlspecialchars($a['severity'] ?? 'info') ?></span></td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="footer">
    © Nafass — نَفَس · Health &amp; air-quality monitoring in Gabès<br>
    Automatically generated report · Internal-use document
  </div>

  <?php if ($autoPrint): ?>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>
  <?php endif; ?>

</body>
</html>
