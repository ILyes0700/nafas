/** Spatial Propagation (PART 15). Data: /backend/api/spatial.php */
window.initSpatial = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };
  const dirName = (d) => { const n = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO']; return n[Math.round(d / 45) % 8]; };

  async function load() {
    let d; try { const r = await fetch(`${API}/spatial.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#sp-edges').querySelector('tbody').innerHTML = `<tr><td colspan="5" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#sp-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    $('#sp-wind').textContent = `${dirName(d.wind.direction)} (${d.wind.direction}°) · ${d.wind.speed} km/h`;
    $('#sp-edges').querySelector('tbody').innerHTML = d.edges.length ? d.edges.map(e => `<tr><td><b>${e.from}</b></td><td>${e.to}</td><td>${e.alignment}</td><td>${e.distance_km}</td><td style="font-weight:600">+${e.contribution}</td></tr>`).join('') : '<tr><td colspan=5 class=muted>Aucune propagation significative avec le vent actuel.</td></tr>';
    kill();
    mk('sp-adj', { type: 'bar', data: { labels: d.adjusted.map(a => a.name), datasets: [{ label: 'AQI local', data: d.adjusted.map(a => a.local_aqi), backgroundColor: '#93c5fd' }, { label: 'AQI ajusté (propagation)', data: d.adjusted.map(a => a.adjusted_aqi), backgroundColor: '#0d3b66' }] }, options: Object.assign({}, base, {}) });
  }
  const b = document.getElementById('sp-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
