/** Concept Drift + Auto-Optimization (PART 14 + 16). Data: /backend/api/drift.php */
window.initDrift = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d; try { const r = await fetch(`${API}/drift.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#dr-cur').textContent = 'Err'; return; }
    $('#dr-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    const s = d.stats; $('#dr-cur').textContent = s.current_drift; $('#dr-retr').textContent = s.retrainings; $('#dr-add').textContent = (s.features_added && s.features_added.length) ? s.features_added.join(', ') : '—'; $('#dr-rem').textContent = (s.features_removed && s.features_removed.length) ? s.features_removed.join(', ') : 'Aucune (toutes > 15% de corrélation)';
    kill();
    mk('dr-drift', { type: 'line', data: { labels: d.drift.labels, datasets: [{ label: 'Score de dérive', data: d.drift.score, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.06)', fill: true, pointRadius: 0, tension: 0.25 }, { label: 'Divergence KL', data: d.drift.kl, borderColor: '#7c3aed', pointRadius: 0, tension: 0.25 }, { label: 'Seuil (0.5)', data: d.drift.labels.map(() => d.drift.threshold), borderColor: '#dc2626', borderDash: [5, 4], pointRadius: 0 }] }, options: Object.assign({}, base, { scales: { x: { ticks: { maxTicksLimit: 12 } } } }) });
    mk('dr-opt', { type: 'line', data: { labels: d.optimization.cycles, datasets: [{ label: 'RMSE', data: d.optimization.rmse, borderColor: '#d97706', yAxisID: 'y', pointRadius: 2, tension: 0.2 }, { label: 'F1', data: d.optimization.f1, borderColor: '#16a34a', yAxisID: 'y1', pointRadius: 2, tension: 0.2 }] }, options: Object.assign({}, base, { plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } }, tooltip: { callbacks: { afterTitle: (items) => { const m = d.optimization.cycle_models; return (m && items.length && m[items[0].dataIndex]) ? m[items[0].dataIndex] : ''; } } } }, scales: { y: { position: 'left', title: { display: true, text: 'RMSE' } }, y1: { position: 'right', title: { display: true, text: 'F1' }, grid: { drawOnChartArea: false } } } }) });
    mk('dr-optuna', { type: 'line', data: { labels: d.optuna.map((_, i) => i + 1), datasets: [{ label: 'Meilleur RMSE', data: d.optuna, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.12)', fill: true, pointRadius: 0, tension: 0.2 }] }, options: Object.assign({}, base, { plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Essai Optuna' }, ticks: { maxTicksLimit: 10 } } } }) });
  }
  const b = document.getElementById('dr-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
