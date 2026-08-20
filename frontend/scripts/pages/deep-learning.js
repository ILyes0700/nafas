/**
 * Deep Learning page — classement des sept modèles autorisés sur quatre zones actives.
 * Data from /backend/api/deep-learning.php. Renders the train/validation/test table,
 * per-zone multi-horizon predictions, actual-vs-predicted chart and a 24×24
 * attention heatmap rendered as a CSS grid.
 */
window.initDeepLearning = async function () {
  const API = '../backend/api';
  const NAVY = '#0d3b66', RED = '#dc2626';
  let seriesChart = null;

  const $ = (s) => document.querySelector(s);
  const chartEmpty = (id, title, message) => {
    const canvas = document.getElementById(id);
    if (!canvas || !canvas.parentElement) return;
    const wrap = canvas.parentElement;
    let note = wrap.querySelector(`[data-empty-for="${id}"]`);
    if (!note) {
      note = document.createElement('div');
      note.className = 'chart-empty';
      note.dataset.emptyFor = id;
      wrap.appendChild(note);
    }
    note.innerHTML = `<strong>${title}</strong>${message}`;
    note.hidden = false;
    canvas.style.display = 'none';
  };
  const chartShow = (id) => {
    const canvas = document.getElementById(id);
    if (!canvas || !canvas.parentElement) return;
    canvas.style.display = '';
    const note = canvas.parentElement.querySelector(`[data-empty-for="${id}"]`);
    if (note) note.hidden = true;
  };

  async function load() {
    let d;
    try {
      const r = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      d = await r.json();
    } catch (e) {
      $('#dl-table').querySelector('tbody').innerHTML =
        `<tr><td colspan="11"><div class="cmp-empty-card"><div><strong>Erreur réelle du backend</strong>${e.message}. Vérifiez le journal PHP.</div></div></td></tr>`;
      renderVs([]);
      renderPredictions([]);
      renderSeries(null);
      renderAttention(null);
      return;
    }
    renderTable(d.models || []);
    renderVs(d.models);
    renderPredictions(d.predictions);
    renderSeries(d.series);
    renderAttention(d.attention);
  }

  function renderTable(models) {
    const tb = $('#dl-table').querySelector('tbody');
    if (!models || !models.length) {
      tb.innerHTML = '<tr><td colspan="11"><div class="cmp-empty-card"><div><strong>Aucun résultat DL réel</strong>Lancez l’entraînement sur les quatre zones actives.</div></div></td></tr>';
      return;
    }
    const esc = (v) => String(v == null ? '—' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    tb.innerHTML = models.map(m => `
      <tr>
        <td>${esc(m.city_id)}</td>
        <td>${esc(m.horizon)}</td>
        <td><b>${esc(m.model_name || m.name)}</b></td>
        <td>${esc(m.split)}</td>
        <td>${esc(m.acc)}</td>
        <td>${esc(m.f1)}</td>
        <td>${esc(m.mae)}</td>
        <td>${esc(m.rmse)}</td>
        <td>${esc(m.r2)}</td>
        <td>${esc(m.auc)}</td>
        <td class="muted">${esc(m.latency)}</td>
      </tr>`).join('');
  }

  // Classement comparatif réel : toutes les modèles autorisés, test final uniquement.
  function renderVs(models) {
    const box = $('#dl-vs');
    if (!box) return;
    const allowed = ['Random Forest', 'XGBoost + Fuzzy', 'LSTM', 'BiLSTM Simple', 'BiLSTM+MultiHead Attn', 'BiLSTM+AE', 'CNN+AE'];
    const rows = (models || []).filter(m => m.split === 'test' && allowed.includes(m.model_name || m.name));
    if (!rows.length) { box.innerHTML = '<div class="muted">Aucune métrique de test réelle disponible.</div>'; return; }
    const byHorizon = {};
    rows.forEach(m => {
      const h = m.horizon || '1h';
      byHorizon[h] = byHorizon[h] || [];
      byHorizon[h].push(m);
    });
    const esc = (v) => String(v == null ? '—' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const blocks = ['1h', '6h', '24h'].filter(h => byHorizon[h]).map(h => {
      const ranked = byHorizon[h].slice().sort((a, b) => Number(a.rmse) - Number(b.rmse));
      return `<h4 style="margin:12px 0 6px">Classement réel ${h}</h4><div class="table-wrap"><table class="basic-table sci-table"><thead><tr><th>Rang</th><th>Modèle</th><th>RMSE TEST</th><th>F1 TEST</th><th>R² TEST</th></tr></thead><tbody>${ranked.map((m, i) => `<tr class="${i === 0 ? 'sci-row-best' : ''}"><td>${i + 1}</td><td><b>${esc(m.model_name || m.name)}</b></td><td>${esc(m.rmse)}</td><td>${esc(m.f1)}</td><td>${esc(m.r2)}</td></tr>`).join('')}</tbody></table></div>`;
    }).join('');
    box.innerHTML = blocks || '<div class="muted">Aucun classement réel disponible.</div>';
  }

  function renderPredictions(preds) {
    const box = $('#dl-predictions');
    if (!preds || !preds.length) { box.innerHTML = '<div class="cmp-empty-card"><div><strong>Aucune prédiction réelle</strong>Les prédictions ne sont pas encore persistées par le pipeline.</div></div>'; return; }
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
              <div class="fh-l muted small">${h.level} · ${Math.round(h.conf * 100)}%<br><span class="forecast-model">${h.model || 'modèle sélectionné sur validation'}</span>${h.validation_rmse != null ? ` · RMSE val ${h.validation_rmse}` : ''}${h.test_rmse != null ? ` · RMSE test ${h.test_rmse}` : ''}</div>
            </div>`).join('')}
        </div>
      </div>`).join('');
  }

  function renderSeries(s) {
    if (seriesChart) { try { seriesChart.destroy(); } catch (e) {} seriesChart = null; }
    if (!s || !Array.isArray(s.labels) || !s.labels.length) {
      chartEmpty('dl-series', 'Série réelle indisponible', 'Aucune paire réel/prédit persistée pour le jeu de test.');
      return;
    }
    if (typeof Chart === 'undefined') {
      chartEmpty('dl-series', 'Graphique indisponible', 'Chart.js n’est pas chargé dans la page.');
      return;
    }
    chartShow('dl-series');
    seriesChart = new Chart($('#dl-series').getContext('2d'), {
      type: 'line',
      data: { labels: s.labels, datasets: [
        { label: 'Réel', data: s.actual, borderColor: NAVY, backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 },
        { label: 'Prédit (modèle optimal)', data: s.predicted, borderColor: RED, borderDash: [5, 4], backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 },
      ] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } }, title: { display: !!s.zone, text: s.zone ? `Zone ${s.zone} · modèle sélectionné sur validation (${s.model || '—'}) · RMSE test=${s.rmse}` : '', font: { size: 11 } } } },
    });
  }

  function renderAttention(att) {
    const box = $('#dl-attention');
    if (!att || !att.weights) { box.innerHTML = '<div class="cmp-empty-card"><div><strong>Attention indisponible</strong>Aucun artefact d’attention réel n’est disponible pour ce run.</div></div>'; return; }
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
    const rows = w.length;
    const cols = rows && Array.isArray(w[0]) ? w[0].length : 0;
    if (!rows || !cols) { box.innerHTML = '<div class="muted">Matrice d’attention vide.</div>'; return; }
    let html = `<div class="attn-grid" style="grid-template-columns: 22px repeat(${cols}, 1fr);">`;
    html += '<div class="attn-corner"></div>';
    for (let j = 0; j < cols; j++) html += `<div class="attn-hdr">${j % 6 === 0 ? j : ''}</div>`;
    for (let i = 0; i < rows; i++) {
      html += `<div class="attn-rowhdr">${i % 6 === 0 ? i : ''}</div>`;
      for (let j = 0; j < cols; j++) {
        const v = w[i][j];
        html += `<div class="attn-cell" style="background:${cell(v)}" title="h${i}←h${j} : ${v}"></div>`;
      }
    }
    html += '</div>';
    html += `<div class="attn-legend muted small">Axe X = heures observées · Axe Y = heure prédite · matrice ${rows}×${cols} · plus sombre = plus influent</div>`;
    box.innerHTML = html;
  }

  const btn = document.getElementById('dl-refresh');
  if (btn) btn.addEventListener('click', load);
  await load();
};
