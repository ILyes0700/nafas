/* Page Alertes — style "inbox compact" : liste dense groupée par date,
   click ligne = expand/collapse pour montrer message + actions.
   Auto-refresh 20s + écoute 'gt:alerts-updated'. */

let _alertsTimer = null;
let _alertsCurrentFilter = 'all';
let _alertsSearch = '';
let _alertsListenerAttached = false;
let _alertsCache = [];
let _alertsExpanded = new Set();

const SEVERITIES = ['critical', 'danger', 'warning', 'info'];

function _stripAutoTagAlerts(title) {
  return (title || '').replace(/^\[AUTO:[^\]]+\]\s*/, '');
}

function _esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* Mini icône SVG selon sévérité (pour la pastille de la ligne) */
function _sevIcon(sev) {
  const o = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">';
  switch (sev) {
    case 'critical':
    case 'warning':
      return o + '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r=".5" fill="currentColor"/></svg>';
    case 'danger':
      return o + '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>';
    default:
      return o + '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }
}

/* Formatte une heure HH:MM */
function _fmtTime(iso) {
  if (!iso) return '—';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
}

/* Returns the date bucket (Today, Yesterday, etc.) */
function _dateBucket(iso) {
  if (!iso) return { key: 'older', label: 'Older' };
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d.getTime())) return { key: 'older', label: 'Older' };
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yest  = new Date(today); yest.setDate(today.getDate() - 1);
  const week  = new Date(today); week.setDate(today.getDate() - 7);
  if (d >= today) return { key: 'today', label: 'Today' };
  if (d >= yest)  return { key: 'yesterday', label: 'Yesterday' };
  if (d >= week)  return { key: 'week', label: 'Last 7 days' };
  return { key: 'older', label: 'Older' };
}

const SEV_LABELS = { critical: 'Critical', danger: 'Danger', warning: 'Watch', info: 'Info' };

function _renderPills(alerts) {
  const wrap = document.getElementById('alerts-pills');
  if (!wrap) return;
  const counts = { critical: 0, danger: 0, warning: 0, info: 0 };
  alerts.forEach(a => { if (counts[a.severity] !== undefined) counts[a.severity]++; });
  wrap.innerHTML = SEVERITIES.map(sev => `
    <button class="ah-pill ${_alertsCurrentFilter === sev ? 'active' : ''}" data-sev="${sev}">
      <span class="dot"></span>
      <span class="num">${counts[sev]}</span>
      <span>${SEV_LABELS[sev]}</span>
    </button>
  `).join('');
  wrap.querySelectorAll('.ah-pill').forEach(el => {
    el.addEventListener('click', () => {
      const f = el.dataset.sev;
      _alertsCurrentFilter = (_alertsCurrentFilter === f) ? 'all' : f;
      _refreshChips();
      _renderInbox();
      _renderPills(_alertsCache);
    });
  });
}

function _refreshChips() {
  document.querySelectorAll('.at-chip').forEach(c => {
    c.classList.toggle('active', (c.dataset.filter || 'all') === _alertsCurrentFilter);
  });
}

function _renderInbox() {
  const wrap = document.getElementById('alerts-list');
  if (!wrap) return;

  const order = SEVERITIES;
  const term = _alertsSearch.trim().toLowerCase();

  const items = _alertsCache
    .filter(a => _alertsCurrentFilter === 'all' || a.severity === _alertsCurrentFilter)
    .filter(a => {
      if (!term) return true;
      return (a.title || '').toLowerCase().includes(term)
          || (a.message || '').toLowerCase().includes(term)
          || (a.zone_name || '').toLowerCase().includes(term);
    })
    .slice()
    .sort((a, b) => new Date((b.created_at || '').replace(' ', 'T')) - new Date((a.created_at || '').replace(' ', 'T')));

  if (items.length === 0) {
    wrap.innerHTML = `
      <div class="alerts-empty">
        <span class="e-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </span>
        <h3>No alert</h3>
        <p>${term || _alertsCurrentFilter !== 'all' ? 'No result for this filter.' : 'All quiet. The situation is stable across monitored zones.'}</p>
      </div>`;
    return;
  }

  // Grouper par date bucket
  const groupOrder = ['today', 'yesterday', 'week', 'older'];
  const groups = {};
  items.forEach(a => {
    const b = _dateBucket(a.created_at);
    if (!groups[b.key]) groups[b.key] = { label: b.label, items: [] };
    groups[b.key].items.push(a);
  });

  let html = '';
  groupOrder.forEach(gk => {
    const g = groups[gk];
    if (!g || g.items.length === 0) return;
    html += `
      <div class="alerts-group-head">
        <span>${g.label}</span>
        <span class="gh-count">${g.items.length}</span>
      </div>
    `;
    g.items.forEach(a => {
      const sev = a.severity || 'info';
      const id = String(a.id);
      const isExp = _alertsExpanded.has(id);
      const isCritical = sev === 'critical';
      const title = _stripAutoTagAlerts(a.title);
      const preview = (a.message || '').replace(/\s+/g, ' ').trim();
      const sevPillText = (SEV_LABELS[sev] || sev).toUpperCase();
      html += `
        <div class="alerts-row ${sev} ${isExp ? 'expanded' : ''}" data-id="${_esc(id)}">
          <div class="ar-bar"></div>
          <div class="ar-time">${_fmtTime(a.created_at)}</div>
          <div class="ar-sev-ico">${_sevIcon(sev)}</div>
          <div class="ar-body">
            <div class="ar-title">${_esc(title)}</div>
            ${preview ? `<div class="ar-preview">${_esc(preview)}</div>` : ''}
          </div>
          <div class="ar-zone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ${_esc(a.zone_name || '—')}
          </div>
          <span class="ar-pill">${sevPillText}</span>
          <span class="ar-chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        </div>
        <div class="alerts-detail" data-detail-id="${_esc(id)}">
          ${preview ? `<div class="ad-msg">${_esc(preview)}</div>` : '<div class="ad-msg" style="font-style:italic;color:#94a3b8">No additional message.</div>'}
          <div class="ad-meta">
            <span class="ad-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span>Zone: <strong>${_esc(a.zone_name || '—')}</strong></span>
            </span>
            <span class="ad-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
              <span>Type: <strong>${_esc(a.type || 'pollution')}</strong></span>
            </span>
            <span class="ad-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Detected: <strong>${GT.fmt.date(a.created_at)}</strong></span>
            </span>
            <span class="ad-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
              <span>Severity: <strong>${SEV_LABELS[sev] || sev}</strong></span>
            </span>
          </div>
          <div class="ad-actions">
            <button class="ad-btn primary" onclick="window.location.hash='#/map'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-6 2V6l6-2"/><path d="M21 18l-6 2V6l6-2z"/></svg>
              View on map
            </button>
            ${isCritical ? `
              <button class="ad-btn" onclick="window.location.hash='#/school'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 4 2 10l10 6 10-6z"/></svg>
                Open school mode
              </button>` : ''}
            <button class="ad-btn" onclick="window.location.hash='#/reports'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
              PDF Report
            </button>
            <button class="ad-btn" onclick="window.location.hash='#/symptoms'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9-6-18-3 9H2"/></svg>
              Report symptom
            </button>
          </div>
        </div>
      `;
    });
  });

  wrap.innerHTML = html;

  // Bind toggle expand sur chaque ligne
  wrap.querySelectorAll('.alerts-row').forEach(row => {
    row.addEventListener('click', (e) => {
      // Ne pas toggler si on a cliqué sur un bouton ailleurs
      if (e.target.closest('button')) return;
      const id = row.dataset.id;
      if (_alertsExpanded.has(id)) _alertsExpanded.delete(id);
      else _alertsExpanded.add(id);
      _renderInbox();
    });
  });
}

async function loadAlerts() {
  let data;
  try { data = await GT.api.get('alerts.php'); }
  catch (e) {
    const wrap = document.getElementById('alerts-list');
    if (wrap) wrap.innerHTML = '<div class="alerts-empty"><h3>Loading error</h3><p>Unable to reach the backend.</p></div>';
    return;
  }
  _alertsCache = data.alerts || [];
  const upd = document.getElementById('alerts-updated');
  if (upd) {
    const t = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
    upd.textContent = `${_alertsCache.length} alert${_alertsCache.length > 1 ? 's' : ''} · Updated ${t}`;
  }
  _renderPills(_alertsCache);
  _renderInbox();
}

async function initAlerts() {
  if (_alertsTimer) { clearInterval(_alertsTimer); _alertsTimer = null; }
  _alertsCurrentFilter = 'all';
  _alertsSearch = '';
  _alertsExpanded = new Set();

  document.querySelectorAll('.at-chip').forEach(b => {
    b.addEventListener('click', () => {
      _alertsCurrentFilter = b.dataset.filter || 'all';
      _refreshChips();
      _renderInbox();
      _renderPills(_alertsCache);
    });
  });

  const search = document.getElementById('alerts-search');
  if (search) {
    search.addEventListener('input', (e) => {
      _alertsSearch = e.target.value;
      _renderInbox();
    });
  }

  await loadAlerts();

  _alertsTimer = setInterval(() => {
    if (document.getElementById('alerts-list')) loadAlerts();
    else { clearInterval(_alertsTimer); _alertsTimer = null; }
  }, 20000);

  if (!_alertsListenerAttached) {
    document.addEventListener('gt:alerts-updated', () => {
      if (document.getElementById('alerts-list')) loadAlerts();
    });
    _alertsListenerAttached = true;
  }
}
