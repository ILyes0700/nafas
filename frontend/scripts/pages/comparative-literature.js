/** Comparative literature (PART 11). Data: /backend/api/comparative-literature.php
 *  v4.0 : le nombre de contributions est calcule depuis la reponse au lieu
 *  d'etre code en dur ("11 contributions cles"), la liste ayant change avec
 *  la migration Open-Meteo.
 */
window.initComparativeLiterature = async function () {
  const API = '../backend/api'; let chart = null;
  const $ = (s) => document.querySelector(s);
  const nz = (v) => (v === null || v === undefined || v === '') ? '—' : v;
  async function load() {
    let d; try { const r = await fetch(`${API}/comparative-literature.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#lit-table').querySelector('tbody').innerHTML = `<tr><td colspan="6" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#lit-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    $('#lit-table').querySelector('tbody').innerHTML = d.studies.map(s => `<tr class="${s.ours ? 'sci-row-best' : ''}"><td>${s.ours ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;color:#d97706" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01z"/></svg>' : ''}<b>${s.study}${s.ours ? '' : ' *'}</b>${s.note ? `<div class="muted small">${s.note}</div>` : ''}${s.doi ? `<div class="muted small">DOI : ${s.doi}</div>` : ''}</td><td>${s.year}</td><td class="${s.ours ? 'cell-best' : ''}">${nz(s.rmse)}</td><td class="${s.ours ? 'cell-best' : ''}">${nz(s.f1)}</td><td>${s.loc}</td><td class="muted small">${s.method}</td></tr>`).join('');
    if (d.note) { const c = $('#lit-table').closest('.card') || $('#lit-table').parentElement; let n = c.querySelector('.lit-note'); if (!n) { n = document.createElement('div'); n.className = 'lit-note muted small'; n.style.marginTop = '8px'; c.appendChild(n); } n.textContent = '* ' + d.note; }
    const advs = Array.isArray(d.advantages) ? d.advantages : [];
    const advLabel = `${advs.length} contribution${advs.length > 1 ? 's' : ''} cl${advs.length > 1 ? 'és' : 'é'}`;
    $('#lit-adv').innerHTML = `<div class=sci-best-head><span class=sci-best-check><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg></span><div><div class=sci-best-title>Avantages vs littérature</div><div class=sci-best-name style="font-size:16px">${advLabel}</div></div></div><div class=sci-best-comp>${advs.map(a => `<span class=sci-chip>${a}</span>`).join('')}</div>`;
    if (chart) { try { chart.destroy(); } catch (e) {} }
    if (typeof Chart !== 'undefined') {
      chart = new Chart($('#lit-chart').getContext('2d'), { type: 'bar', data: { labels: d.studies.map(s => s.study.replace(' (Gabès, Tunisie)', '')), datasets: [{ label: 'RMSE', data: d.studies.map(s => s.rmse), backgroundColor: d.studies.map(s => s.ours ? '#16a34a' : '#0d3b66') }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } } });
    }
  }
  const b = document.getElementById('lit-refresh'); if (b) b.addEventListener('click', load);
  await load();
};