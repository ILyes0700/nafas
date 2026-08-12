/* PART 48 — Digital Twin. Pure JS, no chart lib dependency (inline SVG bars).
 * Graceful: shows notes if the v6 table is missing. */
async function initDigitalTwin() {
  const API = (window.GT && GT.api && GT.api.base) ? GT.api.base : 'backend/api';
  const $ = id => document.getElementById(id);
  if (!$('dt-run')) return;

  function drawCurve(curve) {
    const box = $('dt-chart');
    if (!curve || !curve.length) { box.innerHTML = '<p class="dt-empty">Aucune donnée.</p>'; return; }
    const max = Math.max.apply(null, curve) || 1;
    const W = 640, H = 220, pad = 24;
    const step = (W - pad * 2) / Math.max(1, curve.length - 1);
    const pts = curve.map((v, i) => `${pad + i * step},${H - pad - (v / max) * (H - pad * 2)}`).join(' ');
    const color = max >= 70 ? '#e5484d' : (max >= 40 ? '#f5a524' : '#30a46c');
    box.innerHTML =
      `<svg viewBox="0 0 ${W} ${H}" class="dt-svg">` +
      `<polyline fill="none" stroke="${color}" stroke-width="2.5" points="${pts}"/>` +
      curve.map((v, i) => `<circle cx="${pad + i * step}" cy="${H - pad - (v / max) * (H - pad * 2)}" r="2.5" fill="${color}"/>`).join('') +
      `</svg><div class="dt-axis">+0h → +${curve.length - 1}h · max ${max}</div>`;
  }

  async function run() {
    const body = {
      scenario_name: $('dt-name').value,
      zone_id: +$('dt-zone').value,
      base_aqi: +$('dt-base').value,
      source_reduction_pct: +$('dt-red').value,
      wind_speed: +$('dt-wind').value,
      distance_to_source_m: +$('dt-dist').value,
      hours: +$('dt-hours').value,
    };
    $('dt-hint').textContent = 'Simulation…';
    try {
      const res = await fetch(`${API}/digital-twin.php`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
      });
      const j = await res.json();
      drawCurve(j.curve);
      $('dt-conf').textContent = j.confidence != null ? `confiance ${Math.round(j.confidence * 100)}%` : '';
      $('dt-hint').textContent = 'Terminé.';
      loadHistory();
    } catch (e) {
      $('dt-hint').textContent = 'Échec de la simulation.';
    }
  }

  async function loadHistory() {
    try {
      const res = await fetch(`${API}/digital-twin.php`, { credentials: 'same-origin' });
      const j = await res.json();
      const tb = $('dt-history');
      const rows = j.scenarios || [];
      tb.innerHTML = rows.length
        ? rows.map(r => `<tr><td>${r.scenario_name || '—'}</td><td>${r.zone_id || '—'}</td><td>${r.confidence != null ? Math.round(r.confidence * 100) + '%' : '—'}</td><td>${r.created_at || '—'}</td></tr>`).join('')
        : `<tr><td colspan="4">${j.note || 'Aucun scénario enregistré.'}</td></tr>`;
    } catch (e) { /* ignore */ }
  }

  $('dt-run').onclick = run;
  loadHistory();
}
window.initDigitalTwin = initDigitalTwin;
