/** Granger Causality (PART 10). Data: /backend/api/granger.php */
window.initGranger = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };
  const pal = ['#0d3b66', '#2f6fb3', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#dc2626'];

  async function load() {
    let d; try { const r = await fetch(`${API}/granger.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#gr-table').querySelector('tbody').innerHTML = `<tr><td colspan="5" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#gr-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    $('#gr-table').querySelector('tbody').innerHTML = d.pairs.map(p => `<tr><td><b>${p.relation}</b></td><td>${p.best_lag}h</td><td>${p.f}</td><td>${p.p < 0.001 ? '&lt;0.001' : p.p}</td><td>${p.causal ? '<span class="pill safe"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>CAUSAL</span>' : '<span class="pill warning">non</span>'}</td></tr>`).join('');
    $('#gr-interp').innerHTML = `<div class=sci-best-head><span class=sci-best-check><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><div><div class=sci-best-title>Interprétation</div><div class=sci-best-name style="font-size:15px;font-weight:500">${d.interpretation}</div></div></div><div class=sci-best-comp><span class=sci-chip>${d.reference}</span></div>`;
    kill();
    const ds = d.pairs.map((p, i) => ({ label: p.relation, data: p.plags, borderColor: pal[i % pal.length], backgroundColor: 'transparent', pointRadius: 2, tension: 0.2 }));
    ds.push({ label: 'Seuil p=0.05', data: d.lags.map(() => 0.05), borderColor: '#dc2626', borderDash: [5, 4], pointRadius: 0 });
    mk('gr-lag', { type: 'line', data: { labels: d.lags.map(l => l + 'h'), datasets: ds }, options: Object.assign({}, base, { scales: { x: { title: { display: true, text: 'Lag (heures)' } }, y: { title: { display: true, text: 'p-value' } } }, plugins: { legend: { labels: { boxWidth: 10, font: { size: 9 } } } } }) });
  }
  const b = document.getElementById('gr-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
