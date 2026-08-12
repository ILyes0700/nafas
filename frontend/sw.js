/* Nafass — Service Worker (PWA offline support)
 *
 * Strategy:
 *  - Pre-cache the application shell (HTML pages, CSS, JS, icons) on install.
 *  - Static GETs (pages/, scripts/, styles/, assets/, manifest)  → cache-first.
 *  - GET /backend/api/*                                         → network-first
 *      with cache fallback (so dashboards still render the last
 *      known data when the device is offline).
 *  - Anything else (POST, cross-origin, telemed Jitsi, etc.)    → straight to
 *      the network — never cached.
 *
 * Bump CACHE_VERSION whenever you ship breaking front-end changes;
 * the activate handler will purge any older caches.
 */

'use strict';

const CACHE_VERSION = 'nafass-v2.7.2';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

/* Application shell — installed up-front so the app boots offline. */
const APP_SHELL = [
  './',
  './index.php',
  './manifest.json',
  './offline.html',

  /* Stylesheets */
  './styles/theme.css',
  './styles/sidebar.css',
  './styles/roles.css',
  './styles/notifications.css',
  './styles/school.css',
  './styles/reports.css',
  './styles/dashboard-admin.css',
  './styles/dashboard.css',
  './styles/alerts.css',
  './styles/symptoms-chat.css',
  './styles/features-2026.css',
  './styles/learn.css',
  './styles/mobile.css',

  /* Page fragments (loaded on hash change) */
  './pages/dashboard.html',
  './pages/dashboard-admin.html',
  './pages/map.html',
  './pages/alerts.html',
  './pages/reports.html',
  './pages/citizen-reports.html',
  './pages/symptoms.html',
  './pages/chatbot.html',
  './pages/school.html',
  './pages/zones.html',
  './pages/diary.html',
  './pages/correlation.html',
  './pages/weekly.html',
  './pages/learn.html',
  './pages/settings.html',
  './pages/help.html',

  /* Core scripts */
  './scripts/app.js',
  './scripts/router.js',
  './scripts/notifications.js',
  './scripts/i18n.js',
  './scripts/pwa.js',
  './scripts/mobile.js',
  './scripts/api.js',
  './scripts/pages/dashboard.js',
  './scripts/pages/dashboard-admin.js',
  './scripts/pages/map.js',
  './scripts/pages/alerts.js',
  './scripts/pages/reports.js',
  './scripts/pages/citizen-reports.js',
  './scripts/pages/symptoms.js',
  './scripts/pages/chatbot.js',
  './scripts/pages/school.js',
  './scripts/pages/zones.js',
  './scripts/pages/diary.js',
  './scripts/pages/correlation.js',
  './scripts/pages/weekly.js',
  './scripts/pages/learn.js',
  './scripts/pages/settings.js',
  './scripts/pages/help.js',

  /* Icons (only the ones we are sure exist) */
  './assets/logo-48.png',
  './assets/logo-96.png',
  './assets/logo-128.png',
  './assets/logo-192.png',
  './assets/logo-256.png',
  './assets/logo-512.png',
  './assets/logo.svg',
  './assets/favicon.ico',
];

/* ---------- INSTALL ---------- */
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) =>
        // addAll() rejects if any URL fails — fall back to per-URL adds so we
        // don't end up with no cache at all if a single asset is missing.
        Promise.allSettled(APP_SHELL.map((u) => cache.add(u)))
      )
      .then(() => self.skipWaiting())
  );
});

/* ---------- ACTIVATE — purge old caches ---------- */
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => !k.startsWith(CACHE_VERSION))
          .map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

/* ---------- Helpers ---------- */
function isStaticAsset(url) {
  return /\.(?:css|js|png|jpg|jpeg|gif|svg|ico|webp|woff2?|ttf|json)$/i.test(url.pathname)
      || /\/(styles|scripts|pages|assets)\//.test(url.pathname);
}

function isApiRequest(url) {
  return /\/backend\/api\//.test(url.pathname);
}

/* User-specific / authenticated endpoints must never be cached or replayed —
   otherwise one user could be shown another user's cached data. */
function isPrivateApi(url) {
  return /\/(profile|feed|follow|messages|auth|admin-users|notifications)\.php$/.test(url.pathname);
}

/* ---------- FETCH ---------- */
self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Bypass non-GET requests entirely (POST, PUT, DELETE) — always go live.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Bypass cross-origin requests (Leaflet tiles, Jitsi, Groq…).
  if (url.origin !== self.location.origin) return;

  // Bypass auth/login flow + CGI we shouldn't replay from cache.
  if (
    url.pathname.endsWith('/login.php')   ||
    url.pathname.endsWith('/logout.php')  ||
    url.pathname.endsWith('/register.php')
  ) return;

  // -------- Strategy 0: private API GET → network-only, never cached --------
  if (isApiRequest(url) && isPrivateApi(url)) {
    event.respondWith(fetch(request));
    return;
  }

  // -------- Strategy 1: API GET → network-first, fallback to cache --------
  if (isApiRequest(url)) {
    event.respondWith(
      fetch(request)
        .then((res) => {
          // Only cache OK responses (2xx).
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(RUNTIME_CACHE).then((c) => c.put(request, copy));
          }
          return res;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // -------- Strategy 2: static asset → cache-first --------
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(request).then((hit) => {
        if (hit) return hit;
        return fetch(request).then((res) => {
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(STATIC_CACHE).then((c) => c.put(request, copy));
          }
          return res;
        });
      })
    );
    return;
  }

  // -------- Strategy 3: HTML / index navigation --------
  // Try network first so users always see the latest shell when online,
  // then fall back to whatever shell is in the cache, then to offline.html.
  event.respondWith(
    fetch(request)
      .then((res) => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(RUNTIME_CACHE).then((c) => c.put(request, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(request)
          .then((hit) => hit || caches.match('./offline.html'))
      )
  );
});

/* Allow pages to ask the SW to update immediately. */
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});
