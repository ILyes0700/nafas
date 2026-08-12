/**
 * Deep Learning page (PART 5) — BiLSTM + Multi-Head Attention + XGBoost hybrid.
 * Data from /backend/api/deep-learning.php. Renders the DL comparison table,
 * per-zone multi-horizon predictions, actual-vs-predicted chart and a 24×24
 * attention heatmap rendered as a CSS grid.
 */
window.initDeepLearning = async function () {
  const API = '../backend/api';
  const NAVY = '#0d3b66', RED = '#dc2626';
  let seriesChart = null;

  const $ = (s) => document.querySelector(s);

  async function load() {
    let d;
    try {
      const r = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      d = await r.json();
    } catch (e) {
      $('#dl-table').querySelector('tbody').innerHTML =
        `<tr><td colspan="9" class="muted">Erreur : ${e.message}. Vérifiez le backend PHP (WAMP).</td></tr>`;
      return;
    }
    $('#dl-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    renderTable(d.models);
    renderVs(d.models);
    renderPredictions(d.predictions);
    renderSeries(d.series);
    renderAttention(d.attention);
  }

  function renderTable(models) {
    const tb = $('#dl-table').querySelector('tbody');
    if (!models || !models.length) { tb.innerHTML = '<tr><td colspan="9" class="muted">Aucune donnée.</td></tr>'; return; }
    let bestRmse = Math.min.apply(null, models.map(m => m.rmse));
    tb.innerHTML = models.map(m => `
      <tr class="${m.rmse === bestRmse ? 'sci-row-best' : ''}">
        <td><b>${m.rmse === bestRmse ? '✅ ' : ''}${m.name}</b></td>
        <td class="muted small">${m.params}</td>
        <td>${m.acc}</td><td class="${m.rmse === bestRmse ? 'cell-best' : ''}">${m.f1}</td>
        <td>${m.mae}</td><td class="${m.rmse === bestRmse ? 'cell-best' : ''}">${m.rmse}</td>
        <td>${m.r2}</td><td>${m.auc}</td><td class="muted">${m.latency}</td></tr>`).join('');
  }

  // Real LSTM vs BiLSTM head-to-head, derived from the real model metrics.
  function renderVs(models) {
    const box = $('#dl-vs');
    if (!box) return;
    if (!models || !models.length) { box.innerHTML = '<div class="muted">Aucune donnée.</div>'; return; }
    const lstm = models.find(m => /lstm/i.test(m.name) && !/bi/i.test(m.name));
    const bilstm = models.find(m => /bilstm\s*simple/i.test(m.name)) || models.find(m => /bilstm/i.test(m.name) && !/attn|attention/i.test(m.name));
    if (!lstm || !bilstm) {
      box.innerHTML = '<div class="muted">Comparaison indisponible : lancez l\'entraînement LSTM + BiLSTM (TensorFlow requis).</div>';
      return;
    }
    const row = (label, a, b, lowerBetter) => {
      const aw = lowerBetter ? (a <= b) : (a >= b);
      return `<tr><td>${label}</td>
        <td class="${aw ? 'cell-best' : ''}">${a}${aw ? ' ✅' : ''}</td>
        <td class="${!aw ? 'cell-best' : ''}">${b}${!aw ? ' ✅' : ''}</td></tr>`;
    };
    box.innerHTML = `
      <div class="table-wrap" style="margin-top:8px">
        <table class="basic-table sci-table">
          <thead><tr><th>Métrique</th><th>LSTM</th><th>BiLSTM</th></tr></thead>
          <tbody>
            ${row('RMSE (plus bas = mieux)', lstm.rmse, bilstm.rmse, true)}
            ${row('MAE (plus bas = mieux)', lstm.mae, bilstm.mae, true)}
            ${row('F1 (plus haut = mieux)', lstm.f1, bilstm.f1, false)}
            ${row('R² (plus haut = mieux)', lstm.r2, bilstm.r2, false)}
            ${row('AUC (plus haut = mieux)', lstm.auc, bilstm.auc, false)}
          </tbody>
        </table>
      </div>`;
  }

  function renderPredictions(preds) {
    const box = $('#dl-predictions');
    if (!preds || !preds.length) { box.innerHTML = '<div class="muted">Aucune prédiction.</div>'; return; }
    box.innerHTML = preds.map(p => `
      <div class="dl-pred-card">
        <div class="dl-pred-head">
          <b>${p.name}</b>
          <span class="muted small">${p.name_ar || ''}</span>
        </div>
        <div class="dl-pred-type muted small">${(p.type || '').replace(/_/g, ' ')}</div>
        <div class="forecast-horizons">
          ${p.horizons.map(h => `
            <div class="fh fh-${h.level}">
              <div class="fh-h">+${h.h}h</div>
              <div class="fh-v">${h.predicted}</div>
              <div class="fh-l muted small">${h.level} · ${Math.round(h.conf * 100)}%</div>
            </div>`).join('')}
        </div>
      </div>`).join('');
  }

  function renderSeries(s) {
    if (!s || typeof Chart === 'undefined') return;
    if (seriesChart) { try { seriesChart.destroy(); } catch (e) {} }
    seriesChart = new Chart($('#dl-series').getContext('2d'), {
      type: 'line',
      data: { labels: s.labels, datasets: [
        { label: 'Réel', data: s.actual, borderColor: NAVY, backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 },
        { label: 'Prédit (modèle optimal)', data: s.predicted, borderColor: RED, borderDash: [5, 4], backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 },
      ] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } }, title: { display: !!s.zone, text: s.zone ? `Zone ${s.zone} · meilleur modèle (FULL SYSTEM) · RMSE=${s.rmse}` : '', font: { size: 11 } } } },
    });
  }

  function renderAttention(att) {
    const box = $('#dl-attention');
    if (!att || !att.weights) { box.innerHTML = '<div class="muted">Indisponible.</div>'; return; }
    const w = att.weights;
    let max = -Infinity, min = Infinity;
    w.forEach(row => row.forEach(v => { if (v > max) max = v; if (v < min) min = v; }));
    // contrast-stretched colour scale (min -> max) so the REAL variation shows,
    // even when the attention weights are close together.
    const span = (max - min) || 1;
    const cell = (v) => {
      const t = (v - min) / span;
      const light = 92 - t * 62; // HSL lightness (clair = faible, sombre = fort)
      return `hsl(210, 65%, ${light}%)`;
    };
    let html = '<div class="attn-grid" style="grid-template-columns: 22px repeat(24, 1fr);">';
    html += '<div class="attn-corner"></div>';
    for (let j = 0; j < 24; j++) html += `<div class="attn-hdr">${j % 3 === 0 ? j : ''}</div>`;
    for (let i = 0; i < 24; i++) {
      html += `<div class="attn-rowhdr">${i % 3 === 0 ? i : ''}</div>`;
      for (let j = 0; j < 24; j++) {
        const v = w[i][j];
        html += `<div class="attn-cell" style="background:${cell(v)}" title="h${i}←h${j} : ${v}"></div>`;
      }
    }
    html += '</div>';
    html += '<div class="attn-legend muted small">Axe X = heures observées · Axe Y = heure prédite · plus sombre = plus influent</div>';
    box.innerHTML = html;
  }

  const btn = document.getElementById('dl-refresh');
  if (btn) btn.addEventListener('click', load);
  await load();
};
