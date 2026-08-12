/**
 * Anomaly Detection page (PART 5.5) — Autoencoder + Isolation Forest.
 * Data from /backend/api/anomaly.php. Renders header stats, an AQI timeline with
 * red anomaly markers, a reconstruction-error comparison, an anomaly-type pie and
 * the recent-events table.
 */
window.initAnomaly = async function () {
  const API = '../backend/api';
  const NAVY = '#0d3b66', RED = '#dc2626', GREEN = '#16a34a', ORANGE = '#d97706';
  const pie = ['#dc2626', '#d97706', '#7c3aed', '#0891b2', '#16a34a'];
  const charts = [];
  const $ = (s) => document.querySelector(s);

  function destroyCharts() { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; }
  function mkChart(id, cfg) {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    charts.push(new Chart(el.getContext('2d'), cfg));
  }
  const baseOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d;
    try {
      const r = await fetch(`${API}/anomaly.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      d = await r.json();
    } catch (e) {
      $('#an-events').querySelector('tbody').innerHTML =
        `<tr><td colspan="7" class="muted">Erreur : ${e.message}. Vérifiez le backend PHP (WAMP).</td></tr>`;
      return;
    }
    $('#an-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    const s = d.stats || {};
    $('#an-total').textContent = s.total ?? '—';
    $('#an-active').textContent = s.active ?? '—';
    $('#an-threshold').textContent = s.threshold ?? '—';
    $('#an-method').textContent = s.method ?? '—';
    renderEvents(d.events);
    destroyCharts();
    renderTimeline(d.timeline);
    renderRecon(d.recon);
    renderTypes(d.types);
  }

  function renderEvents(events) {
    const tb = $('#an-events').querySelector('tbody');
    if (!events || !events.length) { tb.innerHTML = '<tr><td colspan="7" class="muted">Aucun événement.</td></tr>'; return; }
    const badge = (t) => {
      const cls = t.includes('industriel') ? 'danger' : (t.includes('sable') ? 'warning' : (t.includes('chimique') ? 'critical' : 'info'));
      return `<span class="pill ${cls}">${t}</span>`;
    };
    tb.innerHTML = events.map(e => `
      <tr>
        <td><b>${e.city}</b></td>
        <td class="muted small">${(GT.fmt ? GT.fmt.date(e.detected_at) : e.detected_at)}</td>
        <td>${e.aqi}</td>
        <td>${badge(e.type_label)}</td>
        <td>${e.score}</td>
        <td class="muted">${e.recon_error}</td>
        <td class="muted">${e.iso_score}</td>
      </tr>`).join('');
  }

  function renderTimeline(t) {
    if (!t) return;
    const anomIdx = {};
    (t.anomalies || []).forEach(a => { anomIdx[a.index] = a.value; });
    const pts = t.aqi.map((v, i) => anomIdx[i] !== undefined ? v : null);
    mkChart('an-timeline', {
      type: 'line',
      data: { labels: t.labels, datasets: [
        { label: 'AQI', data: t.aqi, borderColor: NAVY, backgroundColor: 'rgba(13,59,102,.06)', fill: true, pointRadius: 0, tension: 0.2 },
        { label: 'Anomalie', data: pts, borderColor: 'transparent', backgroundColor: RED, pointRadius: 6, pointHoverRadius: 8, showLine: false },
      ] },
      options: Object.assign({}, baseOpts, { scales: { x: { ticks: { maxTicksLimit: 14 } } } }),
    });
  }

  function renderRecon(r) {
    if (!r) return;
    const scatter = (arr, color) => arr.map((v, i) => ({ x: i, y: v }));
    mkChart('an-recon', {
      type: 'scatter',
      data: { datasets: [
        { label: 'Normal', data: scatter(r.normal), backgroundColor: GREEN, pointRadius: 3 },
        { label: 'Anomalie', data: scatter(r.anomaly), backgroundColor: RED, pointRadius: 4 },
      ] },
      options: Object.assign({}, baseOpts, {
        plugins: Object.assign({}, baseOpts.plugins, {
          annotation: undefined,
          title: { display: true, text: `Seuil = ${r.threshold} (z-score |µ/σ|)` },
        }),
        scales: { y: { title: { display: true, text: 'Score anomalie |z|' } }, x: { title: { display: true, text: 'échantillon' } } },
      }),
    });
  }

  function renderTypes(types) {
    if (!types || !types.length) return;
    mkChart('an-types', {
      type: 'doughnut',
      data: { labels: types.map(t => t.type), datasets: [{ data: types.map(t => t.count), backgroundColor: pie }] },
      options: Object.assign({}, baseOpts, { plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }),
    });
  }

  const btn = document.getElementById('an-refresh');
  if (btn) btn.addEventListener('click', load);
  await load();
};
