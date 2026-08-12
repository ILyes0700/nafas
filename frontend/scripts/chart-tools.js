/* ============================================================================
 * chart-tools.js — Barre d'outils PRO sur CHAQUE graphique (complément du BI).
 *
 * N'EFFACE PAS le Business Intelligence (chart-insight.js) : c'est un module
 * séparé qui ajoute, en haut de chaque graphique, une barre d'actions :
 *   • Export PNG      (image réelle du canvas)
 *   • Export CSV      (vraies données du graphique : labels + datasets)
 *   • Copier tableau  (presse-papiers, format tableur)
 *   • Plein écran      (agrandir le graphique dans une modale)
 *
 * « NO FAKE » : les exports proviennent UNIQUEMENT des données réelles du
 * graphique (Chart.getChart). Aucune valeur inventée.
 * S'applique à TOUS les graphiques de toutes les pages.
 * ========================================================================== */
(function () {
  'use strict';

  function I(p) { return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; }
  var IC = {
    png: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    csv: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15l3 3 3-3"/>',
    copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    full: '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/>',
    close: '<path d="M18 6L6 18M6 6l12 12"/>'
  };

  function injectCss() {
    if (document.getElementById('cxt-style')) return;
    var css = [
      '.cxt-bar{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:10px 0 2px;padding:7px 10px;border:1px solid #e5e9f0;border-radius:12px;background:linear-gradient(120deg,#f7faff,#eef4fb)}',
      '.cxt-bar .cxt-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#5b6b82;margin-right:2px;display:inline-flex;align-items:center;gap:6px}',
      '.cxt-bar .cxt-lbl svg{color:#1d4e89}',
      '.cxt-btn{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border:1px solid #d5deea;border-radius:9px;background:#fff;color:#0d3b66;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}',
      '.cxt-btn:hover{background:#0d3b66;color:#fff;border-color:#0d3b66;transform:translateY(-1px);box-shadow:0 3px 9px rgba(13,59,102,.22)}',
      '.cxt-btn svg{flex:0 0 auto}',
      '.cxt-spacer{flex:1 1 auto}',
      '.cxt-meta{font-size:11px;color:#64748b;font-weight:600}',
      '.cxt-ok{color:#15803d!important;border-color:#86efac!important;background:#f0fdf4!important}',
      '.cxt-modal{position:fixed;inset:0;z-index:9999;background:rgba(9,20,38,.72);display:flex;align-items:center;justify-content:center;padding:24px;animation:cxtFade .2s ease}',
      '@keyframes cxtFade{from{opacity:0}to{opacity:1}}',
      '.cxt-modal-box{background:#fff;border-radius:16px;width:min(1050px,96vw);max-height:92vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 70px rgba(0,0,0,.4)}',
      '.cxt-modal-hd{display:flex;align-items:center;gap:10px;padding:13px 16px;background:linear-gradient(120deg,#0d3b66,#1d4e89);color:#fff;font-weight:700;font-size:14px}',
      '.cxt-modal-hd .cxt-x{margin-left:auto;background:rgba(255,255,255,.16);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}',
      '.cxt-modal-hd .cxt-x:hover{background:rgba(255,255,255,.3)}',
      '.cxt-modal-body{padding:18px;background:#fff;overflow:auto}',
      '.cxt-modal-body canvas{max-width:100%!important;height:auto!important}',
      '.pf2-dark .cxt-bar,.df-dark .cxt-bar{background:#2b2c2d;border-color:#3a3b3c}',
      '.pf2-dark .cxt-btn{background:#3a3b3c;color:#e7e9ee;border-color:#4a4b4d}',
      '.pf2-dark .cxt-modal-box{background:#242526}',
      '.pf2-dark .cxt-modal-body{background:#242526}'
    ].join('');
    var st = document.createElement('style');
    st.id = 'cxt-style'; st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  function toName(chart, ext) {
    var t = '';
    try { t = (chart.options && chart.options.plugins && chart.options.plugins.title && chart.options.plugins.title.text) || ''; } catch (e) {}
    if (Array.isArray(t)) t = t.join(' ');
    t = String(t || 'graphique').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'graphique';
    var d = new Date().toISOString().slice(0, 10);
    return 'gabes-' + t + '-' + d + '.' + ext;
  }

  function cell(v) {
    if (v == null) return '';
    if (typeof v === 'object') v = (v.y != null ? v.y : (v.v != null ? v.v : JSON.stringify(v)));
    v = String(v);
    return /[";,\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
  }

  // Construit une matrice [ [labelCol, ...datasetCols], ... ] depuis les VRAIES donnees
  function matrix(chart) {
    var labels = (chart.data && chart.data.labels) || [];
    var dss = (chart.data && chart.data.datasets) || [];
    var maxLen = labels.length;
    dss.forEach(function (d) { maxLen = Math.max(maxLen, (d.data || []).length); });
    var head = ['Label'];
    dss.forEach(function (d, i) { head.push(d.label || ('Série ' + (i + 1))); });
    var rows = [head];
    for (var r = 0; r < maxLen; r++) {
      var row = [labels[r] != null ? labels[r] : ('#' + (r + 1))];
      dss.forEach(function (d) {
        var v = (d.data || [])[r];
        if (v != null && typeof v === 'object') v = (v.y != null ? v.y : v.v);
        row.push(v == null ? '' : v);
      });
      rows.push(row);
    }
    return rows;
  }

  function download(name, mime, dataUrlOrText, isDataUrl) {
    var a = document.createElement('a');
    a.download = name;
    a.href = isDataUrl ? dataUrlOrText : ('data:' + mime + ';charset=utf-8,' + encodeURIComponent(dataUrlOrText));
    document.body.appendChild(a); a.click(); a.remove();
  }

  function flash(btn, txt) {
    var old = btn.innerHTML; btn.classList.add('cxt-ok'); btn.innerHTML = I('<path d="M20 6L9 17l-5-5"/>') + txt;
    setTimeout(function () { btn.classList.remove('cxt-ok'); btn.innerHTML = old; }, 1400);
  }

  function exportPng(chart) {
    var url;
    try { url = chart.toBase64Image ? chart.toBase64Image('image/png', 1) : chart.canvas.toDataURL('image/png'); }
    catch (e) { url = chart.canvas.toDataURL('image/png'); }
    download(toName(chart, 'png'), 'image/png', url, true);
  }
  function exportCsv(chart) {
    var m = matrix(chart);
    var csv = m.map(function (row) { return row.map(cell).join(';'); }).join('\r\n');
    download(toName(chart, 'csv'), 'text/csv', '\uFEFF' + csv, false);
  }
  function copyTable(chart, btn) {
    var m = matrix(chart);
    var tsv = m.map(function (row) { return row.map(function (v) { return v == null ? '' : String(v); }).join('\t'); }).join('\n');
    var done = function () { flash(btn, 'Copié'); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(tsv).then(done, done);
    else { try { var ta = document.createElement('textarea'); ta.value = tsv; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); } catch (e) {} done(); }
  }
  function fullscreen(chart) {
    var title = '';
    try { title = (chart.options.plugins.title && chart.options.plugins.title.text) || 'Graphique'; } catch (e) { title = 'Graphique'; }
    if (Array.isArray(title)) title = title.join(' ');
    var ov = document.createElement('div'); ov.className = 'cxt-modal';
    ov.innerHTML = '<div class="cxt-modal-box"><div class="cxt-modal-hd">' + I(IC.full) + '<span>' + String(title || 'Graphique') + '</span><button class="cxt-x" aria-label="Fermer">' + I(IC.close) + '</button></div><div class="cxt-modal-body"><img alt="" style="width:100%;border-radius:8px"></div></div>';
    var img = ov.querySelector('img');
    try { img.src = chart.toBase64Image ? chart.toBase64Image('image/png', 2) : chart.canvas.toDataURL('image/png'); } catch (e) { img.src = chart.canvas.toDataURL('image/png'); }
    function close() { ov.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
    ov.querySelector('.cxt-x').addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
  }

  function btn(icon, label, fn) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'cxt-btn'; b.innerHTML = I(icon) + label;
    b.addEventListener('click', function (e) { e.preventDefault(); fn(b); });
    return b;
  }

  function ensureBar(canvas, chart) {
    var id = canvas.id || (canvas.id = 'cxt-cv-' + Math.random().toString(36).slice(2, 8));
    var host = canvas.closest('.chart-wrap') || canvas.parentElement;
    if (!host) return;
    var existing = host.parentElement && host.parentElement.querySelector('.cxt-bar[data-for="' + id + '"]');
    // nb de points reels
    var pts = 0, ds = (chart.data && chart.data.datasets) || [];
    ds.forEach(function (d) { pts = Math.max(pts, (d.data || []).length); });
    if (pts < 1) { if (existing) existing.remove(); return; }
    if (existing) { existing.querySelector('.cxt-meta').textContent = pts + ' pts · ' + ds.length + ' série(s)'; return; }
    var bar = document.createElement('div');
    bar.className = 'cxt-bar'; bar.setAttribute('data-for', id);
    var lbl = document.createElement('span'); lbl.className = 'cxt-lbl';
    lbl.innerHTML = I('<path d="M4 4h16v16H4z"/><path d="M8 15v-3M12 15V9M16 15v-5"/>') + 'Outils graphique';
    bar.appendChild(lbl);
    bar.appendChild(btn(IC.png, 'PNG', function () { exportPng(chart); }));
    bar.appendChild(btn(IC.csv, 'CSV', function () { exportCsv(chart); }));
    bar.appendChild(btn(IC.copy, 'Copier', function (b) { copyTable(chart, b); }));
    bar.appendChild(btn(IC.full, 'Plein écran', function () { fullscreen(chart); }));
    var sp = document.createElement('span'); sp.className = 'cxt-spacer'; bar.appendChild(sp);
    var meta = document.createElement('span'); meta.className = 'cxt-meta'; meta.textContent = pts + ' pts · ' + ds.length + ' série(s)'; bar.appendChild(meta);
    // inserer AVANT le conteneur du graphique (le BI reste en dessous)
    if (host.parentElement) host.parentElement.insertBefore(bar, host);
  }

  function tick() {
    if (!window.Chart || !Chart.getChart) return;
    injectCss();
    var canvases = document.querySelectorAll('canvas');
    for (var i = 0; i < canvases.length; i++) {
      var cv = canvases[i];
      if (cv.offsetWidth === 0 && !cv.offsetParent) continue;
      var ch = null; try { ch = Chart.getChart(cv); } catch (e) { ch = null; }
      if (!ch) continue;
      try { ensureBar(cv, ch); } catch (e) { /* silencieux */ }
    }
  }

  function start() {
    setInterval(tick, 1300);
    window.addEventListener('hashchange', function () { setTimeout(tick, 400); });
    setTimeout(tick, 900);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
