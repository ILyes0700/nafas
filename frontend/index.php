<?php
require_once __DIR__ . '/../backend/lib/auth.php';
$me = auth_require();                           // redirige vers login.php si pas connecté
$allowed = role_allowed_routes($me['role']);    // routes hash autorisées pour ce rôle

/* ----- Jeu d'icônes SVG inline (style Lucide, stroke 1.75) -----
   Toutes les icônes utilisent currentColor pour hériter de la couleur du nav.
   Format : <svg ... > <path/circle/... /> </svg>  (compact, validé). */
function nav_icon(string $key): string {
    $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $end = '</svg>';
    switch ($key) {
        case 'community':
            return $svg . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>' . $end;
        case 'dashboard':
            return $svg . '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>' . $end;
        case 'map':
            return $svg . '<path d="M9 20l-6 2V6l6-2"/><path d="M15 22l-6-2"/><path d="M21 18l-6 2V6l6-2z"/><path d="M9 4l6 2"/><circle cx="9" cy="10.5" r="2"/><path d="M9 12.5v3"/>' . $end;
        case 'api-data':
            return $svg . '<path d="M4 14a8 8 0 0 1 16 0"/><path d="M12 14v7"/><circle cx="12" cy="3.5" r="1.5"/><path d="M7 18h10"/>' . $end;
        case 'alerts':
            return $svg . '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/>' . $end;
        case 'reports':
            return $svg . '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 18v-3"/><path d="M12 18v-6"/><path d="M16 18v-2"/>' . $end;
        case 'citizen-reports':
            return $svg . '<path d="M3 11l18-7-3 18-6-7-9-4z"/><path d="M12 15l9-11"/>' . $end;
        case 'symptoms':
            return $svg . '<path d="M22 12h-4l-3 9-6-18-3 9H2"/>' . $end;
        case 'chatbot':
            return $svg . '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><circle cx="9" cy="11" r="0.6" fill="currentColor"/><circle cx="13" cy="11" r="0.6" fill="currentColor"/><circle cx="17" cy="11" r="0.6" fill="currentColor"/>' . $end;
        case 'school':
            return $svg . '<path d="M22 10L12 4 2 10l10 6 10-6z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/><path d="M22 10v6"/>' . $end;
        case 'zones':
            return $svg . '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/>' . $end;
        case 'diary':
            return $svg . '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/><path d="M9 7h7"/><path d="M9 11h5"/>' . $end;
        case 'correlation':
            return $svg . '<path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 5-5"/><circle cx="7" cy="14" r="1.4"/><circle cx="10" cy="11" r="1.4"/><circle cx="14" cy="15" r="1.4"/><circle cx="19" cy="10" r="1.4"/>' . $end;
        case 'deep-learning':
            return $svg . '<circle cx="5" cy="6" r="1.8"/><circle cx="5" cy="18" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="6" r="1.8"/><circle cx="19" cy="18" r="1.8"/><path d="M7 6.6l3 4.4M7 17.4l3-4.4M14 11l3-4M14 13l3 4"/>' . $end;
        case 'bilstm-ae':
            /* v4.0 : remplace l'ancienne icone 'cgan'. Encodeur -> latent -> decodeur. */
            return $svg . '<circle cx="4" cy="7" r="1.6"/><circle cx="4" cy="17" r="1.6"/><circle cx="12" cy="12" r="2"/><circle cx="20" cy="7" r="1.6"/><circle cx="20" cy="17" r="1.6"/><path d="M5.5 8l5 3M5.5 16l5-3M13.5 11l5-3M13.5 13l5 3"/>' . $end;
        case 'anomaly':
            return $svg . '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/>' . $end;
        case 'comparison':
            return $svg . '<path d="M3 3v18h18"/><rect x="6" y="10" width="3" height="8" rx="1"/><rect x="11" y="6" width="3" height="12" rx="1"/><rect x="16" y="13" width="3" height="5" rx="1"/>' . $end;
        case 'fuzzy-type2':
            return $svg . '<path d="M3 17c3 0 3-10 6-10s3 10 6 10 3-6 6-6"/><path d="M3 21h18"/>' . $end;
        case 'forecast-ml':
            return $svg . '<path d="M12 3v18"/><path d="M5 8l7-5 7 5"/><path d="M5 8v8l7 5 7-5V8"/>' . $end;
        case 'granger':
            return $svg . '<circle cx="5" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><path d="M7 12h10"/><path d="M14 9l3 3-3 3"/>' . $end;
        case 'health-impact':
            return $svg . '<path d="M3 12h4l2 5 4-12 2 7h6"/>' . $end;
        case 'drift':
            return $svg . '<path d="M3 12a9 9 0 0115-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 01-15 6.7L3 16"/><path d="M3 21v-5h5"/>' . $end;
        case 'spatial':
            return $svg . '<path d="M12 2v6"/><path d="M12 22v-6"/><path d="M2 12h6"/><path d="M22 12h-6"/><circle cx="12" cy="12" r="2"/>' . $end;
        case 'ensemble':
            return $svg . '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="8" y="14" width="8" height="7" rx="1"/>' . $end;
        case 'smart-alerts':
            return $svg . '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/><path d="M12 2v2"/>' . $end;
        case 'federated':
            return $svg . '<circle cx="12" cy="12" r="3"/><circle cx="5" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>' . $end;
        case 'comparative-literature':
            return $svg . '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/><path d="M9 7h7"/>' . $end;
        case 'upgrade-dashboard':
            return $svg . '<path d="M12 2l2.5 5 5.5.8-4 3.9.9 5.5L12 15l-4.9 2.6.9-5.5-4-3.9 5.5-.8z"/>' . $end;
        case 'weekly':
            return $svg . '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h2"/><path d="M14 14h2"/><path d="M8 18h2"/><path d="M14 18h2"/>' . $end;
        case 'settings':
            return $svg . '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 008 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/>' . $end;
        case 'help':
            return $svg . '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/>' . $end;
        case 'profile':
            return $svg . '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>' . $end;
        case 'learn':
            return $svg . '<path d="M2 6.5L12 3l10 3.5-10 3.5-10-3.5z"/><path d="M6 8.5v6c2 1.5 10 1.5 12 0v-6"/><path d="M22 7v6"/>' . $end;
        case 'logout':
            return $svg . '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>' . $end;
        case 'users':
            return $svg . '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>' . $end;
        case 'admin':
            return $svg . '<path d="M12 2L4 5v7c0 5 3.5 9 8 10 4.5-1 8-5 8-10V5l-8-3z"/><path d="M9 12l2 2 4-4"/>' . $end;
        case 'bell':
            return $svg . '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/>' . $end;
        default:
            return $svg . '<circle cx="12" cy="12" r="9"/>' . $end;
    }
}

$NAV = [
  // — Cœur & temps réel —
  'dashboard'        => 'Dashboard',
  'ai-dashboard'     => 'AI Dashboard',
  'map'              => 'Map / Air Quality',
  'api-data'         => 'Real-Time API Data',
  'alerts'           => 'Alerts',
  'smart-alerts'     => 'Smart Alerts',
  // — Prévision & IA explicable —
  'forecast'         => 'Forecast — ML/DL',
  'forecast-ml'      => 'ML — SHAP / LIME / ROC',
  'deep-learning'    => 'Deep Learning — BiLSTM',
  'bilstm-ae'        => 'BiLSTM + Autoencoder',
  'comparison'       => 'Model Comparison',
  'ensemble'         => 'Ensemble & Trust',
  'anomaly'          => 'Anomaly Detection',
  // — Santé —
  'health-impact'    => 'Health Impact Index',
  'symptoms'         => 'Symptoms',
  'diary'            => 'Health Diary',
  'correlation'      => 'Correlations',
  'weekly'           => 'Weekly AI Report',
  // — ML avancé & recherche —
  'fuzzy-type2'      => 'Fuzzy Logic Type-2',
  'granger'          => 'Granger Causality',
  'drift'            => 'Concept Drift & AutoOpt',
  'spatial'          => 'Spatial Propagation',
  'federated'        => 'Federated Learning',
  'digital-twin'     => 'Digital Twin',
  'model-registry'   => 'Model Registry & A/B',
  'comparative-literature' => 'Literature Comparison',
  'upgrade-dashboard'=> 'Upgrades Overview',
  // — Communauté & contenus —
  'community'        => 'Community',
  'reports'          => 'Reports',
  'citizen-reports'  => 'Reports',
  'zones'            => 'Risk Zones',
  'chatbot'          => 'Chatbot Nafass',
  'school'           => 'School Mode',
  'learn'            => 'Learn & Prevent',
  // — Administration —
  'admin'            => 'Admin Panel',
  'users'            => 'User Management',
  // — Compte —
  'settings'         => 'Settings',
  'profile'          => 'My Profile',
  'help'             => 'Help',
];

$roleLabel = [
  'citizen' => ['Citizen','Citizen'],
  'health'  => ['Health Authority','Health Authority'],
  'school'  => ['School Director','School Director'],
  'admin'   => ['Administrator','Administrator'],
  'super_admin' => ['Super Admin','Super Admin'],
][$me['role']] ?? ['User',''];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Nafass — Gabès · Air Quality & Health Monitoring</title>
  <link rel="stylesheet" href="styles/theme.css">
  <link rel="stylesheet" href="styles/sidebar.css">
  <link rel="stylesheet" href="styles/roles.css">
  <link rel="stylesheet" href="styles/notifications.css">
  <link rel="stylesheet" href="styles/school.css">
  <link rel="stylesheet" href="styles/reports.css">
  <link rel="stylesheet" href="styles/dashboard-admin.css">
  <link rel="stylesheet" href="styles/users.css">
  <link rel="stylesheet" href="styles/profile.css">
  <link rel="stylesheet" href="styles/dashboard.css">
  <link rel="stylesheet" href="styles/dashboard-feed.css">
  <link rel="stylesheet" href="styles/messenger.css">
  <link rel="stylesheet" href="styles/alerts.css">
  <link rel="stylesheet" href="styles/symptoms-chat.css">
  <link rel="stylesheet" href="styles/features-2026.css">
  <link rel="stylesheet" href="styles/forecast.css">
  <link rel="stylesheet" href="styles/scientific.css">
  <link rel="stylesheet" href="styles/learn.css">
  <link rel="stylesheet" href="styles/mobile.css">
  <link rel="stylesheet" href="styles/ai-dashboard.css">

  <!-- Leaflet (carte réelle de Gabès) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <!-- Chart.js (panel admin) -->
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Socket.IO (temps réel — PART 17.5) -->
  <script defer src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>

  <!-- Favicon + PWA (logo5) -->
  <link rel="icon" type="image/png" sizes="32x32" href="assets/logo-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/logo-16.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/logo-192.png">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0d3b66">
  <meta name="application-name" content="Nafass">
  <meta name="apple-mobile-web-app-title" content="Nafass">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="mobile-web-app-capable" content="yes">
  <!-- Open Graph / partage -->
  <meta property="og:title" content="Nafass — Gabès · Air Quality & Health Monitoring">
  <meta property="og:description" content="Health & air quality monitoring system for Gabès, Tunisia.">
  <meta property="og:image" content="assets/og-image.png">
  <meta property="og:type" content="website">
</head>
<body data-role="<?= htmlspecialchars($me['role']) ?>">

<div class="app">

  <!-- Mobile sidebar overlay (tap to close) -->
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- =================== SIDEBAR =================== -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="logo">ن</div>
      <div>
        <div class="name">Nafass</div>
        <div class="slogan">نَفَس · Gabès</div>
      </div>
    </div>

    <div class="status-card">
      <div class="lbl">Global Status</div>
      <div class="val">
        <span id="global-dot" class="dot safe"></span>
        <span id="global-label">—</span>
      </div>
      <div class="meta">
        <span><span class="online-dot"></span>Local online</span>
        <span id="global-alerts">— alerts</span>
      </div>
    </div>

    <nav class="nav">
      <?php foreach ($NAV as $key => $label): ?>
        <?php if (in_array($key, $allowed, true)): ?>
          <a href="#/<?= $key ?>"><span class="ico"><?= nav_icon($key) ?></span><span class="lbl"><?= $label ?></span></a>
        <?php endif ?>
      <?php endforeach ?>
      <a href="logout.php" class="nav-logout"><span class="ico"><?= nav_icon('logout') ?></span><span class="lbl">Logout</span></a>
    </nav>

    <div class="sidebar-foot">
      <div class="row between">
        <span>v1.1.0 — local</span>
        <span class="muted"><?= htmlspecialchars($me['username']) ?></span>
      </div>
      <div style="margin-top:6px">A smarter city, cleaner air, better health</div>
    </div>
  </aside>

  <!-- =================== MAIN =================== -->
  <main>
    <div class="topbar">
      <button id="sidebar-toggle" class="sidebar-toggle" aria-label="Open menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <div>
        <h1 id="page-title">Dashboard</h1>
        <div class="subtitle">
        <span id="page-title-ar">Dashboard</span> — Nafass · Health & Air Quality Monitoring in Gabès
        </div>
      </div>
      <div class="topbar-right">
        <!-- Cloche de notifications -->
        <div class="notif-wrap">
          <button id="notif-bell" class="notif-bell" title="Notifications">
            <?= nav_icon('bell') ?>
            <span id="notif-badge" class="notif-badge">0</span>
          </button>
          <div id="notif-panel" class="notif-panel">
            <header>
              <span>Notifications</span>
              <button id="notif-clear">Mark all as read</button>
            </header>
            <div id="notif-list" class="notif-list">
              <div class="notif-empty">Loading…</div>
            </div>
          </div>
        </div>

        <div style="text-align:right;line-height:1.2">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($me['full_name'] ?: $me['username']) ?></div>
          <div class="muted" style="font-size:11px"><?= htmlspecialchars($roleLabel[1]) ?></div>
        </div>
        <span class="role-badge"><?= htmlspecialchars($roleLabel[0]) ?></span>
        <span id="live-badge" class="live-badge off" title="Monitoring temps réel">… connexion</span>
      </div>
    </div>

    <div class="main">
      <div id="view"></div>
    </div>
  </main>

</div>

<!-- Container des toasts -->
<div id="toast-wrap" class="toast-wrap"></div>

<!-- Injection serveur du rôle pour le JS -->
<script>
  window.GT_USER = <?= json_encode([
    'id'        => $me['id'],
    'username'  => $me['username'],
    'full_name' => $me['full_name'],
    'role'      => $me['role'],
    'allowed'   => $allowed,
    'must_change_password' => (int)($me['must_change_password'] ?? 0),
    'avatar'    => $me['avatar_path'] ?? null,
  ], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="scripts/api.js"></script>
<script src="scripts/app.js"></script>
<script src="scripts/notifications.js"></script>
<script src="scripts/messenger.js"></script>
<script src="scripts/pages/dashboard.js"></script>
<script src="scripts/pages/dashboard-feed.js"></script>
<script src="scripts/pro-icons.js"></script>
<script src="scripts/chart-insight.js"></script>
<script src="scripts/chart-tools.js"></script>
<script src="lib/timelapse_export.js"></script>
<script src="scripts/pages/map.js"></script>
<script src="scripts/pages/ai-dashboard.js"></script>
<script src="scripts/pages/api-data.js"></script>
<script src="scripts/pages/alerts.js"></script>
<script src="scripts/pages/reports.js"></script>
<script src="scripts/pages/citizen-reports.js"></script>
<script src="scripts/pages/symptoms.js"></script>
<script src="scripts/pages/chatbot.js"></script>
<script src="scripts/pages/school.js"></script>
<script src="scripts/pages/zones.js"></script>
<script src="scripts/pages/diary.js"></script>
<script src="scripts/pages/correlation.js"></script>
<script src="scripts/pages/weekly.js"></script>
<script src="scripts/pages/forecast.js"></script>
<script src="scripts/pages/deep-learning.js"></script>
<script src="scripts/pages/bilstm-ae.js"></script>
<script src="scripts/pages/anomaly.js"></script>
<script src="scripts/pages/comparison.js"></script>
<script src="scripts/pages/fuzzy-type2.js"></script>
<script src="scripts/pages/forecast-ml.js"></script>
<script src="scripts/pages/granger.js"></script>
<script src="scripts/pages/health-impact.js"></script>
<script src="scripts/pages/drift.js"></script>
<script src="scripts/pages/spatial.js"></script>
<script src="scripts/pages/ensemble.js"></script>
<script src="scripts/pages/smart-alerts.js"></script>
<script src="scripts/pages/federated.js"></script>
<script src="scripts/pages/comparative-literature.js"></script>
<script src="scripts/pages/upgrade-dashboard.js"></script>
<script src="scripts/pages/model-registry.js"></script>
<script src="scripts/pages/digital-twin.js"></script>
<script src="scripts/pages/learn.js"></script>
<script src="scripts/pages/settings.js"></script>
<script src="scripts/pages/help.js"></script>
<script src="scripts/pages/dashboard-admin.js"></script>
<script src="scripts/pages/users.js"></script>
<script src="scripts/pages/profile.js"></script>
<script src="scripts/router.js"></script>
<script src="scripts/force-password.js"></script>
<script defer src="scripts/websocket_client.js"></script>
<script src="scripts/pwa.js"></script>
<script src="scripts/mobile.js"></script>
</body>
</html>