/* PWA bootstrap — registers the service worker and shows lightweight UI for
 * the install prompt and the offline state. Designed to be safe in Electron
 * (where service workers also work for http://localhost) and on the open web.
 */
(function () {
  'use strict';

  /* ---------- 1. Register service worker ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('./sw.js')
        .then((reg) => {
          // Soft-update: reload once a fresh worker takes control.
          if (reg.waiting) reg.waiting.postMessage('SKIP_WAITING');
          reg.addEventListener('updatefound', () => {
            const sw = reg.installing;
            if (!sw) return;
            sw.addEventListener('statechange', () => {
              if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                // A new version is ready; reload after a short delay.
                console.info('[Nafass] New version ready — reloading.');
                setTimeout(() => window.location.reload(), 800);
              }
            });
          });
        })
        .catch((e) => console.warn('[Nafass] SW registration failed:', e));
    });
  }

  /* ---------- 2. Install banner (Add to Home Screen) ---------- */
  let deferredPrompt = null;

  function makeBanner(text, ctaLabel, onCta) {
    if (document.getElementById('nafass-pwa-banner')) return null;
    const el = document.createElement('div');
    el.id = 'nafass-pwa-banner';
    el.className = 'nafass-pwa-banner';
    el.innerHTML = `
      <div class="npb-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v12"/><polyline points="7 10 12 15 17 10"/><line x1="5" y1="21" x2="19" y2="21"/>
        </svg>
      </div>
      <div class="npb-text">${text}</div>
      <div class="npb-actions">
        <button class="npb-cta" type="button">${ctaLabel}</button>
        <button class="npb-close" type="button" aria-label="Dismiss">×</button>
      </div>`;
    document.body.appendChild(el);
    el.querySelector('.npb-cta').addEventListener('click', onCta);
    el.querySelector('.npb-close').addEventListener('click', () => {
      el.remove();
      try { localStorage.setItem('nafass.pwa.banner.dismissed', String(Date.now())); } catch (_) {}
    });
    return el;
  }

  function bannerWasDismissedRecently() {
    try {
      const t = Number(localStorage.getItem('nafass.pwa.banner.dismissed') || 0);
      // Don't reshow for 7 days after dismiss.
      return t && (Date.now() - t) < 7 * 24 * 3600 * 1000;
    } catch (_) { return false; }
  }

  window.addEventListener('beforeinstallprompt', (ev) => {
    ev.preventDefault();
    deferredPrompt = ev;
    if (bannerWasDismissedRecently()) return;

    makeBanner(
      'Install Nafass on your device for faster access and offline use.',
      'Install',
      async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        try { await deferredPrompt.userChoice; } catch (_) {}
        deferredPrompt = null;
        const b = document.getElementById('nafass-pwa-banner');
        if (b) b.remove();
      }
    );
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const b = document.getElementById('nafass-pwa-banner');
    if (b) b.remove();
  });

  /* ---------- 3. Offline / online toast ---------- */
  function setNetStatus(online) {
    let bar = document.getElementById('nafass-net-bar');
    if (online) {
      if (bar) {
        bar.classList.add('np-back-online');
        setTimeout(() => bar && bar.remove(), 2200);
      }
      return;
    }
    if (bar) return;
    bar = document.createElement('div');
    bar.id = 'nafass-net-bar';
    bar.className = 'nafass-net-bar';
    bar.innerHTML = `
      <span class="np-dot"></span>
      <span class="np-text">You are offline — showing cached data.</span>`;
    document.body.appendChild(bar);
  }

  window.addEventListener('online',  () => setNetStatus(true));
  window.addEventListener('offline', () => setNetStatus(false));
  if (!navigator.onLine) setNetStatus(false);
})();
