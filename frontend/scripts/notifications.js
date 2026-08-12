/* Bell + toast for near real-time notifications.
   Polling every 15 seconds — detects new alerts based on max known id,
   updates badge and emits 'gt:alerts-updated' event (CustomEvent) that
   Dashboard and Alerts page listen to for immediate refresh. */

(function () {
  let lastSeenId = parseInt(localStorage.getItem('gt_last_alert_id') || '0', 10);
  let lastKnownMaxId = 0;
  let firstLoad = true;

  function elBadge()  { return document.getElementById('notif-badge'); }
  function elBell()   { return document.getElementById('notif-bell'); }
  function elPanel()  { return document.getElementById('notif-panel'); }
  function elList()   { return document.getElementById('notif-list'); }
  function elToasts() { return document.getElementById('toast-wrap'); }

  function stripAuto(t) { return (t || '').replace(/^\[AUTO:[^\]]+\]\s*/, ''); }

  function fmt(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60)   return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + ' min';
    if (diff < 86400)return Math.floor(diff/3600) + ' h';
    return d.toLocaleDateString('en-US') + ' ' + d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
  }

  function render(alerts) {
    const list = elList();
    if (!list) return;
    if (!alerts.length) {
      list.innerHTML = '<div class="notif-empty">No recent alerts</div>';
      return;
    }
    list.innerHTML = alerts.slice(0, 30).map(a => {
      const sev = a.severity || 'info';
      const ICONS = {
        critical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        danger:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        warning:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>'
      };
      const icon = ICONS[sev] || ICONS.info;
      return `
        <div class="notif-item" onclick="window.location.hash='#/alerts'">
          <div class="ic ${sev}">${icon}</div>
          <div class="body">
            <div class="ttl">${stripAuto(a.title)}</div>
            <div class="msg">${a.zone_name ? a.zone_name + ' · ' : ''}${a.message || ''}</div>
            <div class="when">${fmt(a.created_at)}</div>
          </div>
        </div>`;
    }).join('');
  }

  function toast(a) {
    const wrap = elToasts(); if (!wrap) return;
    const sev = a.severity || 'info';
    const TOAST_ICONS = {
      critical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:-3px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
      danger:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:-3px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
      warning:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:-3px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
      info:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:-3px"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/></svg>'
    };
    const icon = TOAST_ICONS[sev] || TOAST_ICONS.info;
    const div = document.createElement('div');
    div.className = 'toast ' + sev;
    div.innerHTML = `
      <div class="t-ttl">${icon} ${stripAuto(a.title)}</div>
      <div class="t-msg">${a.zone_name ? a.zone_name + ' — ' : ''}${a.message || ''}</div>
    `;
    div.addEventListener('click', () => window.location.hash = '#/alerts');
    wrap.appendChild(div);
    setTimeout(() => div.remove(), 6000);
  }

  async function poll() {
    try {
      const data = await GT.api.get('alerts.php');
      const alerts = data.alerts || [];
      render(alerts);

      const maxId = alerts.reduce((m, a) => Math.max(m, +a.id), 0);
      const newOnes = alerts.filter(a => +a.id > lastSeenId);

      if (!firstLoad && maxId > lastKnownMaxId) {
        // Emit event so Dashboard / Alerts page refresh immediately
        document.dispatchEvent(new CustomEvent('gt:alerts-updated', {
          detail: { newAlerts: alerts.filter(a => +a.id > lastKnownMaxId) }
        }));
      }

      if (!firstLoad && newOnes.length > 0) {
        newOnes.slice(0, 3).forEach(toast);
        const bell = elBell();
        if (bell) {
          bell.classList.remove('pulse');
          void bell.offsetWidth;
          bell.classList.add('pulse');
        }
      }

      const unseenCount = alerts.filter(a => +a.id > lastSeenId).length;
      const badge = elBadge();
      if (badge) {
        badge.textContent = unseenCount > 99 ? '99+' : unseenCount;
        badge.classList.toggle('has', unseenCount > 0);
      }

      lastKnownMaxId = maxId;
      if (firstLoad) {
        lastSeenId = maxId;
        firstLoad = false;
      }
    } catch (_) { /* offline */ }
  }

  function markAllRead() {
    GT.api.get('alerts.php').then(d => {
      const max = (d.alerts || []).reduce((m,a) => Math.max(m, +a.id), 0);
      lastSeenId = max;
      localStorage.setItem('gt_last_alert_id', String(max));
      const badge = elBadge();
      if (badge) { badge.textContent = '0'; badge.classList.remove('has'); }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const bell = elBell();
    const panel = elPanel();
    if (bell && panel) {
      bell.addEventListener('click', (e) => {
        e.stopPropagation();
        panel.classList.toggle('open');
      });
      document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== bell) panel.classList.remove('open');
      });
      const clearBtn = document.getElementById('notif-clear');
      if (clearBtn) clearBtn.addEventListener('click', markAllRead);
    }
    poll();
    setInterval(poll, 15000);
  });
})();
