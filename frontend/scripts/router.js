/* Mini hash-based SPA router for Gabes Tatenafas (Nafass).
   Blocks routes that aren't allowed for the current role.

   v4.0 - Migration donnees reelles Open-Meteo :
     - La route 'cgan' (Conditional GAN) a ete SUPPRIMEE. Le modele generatif
       et toute la generation de donnees synthetiques ont ete retires du
       projet ; le pipeline s'entraine desormais uniquement sur les donnees
       reelles de la table open_data.
     - Penser a supprimer aussi frontend/pages/cgan.html,
       frontend/scripts/pages/cgan.js, le lien de nav dans index.php et
       l'entree 'cgan' dans backend/lib/auth.php.
     - Ajout de la route 'bilstm-ae' pour le nouveau modele BiLSTM+Autoencoder.
*/

const ROUTES = {
  'dashboard'      : { file: 'pages/dashboard.html', title: 'Dashboard', ar: 'لوحة القيادة', init: 'initDashboard' },
  'community'      : { file: 'pages/community.html', title: 'Community', ar: 'المجتمع', init: 'initDashboardFeed' },
  'admin'          : { file: 'pages/dashboard-admin.html', title: 'Administrator Panel', ar: 'لوحة الإدارة', init: 'initDashboardAdmin' },
  'users'          : { file: 'pages/users.html', title: 'User Management', ar: 'إدارة المستخدمين', init: 'initUsers' },
  'map'            : { file: 'pages/map.html', title: 'Map / Air Quality', ar: 'الخريطة', init: 'initMap' },
  'api-data'       : { file: 'pages/api-data.html', title: 'Real-Time API Data', ar: 'بيانات API', init: 'initApiData' },
  'alerts'         : { file: 'pages/alerts.html', title: 'Alerts Center', ar: 'التنبيهات', init: 'initAlerts' },
  'reports'        : { file: 'pages/reports.html', title: 'Reports', ar: 'التقارير', init: 'initReports' },
  'citizen-reports': { file: 'pages/citizen-reports.html', title: 'Citizen Reports', ar: 'بلاغات', init: 'initCitizenReports' },
  'symptoms'       : { file: 'pages/symptoms.html', title: 'Symptoms', ar: 'الأعراض', init: 'initSymptoms' },
  'chatbot'        : { file: 'pages/chatbot.html', title: 'Nafass Chatbot', ar: 'نفس', init: 'initChatbot' },
  'school'         : { file: 'pages/school.html', title: 'School Mode', ar: 'وضع المدرسة', init: 'initSchool' },
  'zones'          : { file: 'pages/zones.html', title: 'Risk Zones', ar: 'مناطق الخطر', init: 'initZones' },
  'diary'          : { file: 'pages/diary.html', title: 'Health Diary', ar: 'يوميات صحتي', init: 'initDiary' },
  'correlation'    : { file: 'pages/correlation.html', title: 'Correlations', ar: 'الارتباطات', init: 'initCorrelation' },
  'weekly'         : { file: 'pages/weekly.html', title: 'Weekly AI Report', ar: 'الملخص الأسبوعي', init: 'initWeekly' },
  'forecast'       : { file: 'pages/forecast.html', title: 'Forecast — Hybrid ML/DL', ar: 'التنبؤ الهجين', init: 'initForecast' },
  'deep-learning'  : { file: 'pages/deep-learning.html', title: 'Deep Learning — BiLSTM', ar: 'التعلّم العميق', init: 'initDeepLearning' },
  /* v4.0 : nouveau modele BiLSTM + Autoencoder. Il partage la page
     deep-learning.html, dont le JS affiche desormais l'onglet "BiLSTM+AE"
     a partir des lignes model_performance portant ce model_name. */
  'bilstm-ae'      : { file: 'pages/deep-learning.html', title: 'BiLSTM + Autoencoder', ar: 'المشفّر التلقائي', init: 'initDeepLearning' },
  'anomaly'        : { file: 'pages/anomaly.html', title: 'Anomaly Detection', ar: 'كشف الشذوذ', init: 'initAnomaly' },
  'comparison'     : { file: 'pages/comparison.html', title: 'Model Comparison', ar: 'مقارنة النماذج', init: 'initComparison' },
  'fuzzy-type2'    : { file: 'pages/fuzzy-type2.html', title: 'Fuzzy Logic Type-2', ar: 'المنطق الضبابي', init: 'initFuzzyType2' },
  /* v4.0 : la route 'cgan' a ete supprimee ici. */
  'forecast-ml'    : { file: 'pages/forecast-ml.html', title: 'ML — SHAP / LIME / ROC', ar: 'تعلّم آلي', init: 'initForecastMl' },
  'granger'        : { file: 'pages/granger.html', title: 'Granger Causality', ar: 'السببية', init: 'initGranger' },
  'health-impact'  : { file: 'pages/health-impact.html', title: 'Health Impact Index', ar: 'الأثر الصحي', init: 'initHealthImpact' },
  'drift'          : { file: 'pages/drift.html', title: 'Concept Drift & AutoOpt', ar: 'انحراف المفهوم', init: 'initDrift' },
  'spatial'        : { file: 'pages/spatial.html', title: 'Spatial Propagation', ar: 'الانتشار المكاني', init: 'initSpatial' },
  'ensemble'       : { file: 'pages/ensemble.html', title: 'Ensemble & Trust', ar: 'التجميع', init: 'initEnsemble' },
  'smart-alerts'   : { file: 'pages/smart-alerts.html', title: 'Smart Alert Engine', ar: 'تنبيهات ذكية', init: 'initSmartAlerts' },
  'federated'      : { file: 'pages/federated.html', title: 'Federated Learning', ar: 'التعلم الفيدرالي', init: 'initFederated' },
  'comparative-literature': { file: 'pages/comparative-literature.html', title: 'Literature Comparison', ar: 'مقارنة الأدبيات', init: 'initComparativeLiterature' },
  'upgrade-dashboard': { file: 'pages/upgrade-dashboard.html', title: 'Upgrades Overview', ar: 'نظرة عامة', init: 'initUpgradeDashboard' },
  'model-registry' : { file: 'pages/model-registry.html', title: 'Model Registry & A/B', ar: 'سجل النماذج', init: 'initModelRegistry' },
  'digital-twin'   : { file: 'pages/digital-twin.html', title: 'Digital Twin', ar: 'التوأم الرقمي', init: 'initDigitalTwin' },
  'ai-dashboard'   : { file: 'pages/ai-dashboard.html', title: 'AI Dashboard', ar: 'لوحة الذكاء', init: 'initAiDashboard' },
  'learn'          : { file: 'pages/learn.html', title: 'Learn & Prevent', ar: 'تعلّم و توقّف', init: 'initLearn' },
  'settings'       : { file: 'pages/settings.html', title: 'Settings', ar: 'الإعدادات', init: 'initSettings' },
  'profile'        : { file: 'pages/profile.html', title: 'My Profile', ar: 'الملف الشخصي', init: 'initProfile' },
  'help'           : { file: 'pages/help.html', title: 'Help', ar: 'مساعدة', init: 'initHelp' },
};

async function loadRoute() {
  const allowed = ((window.GT_USER && GT_USER.allowed) || Object.keys(ROUTES)).slice();
  // Profile is available to every authenticated user, regardless of role.
  if (!allowed.includes('profile')) allowed.push('profile');

  /* v4.0 : filet de securite. Si une session ouverte avant la migration a
     encore 'cgan' dans ses permissions (JWT/cookie non expire, ou auth.php
     pas encore mis a jour), on retire l'entree ici pour eviter un lien mort
     et surtout pour eviter que 'cgan' devienne allowed[0] et serve de route
     de repli par defaut. */
  const cganIdx = allowed.indexOf('cgan');
  if (cganIdx !== -1) allowed.splice(cganIdx, 1);

  let raw = (window.location.hash || '#/dashboard').replace('#/','');
  // Support query params in the hash, e.g. #/profile?u=5 (independent page per user).
  const qIdx = raw.indexOf('?');
  let key = qIdx >= 0 ? raw.slice(0, qIdx) : raw;
  window.__routeParams = new URLSearchParams(qIdx >= 0 ? raw.slice(qIdx + 1) : '');

  if (!ROUTES[key]) key = 'dashboard';
  if (!allowed.includes(key)) {
    // Rediriger vers la première route autorisée
    key = allowed[0] || 'dashboard';
    window.location.hash = '#/' + key;
    return;
  }
  const route = ROUTES[key];

  document.querySelectorAll('.nav a').forEach(a => {
    a.classList.toggle('active', a.getAttribute('href') === '#/' + key);
  });

  document.getElementById('page-title').textContent = route.title;
  document.getElementById('page-title-ar').textContent = route.ar;

  const main = document.getElementById('view');
  main.innerHTML = '<div class="loading">Loading…</div>'; /* ⚠ A VERIFIER */

  try {
    const html = await fetch(route.file).then(r => r.text());
    main.innerHTML = `<div class="page">${html}</div>`; /* ⚠ A VERIFIER */
    if (route.init && typeof window[route.init] === 'function') {
      window[route.init]();
    }
  } catch (e) {
    main.innerHTML = `<div class="error"><h3>Error</h3><p>Unable to load ${route.file}</p></div>`; /* ⚠ A VERIFIER */
  }
}

window.addEventListener('hashchange', loadRoute);
window.addEventListener('DOMContentLoaded', () => {
  if (!window.location.hash) window.location.hash = '#/dashboard';
  else loadRoute();
});