/* Centre de rapports — preview iframe + impression + téléchargement.
   Le PDF est en réalité une page HTML imprimable :
   - Aperçu : iframe inline → pas de popup bloquée
   - Imprimer : appelle iframe.contentWindow.print() (Ctrl+P natif → Save as PDF)
   - Télécharger : ?download=1 force Content-Disposition: attachment */

const _PERIOD_META = {
  daily:   { title: 'Daily Report',   ar: 'تقرير يومي'    },
  weekly:  { title: 'Weekly Report',  ar: 'تقرير أسبوعي'  },
  monthly: { title: 'Monthly Summary', ar: 'تقرير شهري'    },
};

function _pdfUrl(period, opts = {}) {
  const params = new URLSearchParams({ period });
  if (opts.print)    params.set('print', '1');
  if (opts.download) params.set('download', '1');
  return '../backend/api/pdf.php?' + params.toString();
}

async function initReports() {
  // Date "now"
  const now = new Date();
  const nowEl = document.getElementById('rh-now');
  if (nowEl) nowEl.textContent = now.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });

  // Stats rapides
  let dash;
  try { dash = await GT.api.get('dashboard.php'); }
  catch (e) { dash = null; }

  const statsEl = document.getElementById('reports-stats');
  if (statsEl && dash) {
    const c = dash.counts || {};
    statsEl.innerHTML = `
      ${_statCard('info',   _ico('layers'),    c.zones    || 0, 'Monitored Zones')}
      ${_statCard('danger', _ico('bell'),      c.alerts   || 0, 'Alerts 24h')}
      ${_statCard('warn',   _ico('mail'),      c.reports  || 0, 'Reports 24h')}
      ${_statCard('green',  _ico('heart'),     c.symptoms || 0, 'Symptoms 24h')}
    `;
  }

  // Table zones
  let zonesData;
  try { zonesData = await GT.api.get('zones.php'); }
  catch (e) { zonesData = { zones: [] }; }

  const tb = document.getElementById('reports-zones-body');
  if (tb) {
    const zones = zonesData.zones || [];
    if (zones.length === 0) {
      tb.innerHTML = '<tr><td colspan="5" class="reports-empty">No zone.</td></tr>';
    } else {
      tb.innerHTML = zones.map(zn => {
        const cls = zn.status || 'safe';
        const polColor = ({ safe: '#16a34a', warning: '#d97706', danger: '#dc2626', critical: '#991b1b' })[cls] || '#0d3b66';
        return `
          <tr>
            <td>
              <div class="zname">${zn.name}</div>
              <div class="zname-ar">${zn.name_ar || ''}</div>
            </td>
            <td><span class="pill ${cls}">${cls}</span></td>
            <td>
              <span class="pol-bar">
                <span class="bar"><span style="width:${zn.pollution_level}%;background:${polColor}"></span></span>
                <b>${zn.pollution_level}%</b>
              </span>
            </td>
            <td>${zn.risk_score ?? '—'}/100</td>
            <td>${(+zn.population || 0).toLocaleString('en-US')}</td>
          </tr>`;
      }).join('');
    }
  }

  // Historique des rapports déjà générés
  await _loadReportsHistory();

  // Bind boutons preview / open
  document.querySelectorAll('[data-rep-action]').forEach(btn => {
    btn.addEventListener('click', () => {
      const action = btn.dataset.repAction;
      const period = btn.dataset.period;
      if (action === 'preview') _previewReport(period);
      else if (action === 'open') _openReport(period);
    });
  });

  // Boutons de la barre d'aperçu
  const printBtn = document.getElementById('rep-print');
  if (printBtn) printBtn.addEventListener('click', _printPreview);

  const dlBtn = document.getElementById('rep-download');
  if (dlBtn) dlBtn.addEventListener('click', _downloadPreview);

  const closeBtn = document.getElementById('rep-close');
  if (closeBtn) closeBtn.addEventListener('click', _closePreview);
}

function _statCard(cls, icoSvg, num, lbl) {
  return `
    <div class="rs-card ${cls}">
      <div class="rs-ico">${icoSvg}</div>
      <div>
        <div class="rs-num">${num}</div>
        <div class="rs-lbl">${lbl}</div>
      </div>
    </div>`;
}

function _ico(name) {
  const s = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">';
  switch (name) {
    case 'layers':   return s + '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>';
    case 'bell':     return s + '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>';
    case 'mail':     return s + '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
    case 'heart':    return s + '<path d="M22 12h-4l-3 9-6-18-3 9H2"/></svg>';
    case 'document': return s + '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>';
    default:         return s + '<circle cx="12" cy="12" r="9"/></svg>';
  }
}

let _currentPreviewPeriod = null;

function _previewReport(period) {
  _currentPreviewPeriod = period;
  const wrap = document.getElementById('report-preview');
  const iframe = document.getElementById('report-iframe');
  const title = document.getElementById('rpb-title');
  if (!wrap || !iframe) return;

  if (title && _PERIOD_META[period]) {
    title.textContent = `Preview — ${_PERIOD_META[period].title}`;
  }
  iframe.src = _pdfUrl(period);
  wrap.classList.add('show');
  setTimeout(() => wrap.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
}

function _openReport(period) {
  const url = _pdfUrl(period, { print: 0 });
  // 1) Tente window.open (autorisé par Electron setWindowOpenHandler pour localhost)
  const w = window.open(url, '_blank');
  // 2) Fallback : si bloqué (popup blocker, Electron sans handler), navigue dans la même fenêtre via location
  if (!w || w.closed || typeof w.closed === 'undefined') {
    window.location.href = url;
  }
}

// Sauvegarde une copie du rapport sur le serveur (dans /reports/saved/)
// Utilisé en // de l'impression / téléchargement local.
async function _archiveOnServer(period) {
  try {
    const res = await fetch('../backend/api/pdf-archive.php?save=1&period=' + encodeURIComponent(period), {
      method: 'GET',
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (data && data.ok) {
      _toast(`Report archived on server: ${data.filename}`, 'success');
    }
  } catch (e) {
    console.warn('Server archive failed', e);
  }
}

async function _printPreview() {
  if (!_currentPreviewPeriod) return;
  const iframe = document.getElementById('report-iframe');

  // En parallèle : archive une copie sur le serveur (dossier /reports/saved/)
  _archiveOnServer(_currentPreviewPeriod);

  // 🖥 Electron : utilise printToPDF natif (qualité parfaite, pas de dialog d'imprimante)
  if (window.electronAPI && typeof window.electronAPI.savePdf === 'function') {
    try {
      // URL absolue vers le PDF (le main process exige http://localhost)
      const absUrl = new URL(_pdfUrl(_currentPreviewPeriod), window.location.href).href;
      const meta = _PERIOD_META[_currentPreviewPeriod] || {};
      const ts = new Date().toISOString().slice(0, 10);
      const name = `nafass-${_currentPreviewPeriod}-${ts}.pdf`;
      const r = await window.electronAPI.savePdf({ url: absUrl, suggestedName: name });
      if (r && r.ok) {
        _toast(`PDF saved: ${r.path}`, 'success');
      } else if (r && r.canceled) {
        // no message if user cancels
      } else {
        _toast(`PDF error: ${(r && r.error) || 'unknown'}. Falling back to browser print.`, 'warn');
        _fallbackPrint(iframe);
      }
      return;
    } catch (e) {
      console.error('savePdf failed', e);
    }
  }

  // 🌐 Navigateur (Chrome/Firefox) : print() classique → dialog du navigateur (qui marche bien)
  _fallbackPrint(iframe);
}

function _fallbackPrint(iframe) {
  if (!iframe || !_currentPreviewPeriod) return;
  try {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
  } catch (e) {
    window.open(_pdfUrl(_currentPreviewPeriod, { print: 1 }), '_blank');
  }
}

function _toast(msg, type) {
  // Réutilise le toast global s'il existe
  if (window.GT && GT.toast) { GT.toast(msg, type); return; }
  // Sinon mini-toast
  const t = document.createElement('div');
  t.textContent = msg;
  t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#0d3b66;color:#fff;padding:12px 18px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.18);z-index:9999;font-size:13px;max-width:420px';
  if (type === 'warn') t.style.background = '#d97706';
  if (type === 'success') t.style.background = '#16a34a';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4500);
}

function _downloadPreview() {
  if (!_currentPreviewPeriod) return;
  // En parallèle : archive aussi sur le serveur
  _archiveOnServer(_currentPreviewPeriod);
  const url = _pdfUrl(_currentPreviewPeriod, { download: 1 });
  // Crée un lien temporaire pour déclencher le download
  const a = document.createElement('a');
  a.href = url;
  a.download = '';
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
  setTimeout(() => a.remove(), 1000);
}

function _closePreview() {
  const wrap = document.getElementById('report-preview');
  const iframe = document.getElementById('report-iframe');
  if (wrap) wrap.classList.remove('show');
  if (iframe) iframe.src = 'about:blank';
  _currentPreviewPeriod = null;
}

async function _loadReportsHistory() {
  const histEl = document.getElementById('reports-history');
  if (!histEl) return;
  try {
    const res = await GT.api.get('pdf.php?history=1');
    const items = res.history || [];
    if (items.length === 0) {
      histEl.innerHTML = '<div class="reports-empty">No report generated yet.</div>';
      return;
    }
    histEl.innerHTML = items.map(it => `
      <div class="reports-history-item">
        <div class="rhi-ico">${_ico('document')}</div>
        <div class="rhi-body">
          <div class="rhi-title">${it.title}</div>
          <div class="rhi-meta">${it.period || '—'} · ${GT.fmt.date(it.created_at)}</div>
        </div>
        <div class="rhi-actions">
          <button class="btn outline" onclick="_previewReport('${it.period}')">Preview</button>
        </div>
      </div>`).join('');
  } catch (e) {
    histEl.innerHTML = '<div class="reports-empty">History unavailable.</div>';
  }
}
