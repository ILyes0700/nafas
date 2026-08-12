/** Adaptive Ensemble + Residual + Trust (PART 12 + 13). Data: /backend/api/ensemble.php */
window.initEnsemble = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const nz = (v) => (v === null || v === undefined || Math.abs(Number(v)) < 1e-9) ? '—' : v;
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };
  const dot = (c) => `<svg width="10" height="10" viewBox="0 0 12 12" style="vertical-align:middle;margin-right:4px" aria-hidden="true"><circle cx="6" cy="6" r="5" fill="${c}"/></svg>`;
  const trustPill = (t) => { const m = { HIGH: 'safe', MEDIUM: 'warning', LOW: 'danger' }; const e = { HIGH: dot('#16a34a'), MEDIUM: dot('#d97706'), LOW: dot('#dc2626') }; return `<span class="pill ${m[t]}">${e[t]} ${t}</span>`; };

  async function load() {
    let d; try { const r = await fetch(`${API}/ensemble.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#en-members').querySelector('tbody').innerHTML = `<tr><td colspan="7" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#en-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    $('#en-members').querySelector('tbody').innerHTML = d.members.map(m => `<tr><td><b>${m.model}</b></td><td>${nz(m.r2)}</td><td>${nz(m.f1)}</td><td>${m.rmse}</td><td class=muted>${m.lat}</td><td>${m.score}</td><td><b>${(m.weight * 100).toFixed(1)}%</b></td></tr>`).join('');
    $('#en-trust').querySelector('tbody').innerHTML = d.cities.map(c => `<tr><td><b>${c.name}</b></td><td>${c.pred}</td><td>[${c.lower}–${c.upper}]</td><td>${c.uncertainty}</td><td>${c.confidence}%</td><td>${trustPill(c.trust_level)} ${c.trust}</td></tr>`).join('');
    kill();
    mk('en-weights', { type: 'doughnut', data: { labels: d.members.map(m => m.model), datasets: [{ data: d.members.map(m => m.weight), backgroundColor: ['#93c5fd', '#2f6fb3', '#0d3b66', '#16a34a'] }] }, options: Object.assign({}, base, { plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }) });
    const rr = d.residual;
    mk('en-residual', { type: 'line', data: { labels: rr.hours, datasets: [{ label: 'Réel', data: rr.actual, borderColor: '#0d3b66', pointRadius: 0, tension: 0.3 }, { label: 'Ensemble', data: rr.ensemble, borderColor: '#9ca3af', borderDash: [4, 3], pointRadius: 0, tension: 0.3 }, { label: 'Après résiduel', data: rr.corrected, borderColor: '#16a34a', pointRadius: 0, tension: 0.3 }] }, options: Object.assign({}, base, { scales: { x: { title: { display: true, text: 'Heure' } } } }) });
  }
  const b = document.getElementById('en-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
