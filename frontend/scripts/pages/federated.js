/** Federated Learning FedAvg (PART 20). Data: /backend/api/federated.php */
window.initFederated = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d; try { const r = await fetch(`${API}/federated.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#fd-table').querySelector('tbody').innerHTML = `<tr><td colspan="4" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#fd-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    const imp = d.improvement || { rmse: 0, f1: 0, r2: 0 };
    const rmseTxt = (imp.rmse >= 0 ? '-' : '+') + Math.abs(imp.rmse) + '%';
    const f1Txt = (imp.f1 >= 0 ? '+' : '') + imp.f1 + '%';
    const r2Txt = (imp.r2 >= 0 ? '+' : '') + imp.r2 + '%';
    const rmseCls = imp.rmse >= 0 ? 'pos' : 'neg';
    const f1Cls = imp.f1 >= 0 ? 'pos' : 'neg';
    const r2Cls = imp.r2 >= 0 ? 'pos' : 'neg';
    $('#fd-privacy').innerHTML = `<div class=sci-best-head><span class=sci-best-check><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span><div><div class=sci-best-title>Confidentialité préservée</div><div class=sci-best-name style="font-size:15px;font-weight:500">${d.privacy_note}</div></div></div><div class=sci-best-metrics><div class=sci-best-metric><span>Gain RMSE</span><b class=${rmseCls}>${rmseTxt}</b></div><div class=sci-best-metric><span>Gain F1</span><b class=${f1Cls}>${f1Txt}</b></div><div class=sci-best-metric><span>Gain R²</span><b class=${r2Cls}>${r2Txt}</b></div></div><div class=sci-best-comp><span class=sci-chip>${d.reference}</span></div>`;
    $('#fd-table').querySelector('tbody').innerHTML = d.comparison.map((c, i) => `<tr class="${i === 1 ? 'sci-row-best' : ''}"><td><b>${c.mode}</b></td><td>${c.rmse}</td><td>${c.f1}</td><td>${c.r2}</td></tr>`).join('');
    const exb = $('#fd-explain-body');
    if (exb) exb.textContent = d.explanation || '';
    const rd = $('#fd-rounds');
    if (rd && d.round_detail) rd.querySelector('tbody').innerHTML = d.round_detail.map(r => `<tr><td>Round ${r.round}</td><td>${r.rmse}</td><td>${r.f1}</td></tr>`).join('');
    kill();
    mk('fd-conv', { type: 'line', data: { labels: d.convergence.rounds, datasets: [{ label: 'RMSE global (baisse)', data: d.convergence.rmse, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.1)', fill: true, pointRadius: 2, tension: 0.2, yAxisID: 'y' }, { label: 'F1 global (monte)', data: d.convergence.f1 || [], borderColor: '#16a34a', backgroundColor: 'transparent', pointRadius: 2, tension: 0.2, yAxisID: 'y1' }] }, options: Object.assign({}, base, { plugins: { legend: { display: true } }, scales: { x: { title: { display: true, text: 'Round fédéré' } }, y: { position: 'left', title: { display: true, text: 'RMSE' } }, y1: { position: 'right', title: { display: true, text: 'F1' }, grid: { drawOnChartArea: false } } } }) });
    mk('fd-contrib', { type: 'bar', data: { labels: d.contribution.map(c => c.city), datasets: [{ label: 'Échantillons', data: d.contribution.map(c => c.samples), backgroundColor: '#2f6fb3' }] }, options: Object.assign({}, base, { plugins: { legend: { display: false } } }) });
  }
  const b = document.getElementById('fd-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
