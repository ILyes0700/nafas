/** Health Impact Index (PART 6). Data: /backend/api/health-impact.php */
window.initHealthImpact = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d; try { const r = await fetch(`${API}/health-impact.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#hi-table').querySelector('tbody').innerHTML = `<tr><td colspan="8" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#hi-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    $('#hi-cards').innerHTML = d.cities.map(c => `<div class=dl-pred-card><div class=dl-pred-head><b>${c.name}</b><span class=muted small>${c.name_ar}</span></div><div style="font-size:30px;font-weight:700;color:${c.color}">${c.score}</div><div style="display:inline-block;padding:3px 10px;border-radius:999px;background:${c.color}22;color:${c.color};font-weight:600;font-size:12px">${c.level}</div><div class="muted small" style="margin-top:6px">${c.recommendation}</div></div>`).join('');
    $('#hi-table').querySelector('tbody').innerHTML = d.cities.map(c => `<tr><td><b>${c.name}</b></td><td style="color:${c.color};font-weight:700">${c.score}</td><td><span style="color:${c.color};font-weight:600">${c.level}</span></td><td>${c.aqi}</td><td>${c.pm25}</td><td>${c.so2}</td><td>${c.vuln_pct}%</td><td class="muted small">${c.recommendation}</td></tr>`).join('');
    kill();
    mk('hi-trend', { type: 'line', data: { labels: d.trend.labels, datasets: [{ label: 'Moyenne 7 villes', data: d.trend.avg, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.06)', fill: true, pointRadius: 0, tension: 0.3 }, { label: 'Zone la plus exposée', data: d.trend.worst, borderColor: '#dc2626', pointRadius: 0, tension: 0.3 }] }, options: Object.assign({}, base, { scales: { x: { ticks: { maxTicksLimit: 12 } } } }) });
  }
  const b = document.getElementById('hi-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
