<?php
/**
 * Diagnostic Groq Vision.
 *
 * Utilisation :
 *   1. Place une image dans uploads/reports/  (ou utilise une déjà présente)
 *   2. Ouvre dans le navigateur :
 *        http://localhost/gabes-tatenafas/test-groq-vision.php
 *      → liste les images disponibles dans uploads/reports/
 *
 *   3. Ajoute ?image=NOM_DU_FICHIER pour tester celui-ci :
 *        http://localhost/gabes-tatenafas/test-groq-vision.php?image=report-20260502-103000-abcd1234.jpg
 *
 * Affiche :
 *   - état des prérequis PHP (cURL, fileinfo, mbstring)
 *   - infos sur l'image (taille, MIME)
 *   - résultat de l'appel Groq (succès → analyse FR, échec → cause exacte)
 *
 * À SUPPRIMER en production. La clé Groq est lue depuis backend/config/groq.php.
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/backend/lib/groq_vision.php';

$root = __DIR__;
$dir  = $root . '/uploads/reports';

$ext = ['jpg','jpeg','png','webp','gif'];
$files = [];
if (is_dir($dir)) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($e, $ext, true)) $files[] = $f;
    }
    rsort($files);
}

$imageName = $_GET['image'] ?? '';
$abs = $imageName && in_array($imageName, $files, true) ? $dir . DIRECTORY_SEPARATOR . $imageName : null;

?><!doctype html>
<meta charset="utf-8">
<title>Test Groq Vision</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:920px;margin:24px auto;padding:0 16px;color:#1f2937}
  h1{font-size:20px}
  .ok{color:#059669}.bad{color:#dc2626}
  pre{background:#f3f4f6;padding:12px;border-radius:8px;white-space:pre-wrap;word-wrap:break-word}
  ul{padding-left:18px}
  a{color:#2563eb;text-decoration:none}
  a:hover{text-decoration:underline}
</style>

<h1>Test Groq Vision — diagnostic</h1>

<h2>Prérequis PHP</h2>
<ul>
  <li>cURL : <?= function_exists('curl_init') ? '<span class=ok>OK</span>' : '<span class=bad>MANQUANT — activer php_curl dans php.ini</span>' ?></li>
  <li>fileinfo : <?= function_exists('finfo_open') ? '<span class=ok>OK</span>' : '<span class=bad>MANQUANT — activer php_fileinfo</span>' ?></li>
  <li>mbstring : <?= function_exists('mb_strlen') ? '<span class=ok>OK</span>' : '<span class=bad>MANQUANT — activer php_mbstring</span>' ?></li>
  <li>Clé Groq : <?= (defined('GROQ_API_KEY') && GROQ_API_KEY && stripos(GROQ_API_KEY,'gsk_')===0) ? '<span class=ok>OK (préfixe gsk_)</span>' : '<span class=bad>ABSENTE</span>' ?></li>
</ul>

<h2>Images disponibles dans <code>uploads/reports/</code></h2>
<?php if (!$files): ?>
  <p>Aucune image — envoie un signalement avec photo, puis recharge cette page.</p>
<?php else: ?>
  <ul>
    <?php foreach ($files as $f): ?>
      <li><a href="?image=<?= urlencode($f) ?>"><?= htmlspecialchars($f) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($abs): ?>
  <h2>Test sur <code><?= htmlspecialchars($imageName) ?></code></h2>
  <?php
    $size = filesize($abs);
    $mime = function_exists('finfo_open') ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $abs) ?: 'inconnu') : 'inconnu';
  ?>
  <ul>
    <li>Taille : <?= number_format($size/1024,1,',',' ') ?> Ko</li>
    <li>MIME : <code><?= htmlspecialchars($mime) ?></code></li>
  </ul>

  <p>Lancement de l'analyse (peut prendre 3-8 s)…</p>
  <?php
    $t0 = microtime(true);
    $out = analyze_report_image($abs, 'Test diagnostic depuis test-groq-vision.php', 'smoke', 'Ghannouch');
    $dt = round((microtime(true) - $t0) * 1000);
  ?>
  <p>Durée : <?= $dt ?> ms</p>

  <?php if ($out !== null): ?>
    <h3 class="ok">SUCCÈS</h3>
    <pre><?= htmlspecialchars($out) ?></pre>
  <?php else: ?>
    <h3 class="bad">ÉCHEC</h3>
    <p>Cause :</p>
    <pre><?= htmlspecialchars(groq_vision_last_error() ?? '(inconnue — vérifie le php_error.log de WAMP)') ?></pre>
    <p>Pour plus de détails, ouvre l'icône WAMP → PHP → <code>php_error.log</code> et cherche les lignes <code>[groq_vision]</code>.</p>
  <?php endif; ?>
<?php endif; ?>
