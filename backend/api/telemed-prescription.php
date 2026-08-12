<?php
/**
 * Generates a printable HTML "e-prescription / consultation summary" page
 * for a finalized telemedicine consultation.
 *
 * The browser's native print-to-PDF (Ctrl+P) is used, matching the existing
 * pdf.php/pdf-archive.php pattern in this project.
 *
 * Access:
 *   - The owning citizen can open their own consultation
 *   - Health staff can open any consultation they joined
 *   - Admin can open any consultation
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';

$me = auth_user();
if (!$me) {
    http_response_code(401);
    echo 'Authentication required.';
    exit;
}

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Missing consultation id.';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*,
            u.full_name  AS citizen_name,
            u.username   AS citizen_username,
            h.full_name  AS doctor_name,
            h.username   AS doctor_username
       FROM telemed_requests t
       LEFT JOIN users u ON u.id = t.citizen_id
       LEFT JOIN users h ON h.id = t.joined_health_id
      WHERE t.id = ?"
);
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

// Access control
$role = $me['role'];
$canAccess =
    ($role === 'admin') ||
    ($role === 'health' && (int)$req['joined_health_id'] === (int)$me['id']) ||
    ($role === 'citizen' && (int)$req['citizen_id'] === (int)$me['id']);
if (!$canAccess) {
    http_response_code(403);
    echo 'Forbidden.';
    exit;
}

$pre  = $req['pre_consult']  ? json_decode($req['pre_consult'],  true) : null;
$post = $req['post_consult'] ? json_decode($req['post_consult'], true) : null;
$citizen = $req['citizen_name'] ?: $req['citizen_username'];
$doctor  = $req['doctor_name']  ?: ($req['doctor_username'] ?: '');

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Consultation Summary #<?= $id ?> · Nafass</title>
<style>
  *           { box-sizing: border-box; }
  body        { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
                color: #0f172a; max-width: 760px; margin: 30px auto; padding: 0 24px; }
  header      { display:flex; align-items:center; justify-content:space-between;
                border-bottom: 3px solid #0d3b66; padding-bottom: 14px; margin-bottom: 22px; }
  header h1   { margin: 0; font-size: 22px; color: #0d3b66; }
  header .sub { color: #64748b; font-size: 12px; }
  h2          { font-size: 14px; color: #0d3b66; margin: 22px 0 8px;
                text-transform: uppercase; letter-spacing: .5px; }
  .grid       { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .row        { font-size: 13px; line-height: 1.55; }
  .row b      { color: #334155; min-width: 140px; display: inline-block; }
  .vitals     { display: flex; flex-wrap: wrap; gap: 8px; }
  .chip       { padding: 4px 10px; border-radius: 999px;
                background: #e0e7ff; color: #1e3a8a;
                font-weight: 600; font-size: 12px; }
  .box        { padding: 12px 14px; border-radius: 8px;
                background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px;
                white-space: pre-wrap; line-height: 1.55; }
  .box.diag   { background: #eff6ff; border-color: #93c5fd; }
  .box.rx     { background: #fff7ed; border-color: #fdba74; }
  footer      { margin-top: 40px; padding-top: 14px; border-top: 1px solid #e2e8f0;
                font-size: 11px; color: #64748b; display: flex; justify-content: space-between; }
  .signature  { margin-top: 22px; text-align: right; font-size: 12px; color: #475569; }
  .signature b { color: #0d3b66; font-size: 14px; }
  @media print {
    body       { margin: 0; padding: 0 12mm; }
    .no-print  { display: none !important; }
  }
  .actions    { margin: 16px 0; display: flex; gap: 8px; justify-content: flex-end; }
  .btn        { padding: 8px 14px; background: #0d3b66; color: #fff;
                border: 0; border-radius: 6px; cursor: pointer; font-size: 13px; }
</style>
</head>
<body>
  <div class="actions no-print">
    <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  </div>

  <header>
    <div>
      <h1>Consultation Summary</h1>
      <div class="sub">Nafass · Gabès Tatenafas — In-app Telemedicine</div>
    </div>
    <div class="sub">
      <div>Consultation #<?= (int)$req['id'] ?></div>
      <div><?= $h($req['requested_at']) ?></div>
    </div>
  </header>

  <h2>Patient</h2>
  <div class="row"><b>Name:</b> <?= $h($citizen) ?></div>

  <?php if ($pre): ?>
    <h2>Pre-consultation</h2>
    <div class="vitals">
      <?php if ($pre['temperature'] !== null): ?><span class="chip">🌡 <?= $h($pre['temperature']) ?> °C</span><?php endif; ?>
      <?php if ($pre['pulse']       !== null): ?><span class="chip">❤ <?= $h($pre['pulse']) ?> bpm</span><?php endif; ?>
      <?php if ($pre['oxygen_sat']  !== null): ?><span class="chip">O₂ <?= $h($pre['oxygen_sat']) ?>%</span><?php endif; ?>
    </div>
    <?php if (!empty($pre['symptoms'])): ?>
      <div class="row" style="margin-top:8px"><b>Reported symptoms:</b> <?= $h($pre['symptoms']) ?></div>
    <?php endif; ?>
    <?php if (!empty($pre['notes'])): ?>
      <div class="row"><b>Patient notes:</b> <?= $h($pre['notes']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($post): ?>
    <h2>Diagnosis</h2>
    <div class="box diag"><?= $h($post['diagnosis'] ?? '—') ?></div>

    <?php if (!empty($post['recommendations'])): ?>
      <h2>Recommendations</h2>
      <div class="box"><?= $h($post['recommendations']) ?></div>
    <?php endif; ?>

    <?php if (!empty($post['prescription'])): ?>
      <h2>Prescription</h2>
      <div class="box rx"><?= $h($post['prescription']) ?></div>
    <?php endif; ?>

    <?php if (!empty($post['follow_up_days'])): ?>
      <div class="row" style="margin-top:14px"><b>Follow-up:</b> in <?= (int)$post['follow_up_days'] ?> day(s)</div>
    <?php endif; ?>

    <div class="signature">
      Issued by<br>
      <b><?= $h($post['doctor_name'] ?? $doctor) ?></b><br>
      <?= $h($post['finalized_at'] ?? '') ?>
    </div>
  <?php else: ?>
    <h2>Diagnosis</h2>
    <p>No post-consultation summary has been recorded yet.</p>
  <?php endif; ?>

  <footer>
    <span>Document #<?= (int)$req['id'] ?> · Generated <?= date('Y-m-d H:i') ?></span>
    <span>This summary is informational and does not replace an in-person clinical evaluation.</span>
  </footer>

</body>
</html>
