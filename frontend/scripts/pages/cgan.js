/** Conditional GAN (PART 2). Data: /backend/api/cgan.php */
window.initCgan = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d; try { const r = await fetch(`${API}/cgan.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#gn-aug').querySelector('tbody').innerHTML = `<tr><td colspan="3" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#gn-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    const m = d.metrics; $('#gn-cov').textContent = m.coverage_score; $('#gn-fd').textContent = m.frechet_distance; $('#gn-sim').textContent = m.distribution_similarity; $('#gn-ep').textContent = m.epochs;
    const aug = d.augmentation || [];
    $('#gn-aug').querySelector('tbody').innerHTML = aug.length ? aug.map((a) => `<tr><td><b>${a.data}</b></td><td>${a.n}</td><td>${a.fidelity}</td></tr>`).join('') : '<tr><td colspan="3" class="muted">Aucune donnée générée disponible.</td></tr>';
    kill();
    const hasLoss = d.loss && Array.isArray(d.loss.epochs) && d.loss.epochs.length > 0;
    const lossCard = document.getElementById('gn-loss-card'); if (lossCard) lossCard.style.display = hasLoss ? '' : 'none';
    if (hasLoss) mk('gn-loss', { type: 'line', data: { labels: d.loss.epochs, datasets: [{ label: 'Perte G', data: d.loss.g, borderColor: '#0d3b66', pointRadius: 0, tension: 0.2 }, { label: 'Perte D', data: d.loss.d, borderColor: '#dc2626', pointRadius: 0, tension: 0.2 }] }, options: Object.assign({}, base, { scales: { x: { title: { display: true, text: 'Epoch' }, ticks: { maxTicksLimit: 11 } } } }) });
    mk('gn-overlay', { type: 'line', data: { labels: d.overlay.hours, datasets: [{ label: '_lb', data: d.overlay.gen_lower, borderColor: 'transparent', pointRadius: 0, fill: false, tension: 0.3, spanGaps: true }, { label: 'Plage g\u00e9n\u00e9r\u00e9e (\u00b1\u00e9cart-type)', data: d.overlay.gen_upper, borderColor: 'transparent', backgroundColor: 'rgba(124,58,237,.13)', pointRadius: 0, fill: '-1', tension: 0.3, spanGaps: true }, { label: 'G\u00e9n\u00e9r\u00e9 (AE-CGAN) moy.', data: d.overlay.generated, borderColor: '#7c3aed', borderDash: [5, 4], pointRadius: 0, fill: false, tension: 0.3, spanGaps: true }, { label: 'R\u00e9el', data: d.overlay.real, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.06)', pointRadius: 0, fill: false, tension: 0.3, spanGaps: true }] }, options: Object.assign({}, base, { plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 }, filter: function (it) { return it.text !== '_lb'; } } } }, scales: { x: { title: { display: true, text: 'Heure (0-23)' } }, y: { title: { display: true, text: 'AQI' } } } }) });
  }
  const b = document.getElementById('gn-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
