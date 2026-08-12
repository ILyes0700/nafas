/* =====================================================
 *  Nafass · Mobile UX
 *  - Sidebar burger toggle for ≤768px
 *  - Auto-close on nav click
 *  - Online/Offline banner
 *  - Detects Capacitor host and sets body.is-mobile-app
 * ===================================================== */
(function () {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const toggle  = document.getElementById('sidebar-toggle');

  function open()  { sidebar?.classList.add('open');    overlay?.classList.add('show'); }
  function close() { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); }
  function isOpen(){ return sidebar?.classList.contains('open'); }

  toggle?.addEventListener('click', () => { isOpen() ? close() : open(); });
  overlay?.addEventListener('click', close);

  // Close when user taps a nav link (so the page change feels native)
  sidebar?.querySelectorAll('.nav a, a.nav-logout').forEach(a => {
    a.addEventListener('click', () => {
      if (window.innerWidth <= 768) close();
    });
  });

  // Close on resize back to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) close();
  });

  // ----- Capacitor / mobile-app detection -----
  const ua = navigator.userAgent || '';
  const isApp = /CapacitorApp|NafassApp/.test(ua) || !!window.Capacitor;
  if (isApp) document.body.classList.add('is-mobile-app');

  // ----- Online / Offline banner -----
  let banner = document.getElementById('offline-banner');
  if (!banner) {
    banner = document.createElement('div');
    banner.id = 'offline-banner';
    banner.className = 'offline-banner';
    banner.textContent = '⚠️ Hors ligne — affichage des données mises en cache';
    document.body.appendChild(banner);
  }
  function syncOnlineState() {
    if (navigator.onLine) banner.classList.remove('show');
    else                  banner.classList.add('show');
  }
  window.addEventListener('online',  syncOnlineState);
  window.addEventListener('offline', syncOnlineState);
  syncOnlineState();
})();
