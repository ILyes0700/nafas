/* A2 — Pollution↔symptoms correlation engine view. */

(function () {
  let _detailChart = null;

  function rColor(r) {
    const a = Math.abs(r);
    if (a >= 0.7) return '#dc2626';
    if (a >= 0.4) return '#f59e0b';
    if (a >= 0.2) return '#0ea5e9';
    return '#9ca3af';
  }

  async function loadAll(days) {
    const r = await fetch(`../backend/api/correlation.php?days=${days}`, { credentials: 'same-origin' });
    if (!r.ok) return null;
    return r.json();
  }

  async function loadDetail(zoneId, days) {
    const r = await fetch(`../backend/api/correlation.php?zone_id=${zoneId}&days=${days}`, { credentials: 'same-origin' });
    if (!r.ok) return null;
    return r.json();
  }

  function renderList(data) {
    const list = document.getElementById('corr-zones-list');
    if (!list) return;
    list.innerHTML = '';
    if (!data || !data.ok || !data.zones || data.zones.length === 0) {
      list.innerHTML = '<p class="muted">Not enough data yet to analyze.</p>';
      return;
    }
    data.zones.forEach(z => {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'corr-zone-card';
      card.dataset.zoneId = z.zone.id;
      const pct = Math.round(Math.abs(z.r) * 100);
      card.innerHTML = `
        <header>
          <strong>${z.zone.name}</strong>
          <span class="badge" style="background:${rColor(z.r)}">r = ${z.r.toFixed(2)} · ${z.label}</span>
        </header>
        <div class="corr-bar"><div class="corr-bar-fill" style="width:${pct}%; background:${rColor(z.r)}"></div></div>
        <p class="corr-zone-text">${z.trend}</p>`;
      card.addEventListener('click', () => {
        document.querySelectorAll('.corr-zone-card').forEach(c => c.classList.remove('is-active'));
        card.classList.add('is-active');
        showDetail(z.zone.id);
      });
      list.appendChild(card);
    });
  }

  async function showDetail(zoneId) {
    const days = Number((document.getElementById('corr-days') || {}).value || 30);
    const data = await loadDetail(zoneId, days);
    if (!data || !data.ok) return;
    const card = document.getElementById('corr-detail-card');
    if (!card) return;
    card.hidden = false;
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    document.getElementById('corr-detail-title').textContent =
      `Details: ${data.zone.name} (${data.analysis.days} days)`;
    document.getElementById('corr-detail-trend').textContent = data.analysis.trend;

    const stats = document.getElementById('corr-detail-stats');
    if (stats) {
      const ex = (data.analysis.examples || [])
        .map(e => `${e.date}: pollution ${e.pollution}, symptoms ${e.symptoms}`).join(' — ');
      stats.textContent = `r=${data.analysis.r}, |r|=${data.analysis.abs_r}, n=${data.analysis.n_points}` +
                          (ex ? `\nKey dates: ${ex}` : '');
    }

    const c = document.getElementById('corr-detail-chart');
    if (c && window.Chart) {
      if (_detailChart) { _detailChart.destroy(); _detailChart = null; }
      _detailChart = new Chart(c, {
        type: 'line',
        data: {
          labels: data.analysis.days_axis,
          datasets: [
            { label: 'Pollution',        data: data.analysis.x_pollution, yAxisID: 'y',  borderColor: '#0ea5e9', backgroundColor: '#0ea5e933', tension: 0.25 },
            { label: 'Symptoms (per day)', data: data.analysis.y_symptoms, yAxisID: 'y2', borderColor: '#dc2626', backgroundColor: '#dc262633', tension: 0.25 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: {
            y:  { type: 'linear', position: 'left',  title: { display: true, text: 'Pollution (0-100)' } },
            y2: { type: 'linear', position: 'right', title: { display: true, text: 'Symptom count' }, grid: { drawOnChartArea: false } },
          },
        },
      });
    }
  }

  async function refresh() {
    const days = Number((document.getElementById('corr-days') || {}).value || 30);
    const data = await loadAll(days);
    renderList(data);
    const card = document.getElementById('corr-detail-card');
    if (card) card.hidden = true;
  }

  window.initCorrelation = function () {
    const sel = document.getElementById('corr-days');
    if (sel) sel.addEventListener('change', refresh);
    const btn = document.getElementById('corr-refresh');
    if (btn) btn.addEventListener('click', refresh);
    refresh();
  };
})();
