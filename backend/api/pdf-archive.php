<?php
/**
 * Nafass — Archive de rapports PDF/HTML
 *
 * Modes :
 *   ?save=1&period=daily|weekly|monthly   → Génère et enregistre le rapport sur le serveur
 *                                            dans /reports/saved/<period>-<YYYYMMDD-HHMM>.html
 *                                            Retourne JSON {ok, filename, url}.
 *
 *   ?list=1                                → Liste JSON des rapports archivés
 *
 *   ?file=<basename>                       → Sert le fichier (ouvre dans le navigateur)
 *   ?file=<basename>&print=1               → idem + auto-print au chargement
 *   ?file=<basename>&download=1            → force téléchargement (Content-Disposition: attachment)
 *   ?file=<basename>&delete=1              → supprime le fichier (admin only — POST conseillé)
 */

require_once __DIR__ . '/../lib/helpers.php';

// ---- Config dossier ----
$archiveDir = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'saved';
if (!is_dir($archiveDir)) {
    @mkdir($archiveDir, 0775, true);
}

// ---- Helper : nom fichier sécurisé ----
function _safe_basename(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._\-]/', '', $name);
}

// =================================================================
// MODE 1 — LIST
// =================================================================
if (isset($_GET['list'])) {
    header('Content-Type: application/json; charset=utf-8');
    $items = [];
    if (is_dir($archiveDir)) {
        $files = scandir($archiveDir, SCANDIR_SORT_DESCENDING);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $archiveDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($full)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['html', 'pdf'])) continue;
            // Period: extrait avant le premier "-"
            $period = explode('-', $f)[0] ?? '';
            $titles = [
                'daily'   => 'Daily Report',
                'weekly'  => 'Weekly Report',
                'monthly' => 'Monthly Summary',
            ];
            $items[] = [
                'filename'   => $f,
                'period'     => in_array($period, ['daily','weekly','monthly']) ? $period : 'custom',
                'title'      => $titles[$period] ?? 'Report',
                'size'       => filesize($full),
                'created_at' => date('Y-m-d H:i:s', filemtime($full)),
                'ext'        => $ext,
            ];
        }
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

// =================================================================
// MODE 2 — DELETE
// =================================================================
if (!empty($_GET['delete']) && !empty($_GET['file'])) {
    header('Content-Type: application/json; charset=utf-8');
    $file = _safe_basename($_GET['file']);
    $full = $archiveDir . DIRECTORY_SEPARATOR . $file;
    if (is_file($full)) {
        @unlink($full);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'not-found']);
    }
    exit;
}

// =================================================================
// MODE 3 — SERVE FILE
// =================================================================
if (!empty($_GET['file']) && empty($_GET['save'])) {
    $file = _safe_basename($_GET['file']);
    $full = $archiveDir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($full)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $ext === 'pdf' ? 'application/pdf' : 'text/html; charset=utf-8';
    header('Content-Type: ' . $mime);
    if (!empty($_GET['download'])) {
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($full);
        exit;
    }

    // Auto-print : si HTML, on injecte un <script>window.print()</script>
    if ($ext === 'html' && !empty($_GET['print'])) {
        $content = file_get_contents($full);
        // Injecte avant </body> sinon en fin de fichier
        $inject = '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},300);});</script>';
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', $inject . '</body>', $content, 1);
        } else {
            $content .= $inject;
        }
        echo $content;
        exit;
    }

    readfile($full);
    exit;
}

// =================================================================
// MODE 4 — SAVE (génération + enregistrement)
// =================================================================
if (empty($_GET['save'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'missing-params']);
    exit;
}

$period = $_GET['period'] ?? 'daily';
if (!in_array($period, ['daily', 'weekly', 'monthly'])) $period = 'daily';

// Supporte 2 modes :
//   A) Body = PDF binaire (Electron printToPDF) → on l'écrit tel quel en .pdf
//   B) Pas de body → on appelle pdf.php pour récupérer le HTML et on l'écrit en .html

$ts = date('Ymd-Hi');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Mode A — POST avec PDF binaire
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $body = file_get_contents('php://input');

    // Si c'est un PDF (signature %PDF-)
    if ($body && strncmp($body, '%PDF-', 5) === 0) {
        $filename = $period . '-' . $ts . '.pdf';
        $full = $archiveDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($full, $body) === false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'write-failed']);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => true,
            'filename' => $filename,
            'size'     => strlen($body),
            'url'      => 'pdf-archive.php?file=' . rawurlencode($filename),
        ]);
        exit;
    }
}

// Mode B — Génération HTML serveur via boucle interne sur pdf.php
$pdfUrl = 'pdf.php?period=' . urlencode($period);
// On capture le rendu via output buffering en incluant pdf.php
ob_start();
$_GET_BACKUP = $_GET;
$_GET = ['period' => $period];
try {
    include __DIR__ . '/pdf.php';
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'render-failed: ' . $e->getMessage()]);
    exit;
}
$_GET = $_GET_BACKUP;
$html = ob_get_clean();

if (empty($html)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'empty-render']);
    exit;
}

$filename = $period . '-' . $ts . '.html';
$full = $archiveDir . DIRECTORY_SEPARATOR . $filename;
if (file_put_contents($full, $html) === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'write-failed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'       => true,
    'filename' => $filename,
    'size'     => filesize($full),
    'url'      => 'pdf-archive.php?file=' . rawurlencode($filename),
]);
