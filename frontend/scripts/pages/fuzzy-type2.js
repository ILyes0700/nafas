/** Fuzzy Logic Type-2 (PART 1). Data: /backend/api/fuzzy-type2.php */
window.initFuzzyType2 = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };
  const riskPill = (r) => { const m = { low: 'safe', moderate: 'warning', high: 'danger', critical: 'critical' }; return `<span class="pill ${m[r] || 'info'}">${r}</span>`; };

  async function load() {
    let d; try { const r = await fetch(`${API}/fuzzy-type2.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#fz-cities').querySelector('tbody').innerHTML = `<tr><td colspan="6" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#fz-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    const i = d.inputs; $('#fz-in-poll').textContent = i.pollution; $('#fz-in-vuln').textContent = i.vulnerability; $('#fz-in-symp').textContent = i.symptom_severity; $('#fz-in-alerts').textContent = i.alerts_24h;
    const s = d.score;
    $('#fz-score').innerHTML = `<div class=sci-best-head><span class=sci-best-check>\ud83e\udde0</span><div><div class=sci-best-title>fuzzy_score_type2 (feature cl\u00e9 ML/DL)</div><div class=sci-best-name>${s.fuzzy_score_type2} / 100 \u2014 risque ${s.risk_level}</div></div></div>`+
      `<div class=sci-best-metrics><div class=sci-best-metric><span>Incertitude inf.</span><b>${s.uncertainty_lower}</b></div><div class=sci-best-metric><span>Incertitude sup.</span><b>${s.uncertainty_upper}</b></div><div class=sci-best-metric><span>Bande</span><b>${s.uncertainty_band}</b></div></div>`;
    $('#fz-cities').querySelector('tbody').innerHTML = d.cities.map(c => `<tr><td><b>${c.name}</b> <span class="muted small">${c.name_ar}</span></td><td>${c.score}</td><td>${c.lower}</td><td>${c.upper}</td><td>${c.band}</td><td>${riskPill(c.risk)}</td></tr>`).join('');
    kill();
    const ds = [];
    d.mf.forEach(m => {
      ds.push({ label: m.set + ' UMF', data: m.umf, borderColor: m.color, backgroundColor: m.color + '22', pointRadius: 0, tension: 0.2, fill: '+1' });
      ds.push({ label: m.set + ' LMF', data: m.lmf, borderColor: m.color, borderDash: [4, 3], pointRadius: 0, tension: 0.2, fill: false });
    });
    mk('fz-mf', { type: 'line', data: { labels: d.x, datasets: ds }, options: Object.assign({}, base, { plugins: { legend: { labels: { boxWidth: 10, font: { size: 9 }, filter: (l) => l.text.includes('UMF') } } }, scales: { x: { title: { display: true, text: 'Pollution (0-100)' } } } }) });
    mk('fz-deg', { type: 'bar', data: { labels: d.degrees.map(x => x.set), datasets: [{ label: 'LMF', data: d.degrees.map(x => x.lmf), backgroundColor: '#93c5fd' }, { label: 'UMF', data: d.degrees.map(x => x.umf), backgroundColor: '#0d3b66' }] }, options: Object.assign({}, base, { scales: { y: { max: 1 } } }) });
  }
  const b = document.getElementById('fz-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
