/**
 * Master Comparison Dashboard (PART 18).
 * Pulls everything from /backend/api/comparison.php and renders the metric
 * tables + Chart.js visualisations. Matches the Nafass admin design system.
 */
window.initComparison = async function () {
  const API = '../backend/api';
  const charts = [];
  const NAVY = '#0d3b66', NAVY2 = '#2f6fb3', GREEN = '#16a34a', ORANGE = '#d97706', RED = '#dc2626';
  const palette = ['#0d3b66','#2f6fb3','#16a34a','#d97706','#7c3aed','#0891b2','#dc2626','#059669'];

  const $ = (s) => document.querySelector(s);
  const num = (v) => (v === null || v === undefined || v === '') ? '—' : v;

  function destroyCharts() { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; }
  function mkChart(id, cfg) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    charts.push(new Chart(el.getContext('2d'), cfg));
  }
  function hideCard(canvasId) {
    const el = document.getElementById(canvasId);
    const card = el && el.closest('.card');
    if (card) card.style.display = 'none';
  }
  const baseOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d;
    try {
      const r = await fetch(`${API}/comparison.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      d = await r.json();
    } catch (e) {
      $('#cmp-master').querySelector('tbody').innerHTML =
        `<tr><td colspan="12" class="muted">Erreur de chargement : ${e.message}. Vérifiez que le backend PHP tourne (WAMP).</td></tr>`;
      return;
    }

    $('#cmp-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    renderBest(d.best);
    renderMaster(d.master);
    renderHorizon(d.horizonModels, d.horizons);
    renderAblation(d.ablation);
    renderSig(d.significance);
    renderLit(d.literature, d.literatureNote);

    destroyCharts();
    renderBarCharts(d.master);
    renderDegrade(d.horizonModels, d.horizons);
    renderAblationChart(d.ablation);
    renderRadar(d.radar);
    renderOptuna(d.optuna);
    renderSeries(d.series);
  }

  function renderBest(b) {
    if (!b) return;
    $('#cmp-best').innerHTML = `
      <div class="sci-best-head">
        <span class="sci-best-check"><svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
        <div>
          <div class="sci-best-title">Système recommandé</div>
          <div class="sci-best-name">${b.name}</div>
        </div>
      </div>
      <div class="sci-best-metrics">
        <div class="sci-best-metric"><span>RMSE</span><b class="pos">-${b.vs_baseline.rmse}%</b></div>
        <div class="sci-best-metric"><span>F1</span><b class="pos">+${b.vs_baseline.f1}%</b></div>
        <div class="sci-best-metric"><span>AUC</span><b class="pos">+${b.vs_baseline.auc}%</b></div>
        ${(b.wilcoxon_p || b.wilcoxon_p === 0) ? `<div class="sci-best-metric"><span>Wilcoxon</span><b>${b.wilcoxon_p < 0.001 ? 'p&lt;0.001' : 'p=' + b.wilcoxon_p}</b></div>` : ''}
      </div>
      <div class="sci-best-comp">
        ${b.components.map(c => `<span class="sci-chip">✓ ${c}</span>`).join('')}
      </div>`;
  }

  function renderMaster(rows) {
    const tb = $('#cmp-master').querySelector('tbody');
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="12" class="muted">Aucune donnée.</td></tr>'; return; }
    tb.innerHTML = rows.map(m => `
      <tr class="${m.recommended ? 'sci-row-best' : ''}">
        <td>${m.recommended ? '<svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ' : ''}<b>${m.model}</b></td>
        <td>${num(m.acc)}</td><td>${num(m.prec)}</td><td>${num(m.rec)}</td>
        <td class="${m.best ? 'cell-best' : ''}">${num(m.f1)}</td>
        <td>${num(m.mae)}</td>
        <td class="${m.best ? 'cell-best' : ''}">${num(m.rmse)}</td>
        <td>${num(m.mape)}</td><td>${num(m.smape)}</td>
        <td>${num(m.r2)}</td><td>${num(m.auc)}</td>
        <td class="muted">${num(m.latency)}</td>
      </tr>`).join('');
  }

  function renderHorizon(models, hz) {
    const tb = $('#cmp-horizon').querySelector('tbody');
    if (!models || !hz) { tb.innerHTML = '<tr><td colspan="10" class="muted">Aucune donnée.</td></tr>'; return; }
    tb.innerHTML = models.map(name => {
      const c = (h) => (hz[h] && hz[h][name]) ? hz[h][name] : { rmse: '—', f1: '—', auc: '—' };
      const a = c('1h'), b = c('6h'), d = c('24h');
      return `<tr><td><b>${name}</b></td>
        <td>${a.rmse}</td><td>${a.f1}</td><td>${a.auc}</td>
        <td>${b.rmse}</td><td>${b.f1}</td><td>${b.auc}</td>
        <td>${d.rmse}</td><td>${d.f1}</td><td>${d.auc}</td></tr>`;
    }).join('');
  }

  function renderAblation(rows) {
    const tb = $('#cmp-ablation').querySelector('tbody');
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="7" class="muted">Aucune donnée.</td></tr>'; return; }
    const arrow = (v, up) => v === null ? '<span class="muted">—</span>'
      : `<span class="${(up ? v > 0 : v > 0) ? 'delta-pos' : 'delta-neg'}">${up ? '↑' : '↓'}${Math.abs(v)}%</span>`;
    tb.innerHTML = rows.map((r, i) => `
      <tr class="${i === rows.length - 1 ? 'sci-row-best' : ''}">
        <td>${i === rows.length - 1 ? '<svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ' : ''}<b>${r.config}</b></td>
        <td>${r.rmse}</td><td>${arrow(r.delta_rmse, false)}</td>
        <td>${r.f1}</td><td>${arrow(r.delta_f1, true)}</td>
        <td>${r.r2}</td><td>${r.auc}</td></tr>`).join('');
  }

  function renderSig(rows) {
    const tb = $('#cmp-sig').querySelector('tbody');
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="4" class="muted">Aucune donnée.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `
      <tr><td>${r.comparison}</td><td class="muted">${r.stat}</td>
      <td>${r.wilcoxon_p < 0.001 ? '&lt;0.001' : r.wilcoxon_p}</td>
      <td>${r.significant ? '<span class="pill safe">Oui <svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>' : '<span class="pill warning">Non</span>'}</td></tr>`).join('');
  }

  function renderLit(rows, note) {
    const tb = $('#cmp-lit').querySelector('tbody');
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="5" class="muted">Aucune donnée.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `
      <tr class="${r.ours ? 'sci-row-best' : ''}">
        <td>${r.ours ? '<svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> ' : ''}<b>${r.study}${!r.ours ? ' *' : ''}</b>${r.note ? `<div class="muted small">${r.note}</div>` : ''}${r.doi ? `<div class="muted small">DOI : ${r.doi}</div>` : ''}</td><td>${r.year}</td>
        <td class="muted small">${r.method}</td><td>${num(r.rmse)}</td><td>${num(r.f1)}</td></tr>`).join('');
    if (note) {
      const c = $('#cmp-lit').closest('.card') || $('#cmp-lit').parentElement;
      let n = c.querySelector('.cmp-lit-note');
      if (!n) { n = document.createElement('div'); n.className = 'cmp-lit-note muted small'; n.style.marginTop = '8px'; c.appendChild(n); }
      n.textContent = '* ' + note;
    }
  }

  function renderBarCharts(m) {
    if (!m) return;
    const labels = m.map(x => x.model);
    const short = labels.map(l => l.replace('BiLSTM', 'BiL').replace(' Baseline', ''));
    mkChart('cmp-f1', { type: 'bar', data: { labels: short, datasets: [{ label: 'F1', data: m.map(x => x.f1), backgroundColor: NAVY }] },
      options: Object.assign({}, baseOpts, { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, suggestedMin: 0.6 } } }) });
    mkChart('cmp-rmse', { type: 'bar', data: { labels: short, datasets: [{ label: 'RMSE', data: m.map(x => x.rmse), backgroundColor: ORANGE }] },
      options: Object.assign({}, baseOpts, { plugins: { legend: { display: false } } }) });
    mkChart('cmp-lat', { type: 'bar', data: { labels: short, datasets: [{ label: 'Latence (ms)', data: m.map(x => x.latency), backgroundColor: NAVY2 }] },
      options: Object.assign({}, baseOpts, { plugins: { legend: { display: false } } }) });
  }

  function renderDegrade(models, hz) {
    if (!models || !hz) return;
    const hs = ['1h', '6h', '24h'];
    const ds = models.map((name, i) => ({
      label: name.replace('BiLSTM', 'BiL'),
      data: hs.map(h => (hz[h] && hz[h][name]) ? hz[h][name].f1 : null),
      borderColor: palette[i % palette.length], backgroundColor: 'transparent', tension: 0.3, fill: false,
    }));
    mkChart('cmp-degrade', { type: 'line', data: { labels: ['+1h', '+6h', '+24h'], datasets: ds },
      options: Object.assign({}, baseOpts, { plugins: { title: { display: true, text: 'Dégradation du F1 selon l\'horizon' }, legend: { labels: { boxWidth: 10, font: { size: 10 } } } } }) });
  }

  function renderAblationChart(rows) {
    if (!rows) return;
    mkChart('cmp-ablation-chart', { type: 'bar', data: { labels: rows.map(r => r.config), datasets: [{ label: 'RMSE', data: rows.map(r => r.rmse), backgroundColor: rows.map((r, i) => i === rows.length - 1 ? GREEN : NAVY) }] },
      options: Object.assign({}, baseOpts, { indexAxis: 'y', plugins: { legend: { display: false }, title: { display: true, text: 'RMSE décroissant à chaque composant ajouté' } } }) });
  }

  function renderRadar(r) {
    if (!r) return;
    mkChart('cmp-radar', { type: 'radar', data: { labels: r.axes, datasets: r.models.map((m, i) => ({ label: m.name.replace('BiLSTM', 'BiL'), data: m.values, borderColor: palette[i], backgroundColor: palette[i] + '22' })) },
      options: Object.assign({}, baseOpts, { scales: { r: { suggestedMin: 0, suggestedMax: 100 } } }) });
  }

  function renderOptuna(o) {
    if (!o || !o.length) { hideCard('cmp-optuna'); return; }
    mkChart('cmp-optuna', { type: 'line', data: { labels: o.map(x => x.trial), datasets: [{ label: 'Meilleur RMSE', data: o.map(x => x.best_rmse), borderColor: NAVY, backgroundColor: NAVY + '18', fill: true, tension: 0.2, pointRadius: 0 }] },
      options: Object.assign({}, baseOpts, { plugins: { legend: { display: false }, title: { display: true, text: '100 essais Optuna' } }, scales: { x: { title: { display: true, text: 'Essai' } } } }) });
  }

  function renderSeries(s) {
    if (!s || !s.labels || !s.labels.length) { hideCard('cmp-series'); return; }
    mkChart('cmp-series', { type: 'line', data: { labels: s.labels, datasets: [
      { label: 'Réel', data: s.actual, borderColor: NAVY, backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 },
      { label: 'Prédit', data: s.predicted, borderColor: RED, backgroundColor: 'transparent', pointRadius: 0, borderDash: [5, 4], tension: 0.25 },
      { label: 'IC sup.', data: s.upper, borderColor: 'transparent', backgroundColor: 'rgba(220,38,38,.08)', pointRadius: 0, fill: '+1' },
      { label: 'IC inf.', data: s.lower, borderColor: 'transparent', backgroundColor: 'rgba(220,38,38,.08)', pointRadius: 0, fill: false },
    ] }, options: Object.assign({}, baseOpts, { plugins: { legend: { labels: { filter: (l) => !l.text.startsWith('IC') } } } }) });
  }

  const btn = document.getElementById('cmp-refresh');
  if (btn) btn.addEventListener('click', load);
  await load();
};
