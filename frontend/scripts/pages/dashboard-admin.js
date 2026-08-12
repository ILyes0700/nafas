/* Panel Admin — réservé au rôle "admin".
   Charts via Chart.js v4 (chargé via CDN dans index.php).
   Palette restreinte : navy primary + 2 accents (or, vert) + rouge pour critique. */

const ADMIN_COLORS = {
  primary  : '#0d3b66',  // navy — barres / lignes principales
  accent1  : '#d97706',  // or / safran — warning / 2e série
  accent2  : '#16a34a',  // vert — safe / 3e série
  danger   : '#dc2626',  // rouge — UNIQUEMENT pour danger/critical
  gridLine : '#e5e7eb',
  text     : '#475569',
  textMute : '#94a3b8',
};

let _admCharts = {};
let _admInterval = null;

async function initDashboardAdmin() {
  // Garde côté frontend : si pas admin → on redirige
  if (!window.GT_USER || GT_USER.role !== 'admin') {
    document.getElementById('view').innerHTML =
      '<div class="card"><h3>Access denied</h3><div>This page is reserved to administrators.</div></div>';
    return;
  }

  // Cleanup ancien interval (si on revient sur la page)
  if (_admInterval) { clearInterval(_admInterval); _admInterval = null; }

  // C12 — astuce du jour (commune avec dashboard.html)
  _admLoadDailyTip();

  await _admLoad();

  // Auto-refresh toutes les 30s
  _admInterval = setInterval(_admLoad, 30000);

  // Bind boutons
  document.getElementById('adm-refresh')?.addEventListener('click', _admLoad);
  document.getElementById('tool-recompute')?.addEventListener('click', _admToolRecompute);
  document.getElementById('tool-purge')?.addEventListener('click', _admToolPurge);
  document.getElementById('adm-pdfs-refresh')?.addEventListener('click', _admLoadArchivedPdfs);
  document.getElementById('tool-reload-status')?.addEventListener('click', _admReloadStatus);

  // Cleanup sur changement de page
  window.addEventListener('hashchange', () => {
    if (!location.hash.includes('admin') && _admInterval) {
      clearInterval(_admInterval); _admInterval = null;
      Object.values(_admCharts).forEach(c => c && c.destroy && c.destroy());
      _admCharts = {};
    }
  }, { once: true });
}

async function _admLoad() {
  let res;
  try { res = await GT.api.get('admin-stats.php?action=summary'); }
  catch (e) { _admToast('Loading error: ' + e.message, 'warn'); return; }

  if (!res || !res.ok) { _admToast('Invalid backend response', 'warn'); return; }
  const d = res.data;

  // Date
  const nowEl = document.getElementById('ah-now');
  if (nowEl) nowEl.textContent = 'Updated: ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });

  // KPIs
  _renderKPIs(d);

  // Charts
  _renderActivityChart(d.timeseries);
  _renderSeverityChart(d.severity);
  _renderTopZonesChart(d.top_zones);
  _renderRolesChart(d.users);

  // Activité récente
  _renderRecent(d.recent);

  // DB stats
  _renderDbStats(d.db_stats);

  // Archive PDF
  _admLoadArchivedPdfs();
}

/* ----- Archive PDF (rapports enregistrés sur le serveur) ----- */
async function _admLoadArchivedPdfs() {
  const wrap  = document.getElementById('adm-pdfs-list');
  const count = document.getElementById('adm-pdfs-count');
  if (!wrap) return;

  wrap.innerHTML = '<div class="empty">Loading…</div>';

  let res;
  try {
    const r = await fetch('../backend/api/pdf-archive.php?list=1', { credentials: 'same-origin' });
    res = await r.json();
  } catch (e) {
    wrap.innerHTML = '<div class="empty">Unable to load archived reports.</div>';
    if (count) count.textContent = '—';
    return;
  }

  const items = (res && res.items) || [];
  if (count) count.textContent = items.length === 0 ? 'No report' : items.length + ' file(s)';

  if (items.length === 0) {
    wrap.innerHTML = '<div class="empty">No report generated yet. Go to the «Reports» page and click «Print» or «Download» to archive a report.</div>';
    return;
  }

  const periodLabel = {
    daily:   { lbl: 'Daily',     cls: 'green'  },
    weekly:  { lbl: 'Weekly',    cls: ''       },
    monthly: { lbl: 'Monthly',   cls: 'warn'   },
    custom:  { lbl: 'Custom',    cls: ''       },
  };

  wrap.innerHTML = items.map(it => {
    const p   = periodLabel[it.period] || periodLabel.custom;
    const url = '../backend/api/pdf-archive.php?file=' + encodeURIComponent(it.filename);
    const sizeKb = (it.size / 1024).toFixed(1);
    const dt = it.created_at ? GT.fmt.date(it.created_at) : '';
    const isPdf = it.ext === 'pdf';
    return `
      <div class="adm-pdf-row">
        <div class="adm-pdf-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <path d="M14 2v6h6"/>
          </svg>
        </div>
        <div class="adm-pdf-body">
          <div class="adm-pdf-title">${_esc(it.title)} <span class="pill ${p.cls}">${p.lbl}</span> ${isPdf ? '<span class="pill green">PDF</span>' : '<span class="pill">HTML</span>'}</div>
          <div class="adm-pdf-meta">${_esc(it.filename)} · ${sizeKb} KB · ${_esc(dt)}</div>
        </div>
        <div class="adm-pdf-actions">
          <a class="btn outline btn-sm" href="${url}&download=1" download>
            <svg class="btn-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Save
          </a>
          <a class="btn primary btn-sm" href="${url}&print=1" target="_blank" rel="noopener">
            <svg class="btn-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
          </a>
          <button class="btn ghost btn-sm" data-pdf-del="${_esc(it.filename)}" title="Delete">
            <svg class="btn-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
          </button>
        </div>
      </div>
    `;
  }).join('');

  // Bind delete buttons
  wrap.querySelectorAll('[data-pdf-del]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const file = btn.dataset.pdfDel;
      if (!confirm('Delete this report?\n' + file)) return;
      try {
        const r = await fetch('../backend/api/pdf-archive.php?delete=1&file=' + encodeURIComponent(file), {
          credentials: 'same-origin',
        });
        const data = await r.json();
        if (data && data.ok) {
          _admToast('Report deleted.', 'success');
          _admLoadArchivedPdfs();
        } else {
          _admToast('Delete error.', 'warn');
        }
      } catch (e) {
        _admToast('Network error.', 'warn');
      }
    });
  });
}

/* ----- KPIs ----- */
function _renderKPIs(d) {
  const wrap = document.getElementById('adm-kpis');
  if (!wrap) return;
  const u = d.users, c = d.counts;
  const cards = [
    { cls: '',       ico: 'users',    num: u.total,         lbl: 'Users' },
    { cls: '',       ico: 'shield',   num: u.admin,         lbl: 'Admins' },
    { cls: '',       ico: 'heart',    num: u.health,        lbl: 'Health Staff' },
    { cls: '',       ico: 'school',   num: u.school,        lbl: 'Schools' },
    { cls: 'green',  ico: 'user',     num: u.citizen,       lbl: 'Citizens' },
    { cls: 'danger', ico: 'bell',     num: c.alerts_30d,    lbl: 'Alerts 30d' },
    { cls: 'warn',   ico: 'mail',     num: c.reports_30d,   lbl: 'Reports 30d' },
    { cls: '',       ico: 'msg',      num: c.chatbot_30d,   lbl: 'Chatbot Messages 30d' },
  ];
  wrap.innerHTML = cards.map(k => `
    <div class="kpi ${k.cls}">
      <div class="ki">${_admIcon(k.ico)}</div>
      <div>
        <div class="knum">${(+k.num || 0).toLocaleString('en-US')}</div>
        <div class="klbl">${k.lbl}</div>
      </div>
    </div>`).join('');
}

/* ----- CHART : Activité 30j ----- */
function _renderActivityChart(ts) {
  const ctx = document.getElementById('chart-activity');
  if (!ctx || !window.Chart) return;
  if (_admCharts.activity) _admCharts.activity.destroy();

  const labels = (ts.labels || []).map(d => {
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
  });

  _admCharts.activity = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        _lineDS('Alerts',   ts.alerts   || [], ADMIN_COLORS.danger),
        _lineDS('Reports',  ts.reports  || [], ADMIN_COLORS.primary),
        _lineDS('Symptoms', ts.symptoms || [], ADMIN_COLORS.accent2),
      ],
    },
    options: _baseOpts({ legend: true, ticksY: true }),
  });
}

function _lineDS(label, data, color) {
  return {
    label, data,
    borderColor: color,
    backgroundColor: color + '22',
    tension: 0.32,
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 4,
    fill: true,
  };
}

/* ----- CHART : Sévérité (donut) ----- */
function _renderSeverityChart(sev) {
  const ctx = document.getElementById('chart-severity');
  if (!ctx || !window.Chart) return;
  if (_admCharts.severity) _admCharts.severity.destroy();

  const labels = ['Info', 'Warning', 'Danger', 'Critical'];
  const data   = [sev.info || 0, sev.warning || 0, sev.danger || 0, sev.critical || 0];
  const total  = data.reduce((a, b) => a + b, 0);

  _admCharts.severity = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: [ADMIN_COLORS.primary, ADMIN_COLORS.accent1, ADMIN_COLORS.danger, '#7f1d1d'],
        borderColor: '#fff', borderWidth: 2,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '62%',
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, color: ADMIN_COLORS.text, padding: 10, boxWidth: 10 } },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const v = ctx.parsed; const pct = total ? Math.round(v * 100 / total) : 0;
              return ` ${ctx.label} : ${v} (${pct}%)`;
            },
          },
        },
      },
    },
  });
}

/* ----- CHART : Top zones (barres horizontales) ----- */
function _renderTopZonesChart(zones) {
  const ctx = document.getElementById('chart-zones');
  if (!ctx || !window.Chart) return;
  if (_admCharts.zones) _admCharts.zones.destroy();

  const labels = (zones || []).map(z => z.name);
  const data   = (zones || []).map(z => +z.reports_count || 0);

  _admCharts.zones = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Reports 30d',
        data,
        backgroundColor: ADMIN_COLORS.primary,
        borderRadius: 6,
        borderSkipped: false,
        maxBarThickness: 28,
      }],
    },
    options: _baseOpts({ legend: false, ticksY: false, indexAxis: 'y' }),
  });
}

/* ----- CHART : Rôles ----- */
function _renderRolesChart(u) {
  const ctx = document.getElementById('chart-roles');
  if (!ctx || !window.Chart) return;
  if (_admCharts.roles) _admCharts.roles.destroy();

  _admCharts.roles = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Citizens', 'Health', 'Schools', 'Admins'],
      datasets: [{
        data: [u.citizen, u.health, u.school, u.admin],
        backgroundColor: [
          ADMIN_COLORS.accent2,
          ADMIN_COLORS.primary,
          ADMIN_COLORS.accent1,
          '#1e293b',
        ],
        borderRadius: 6,
        borderSkipped: false,
        maxBarThickness: 36,
      }],
    },
    options: _baseOpts({ legend: false, ticksY: true }),
  });
}

/* ----- options Chart.js communes ----- */
function _baseOpts({ legend = true, ticksY = true, indexAxis = 'x' } = {}) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: legend ? {
        position: 'top', align: 'end',
        labels: { font: { size: 11 }, color: ADMIN_COLORS.text, boxWidth: 10, usePointStyle: true, padding: 12 },
      } : { display: false },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 10, cornerRadius: 8,
        titleFont: { size: 11, weight: 600 }, bodyFont: { size: 12 },
      },
    },
    scales: {
      x: {
        grid: { color: ADMIN_COLORS.gridLine, drawBorder: false },
        ticks: { color: ADMIN_COLORS.textMute, font: { size: 10 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: ADMIN_COLORS.gridLine, drawBorder: false },
        ticks: { color: ADMIN_COLORS.textMute, font: { size: 10 }, display: ticksY, precision: 0 },
      },
    },
  };
}

/* ----- ACTIVITÉ RÉCENTE ----- */
function _renderRecent(r) {
  const reportsEl = document.getElementById('ac-reports');
  if (reportsEl) {
    const rows = r.reports || [];
    reportsEl.innerHTML = rows.length === 0
      ? '<div class="empty">No recent report.</div>'
      : rows.map(x => `
        <div class="ac-item">
          <div class="ai-body">
            <div class="ai-title">${_esc(x.citizen_name || 'Anonymous')} · <span style="color:#64748b">${_esc(x.category || '—')}</span></div>
            <div class="ai-meta">${_esc(x.zone_name || '—')} · ${GT.fmt.date(x.reported_at)}</div>
          </div>
        </div>`).join('');
  }

  const alertsEl = document.getElementById('ac-alerts');
  if (alertsEl) {
    const rows = r.alerts || [];
    alertsEl.innerHTML = rows.length === 0
      ? '<div class="empty">No recent alert.</div>'
      : rows.map(x => {
        const sev = x.severity || 'info';
        return `
          <div class="ac-item">
            <div class="ai-body">
              <div class="ai-title">${_esc(_cleanTitle(x.title || ''))}</div>
              <div class="ai-meta">${_esc(x.zone_name || '—')} · ${GT.fmt.date(x.created_at)}</div>
            </div>
            <span class="pill ${sev}">${sev}</span>
          </div>`;
      }).join('');
  }

  const usersEl = document.getElementById('ac-users');
  if (usersEl) {
    const rows = r.users || [];
    usersEl.innerHTML = rows.length === 0
      ? '<div class="empty">No user.</div>'
      : rows.map(x => `
        <div class="ac-item">
          <div class="ai-body">
            <div class="ai-title">${_esc(x.full_name || x.username)}</div>
            <div class="ai-meta">@${_esc(x.username)} · ${_esc(x.role)} · ${GT.fmt.date(x.created_at)}</div>
          </div>
          <span class="pill ${x.is_active ? 'safe' : 'warning'}">${x.is_active ? 'Active' : 'Inactive'}</span>
        </div>`).join('');
  }
}

/* ----- DB STATS ----- */
function _renderDbStats(stats) {
  const el = document.getElementById('db-stats');
  if (!el || !stats) return;
  el.innerHTML = Object.entries(stats).map(([t, n]) => `
    <div class="db-row">
      <span class="db-name">${t}</span>
      <span class="db-count">${n === null ? '—' : (+n).toLocaleString('en-US')}</span>
    </div>`).join('');
}

/* ----- TOOLS ----- */
async function _admToolRecompute() {
  const btn = document.getElementById('tool-recompute');
  if (btn) btn.disabled = true;
  try {
    const r = await GT.api.get('admin-stats.php?action=recompute_risk');
    _admToast(r.ok ? `Risk scores recomputed (${r.updated} zones).` : 'Recompute error.', r.ok ? 'success' : 'warn');
    await _admLoad();
  } catch (e) { _admToast('Error: ' + e.message, 'warn'); }
  finally { if (btn) btn.disabled = false; }
}

async function _admToolPurge() {
  if (!confirm('Delete auto-alerts [AUTO:*] older than 7 days?')) return;
  const btn = document.getElementById('tool-purge');
  if (btn) btn.disabled = true;
  try {
    const r = await GT.api.get('admin-stats.php?action=purge_auto_alerts');
    _admToast(r.ok ? `${r.deleted} alerts purged.` : 'Purge error.', r.ok ? 'success' : 'warn');
    await _admLoad();
  } catch (e) { _admToast('Error: ' + e.message, 'warn'); }
  finally { if (btn) btn.disabled = false; }
}

async function _admReloadStatus() {
  if (typeof refreshGlobalStatus === 'function') refreshGlobalStatus();
  _admToast('Global status refreshed.', 'success');
}

/* ----- HELPERS ----- */
function _admIcon(name) {
  const s = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">';
  switch (name) {
    case 'users':  return s + '<path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>';
    case 'user':   return s + '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    case 'shield': return s + '<path d="M12 2L4 5v7c0 5 3.5 9 8 10 4.5-1 8-5 8-10V5l-8-3z"/></svg>';
    case 'heart':  return s + '<path d="M22 12h-4l-3 9-6-18-3 9H2"/></svg>';
    case 'school': return s + '<path d="M22 10L12 4 2 10l10 6 10-6z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>';
    case 'bell':   return s + '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>';
    case 'mail':   return s + '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
    case 'msg':    return s + '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>';
    default:       return s + '<circle cx="12" cy="12" r="9"/></svg>';
  }
}

function _esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function _cleanTitle(t) {
  return String(t || '').replace(/^\s*\[AUTO:[^\]]+\]\s*/, '');
}

function _admToast(msg, type) {
  if (window.GT && GT.toast) { GT.toast(msg, type); return; }
  const t = document.createElement('div');
  t.textContent = msg;
  t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#0d3b66;color:#fff;padding:12px 18px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.18);z-index:9999;font-size:13px;max-width:420px';
  if (type === 'warn') t.style.background = '#d97706';
  if (type === 'success') t.style.background = '#16a34a';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

/* ============================================================
 * C12 — astuce du jour (admin dashboard)
 * ============================================================ */
async function _admLoadDailyTip() {
  const card = document.getElementById('dash-tip');
  const txt  = document.getElementById('dash-tip-text');
  const tag  = document.getElementById('dash-tip-tag');
  if (!card || !txt) return;
  try {
    const r = await fetch('../backend/api/tips.php?lang=en', { credentials: 'same-origin' });
    const data = await r.json();
    if (data.ok && data.tip) {
      card.hidden = false;
      txt.textContent = data.tip;
      if (tag) tag.textContent = data.cached ? 'AI · cache' : (data.fallback ? 'fallback' : 'AI · fresh');
    }
  } catch (_) {
    // silencieux — le fallback HTML reste affiché
  }
}
