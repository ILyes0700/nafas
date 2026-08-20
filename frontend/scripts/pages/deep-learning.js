/* Nafass — صفحة Deep Learning: بيانات حقيقية فقط، مفصولة حسب النموذج والمنطقة والـpartition. */
window.initDeepLearning = async function () {
  const API = '../backend/api';
  const NAVY = '#0d3b66';
  const RED = '#dc2626';
  const DL_MODELS = ['LSTM', 'BiLSTM Simple', 'BiLSTM+MultiHead Attn', 'BiLSTM+AE', 'CNN+AE'];
  const SPLITS = ['train', 'validation', 'test'];
  let seriesChart = null;
  let activeSplit = 'test';
  let lastRows = [];

  const $ = (s) => document.querySelector(s);
  const esc = (v) => String(v == null ? '—' : v)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const metric = (v, digits = 4) => {
    if (v === null || v === undefined || v === '' || !Number.isFinite(Number(v))) return '—';
    return Number(v).toFixed(digits).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
  };
  const accPercent = (value) => {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—';
    const n = Number(value);
    return `${metric(n < 1.5 ? n * 100 : n, 2)}%`;
  };
  const splitTitle = (split) => ({ train: 'Train (70 %)', validation: 'Validation (10 %)', test: 'Test final (20 %)' }[split] || split);
  const modelName = (row) => row?.model_name || row?.name || '—';

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
    note.innerHTML = `<strong>${esc(title)}</strong>${esc(message)}`;
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

  function ensureSplitFilter() {
    const table = $('#dl-table');
    if (!table) return null;
    let host = $('#dl-split-filter');
    if (!host) {
      host = document.createElement('div');
      host.id = 'dl-split-filter';
      host.className = 'sci-tabs dl-split-tabs';
      table.parentElement?.parentElement?.insertBefore(host, table.parentElement);
    }
    host.innerHTML = SPLITS.map((split) => `<button type="button" class="btn btn-ghost dl-split-btn${activeSplit === split ? ' is-active' : ''}" data-split="${split}">${splitTitle(split)}</button>`).join('');
    host.querySelectorAll('[data-split]').forEach((button) => button.addEventListener('click', () => {
      activeSplit = button.dataset.split || 'test';
      ensureSplitFilter();
      renderTable(lastRows);
    }));
    return host;
  }

  function renderTableCaption(rows) {
    const caption = $('#dl-table-caption');
    if (caption) caption.textContent = `Partition affichée : ${splitTitle(activeSplit)} · ${rows.length} ligne(s) réelle(s) · Acc normalisée en pourcentage.`;
  }

  function renderTable(models) {
    const table = $('#dl-table');
    const tb = table?.querySelector('tbody');
    if (!tb) return;
    lastRows = Array.isArray(models) ? models : [];
    const available = SPLITS.filter((split) => lastRows.some((row) => row.split === split));
    if (!available.includes(activeSplit)) activeSplit = available.includes('test') ? 'test' : (available[0] || 'test');
    ensureSplitFilter();
    const rows = lastRows.filter((row) => row.split === activeSplit);
    renderTableCaption(rows);
    if (!rows.length) {
      tb.innerHTML = `<tr><td colspan="11"><div class="cmp-empty-card"><div><strong>Aucune ligne DL réelle pour ${esc(splitTitle(activeSplit))}</strong>La base ne contient aucune métrique persistée pour cette partition.</div></div></td></tr>`;
      return;
    }
    tb.innerHTML = rows.map((m) => `<tr>
      <td>${esc(m.city_id || m.city || m.zone)}</td>
      <td>${esc(m.horizon)}</td>
      <td><b>${esc(modelName(m))}</b></td>
      <td><span class="split-pill split-${esc(m.split)}">${esc(splitTitle(m.split))}</span></td>
      <td>${accPercent(m.acc)}</td>
      <td>${metric(m.f1)}</td>
      <td>${metric(m.mae)}</td>
      <td>${metric(m.rmse)}</td>
      <td>${metric(m.r2)}</td>
      <td>${metric(m.auc)}</td>
      <td class="muted">${metric(m.latency, 2)}</td>
    </tr>`).join('');
  }

  function architectureStages(model) {
    const input = { title: 'Input', sub: '54 features causales · fenêtre réelle' };
    const output = { title: 'AQI t+h', sub: '+1 h / +6 h / +24 h' };
    const map = {
      'LSTM': [input, { title: 'LSTM', sub: 'séquence temporelle' }, { title: 'Dense', sub: 'régression AQI' }, output],
      'BiLSTM Simple': [input, { title: 'BiLSTM', sub: 'contexte bidirectionnel' }, { title: 'Dense', sub: 'régression AQI' }, output],
      'BiLSTM+MultiHead Attn': [input, { title: 'BiLSTM', sub: 'séquence temporelle' }, { title: 'Multi-Head Attention', sub: 'poids d’attention réels si artifact' }, { title: 'Dense', sub: 'régression AQI' }, output],
      'BiLSTM+AE': [input, { title: 'Encodeur LSTM', sub: 'compression' }, { title: 'Latent / AE', sub: 'représentation apprise' }, { title: 'BiLSTM', sub: 'prévision' }, output],
      'CNN+AE': [input, { title: 'Conv1D', sub: 'motifs locaux' }, { title: 'Latent / AE', sub: 'représentation apprise' }, { title: 'Dense', sub: 'prévision AQI' }, output],
    };
    return map[model] || [input, { title: model || 'Modèle DL', sub: 'architecture non documentée dans l’artifact' }, output];
  }

  function renderArchitecture(rows, series, predictions) {
    const host = $('#dl-architecture');
    const title = $('#dl-architecture-title');
    if (!host) return;
    const selected = series?.model && DL_MODELS.includes(series.model)
      ? series.model
      : predictions?.[0]?.horizons?.find((h) => DL_MODELS.includes(h.model))?.model
        || rows.find((row) => row.split === 'test')?.model_name
        || rows[0]?.model_name;
    if (!selected) {
      if (title) title.textContent = 'Architecture DL — en attente d’un modèle réel';
      host.innerHTML = '<div class="cmp-empty-card"><div><strong>Architecture indisponible</strong>Aucun modèle DL réel n’est sélectionné dans les artifacts.</div></div>';
      return;
    }
    if (title) title.textContent = `Architecture visuelle du modèle réel sélectionné — ${selected}`;
    const stages = architectureStages(selected);
    host.innerHTML = stages.map((stage, index) => `${index ? '<span class="dl-arrow">→</span>' : ''}<div class="dl-node${index === 0 ? '' : index === stages.length - 1 ? ' out' : ' accent'}"><b>${esc(stage.title)}</b><small>${esc(stage.sub)}</small></div>`).join('');
  }

  function renderVs(models) {
    const box = $('#dl-vs');
    if (!box) return;
    const rows = (models || []).filter((m) => m.split === 'test' && DL_MODELS.includes(modelName(m)));
    if (!rows.length) { box.innerHTML = '<div class="muted">Aucune métrique de test réelle disponible.</div>'; return; }
    const byHorizon = {};
    rows.forEach((m) => { const h = m.horizon || '1h'; (byHorizon[h] ||= []).push(m); });
    const blocks = ['1h', '6h', '24h'].filter((h) => byHorizon[h]).map((h) => {
      const ranked = byHorizon[h].slice().sort((a, b) => Number(a.rmse) - Number(b.rmse));
      return `<h4 style="margin:12px 0 6px">Classement réel ${esc(h)} · Test final</h4><div class="table-wrap"><table class="basic-table sci-table"><thead><tr><th>Rang</th><th>Modèle DL</th><th>RMSE TEST</th><th>F1 TEST</th><th>R² TEST</th></tr></thead><tbody>${ranked.map((m, i) => `<tr class="${i === 0 ? 'sci-row-best' : ''}"><td>${i + 1}</td><td><b>${esc(modelName(m))}</b></td><td>${metric(m.rmse)}</td><td>${metric(m.f1)}</td><td>${metric(m.r2)}</td></tr>`).join('')}</tbody></table></div>`;
    }).join('');
    box.innerHTML = blocks || '<div class="muted">Aucun classement réel disponible.</div>';
  }

  function renderPredictions(preds) {
    const box = $('#dl-predictions');
    if (!box) return;
    if (!preds || !preds.length) { box.innerHTML = '<div class="cmp-empty-card"><div><strong>Aucune prédiction DL réelle</strong>Les prédictions DL ne sont pas encore persistées par le pipeline.</div></div>'; return; }
    box.innerHTML = preds.map((p) => `<div class="dl-pred-card"><div class="dl-pred-head"><b>${esc(p.name)}</b><span class="muted small">${esc(p.name_ar || '')}</span></div><div class="dl-pred-type muted small">${esc((p.type || '').replace(/_/g, ' '))}</div><div class="forecast-horizons">${(p.horizons || []).map((h) => `<div class="fh fh-${esc(h.level)}"><div class="fh-h">+${esc(h.h)}h</div><div class="fh-v">${esc(h.predicted)}</div><div class="fh-l muted small">${esc(h.level)} · ${Number.isFinite(Number(h.conf)) ? Math.round(Number(h.conf) * 100) : '—'}%<br><span class="forecast-model">${esc(h.model || 'modèle sélectionné sur validation')}</span>${h.validation_rmse != null ? ` · RMSE val ${esc(h.validation_rmse)}` : ''}${h.test_rmse != null ? ` · RMSE test ${esc(h.test_rmse)}` : ''}</div></div>`).join('')}</div></div>`).join('');
  }

  function renderSeries(s) {
    if (seriesChart) { try { seriesChart.destroy(); } catch (e) {} seriesChart = null; }
    if (!s || !Array.isArray(s.labels) || !s.labels.length) { chartEmpty('dl-series', 'Série réelle indisponible', 'Aucune paire réel/prédit persistée pour le jeu de test.'); return; }
    if (typeof Chart === 'undefined') { chartEmpty('dl-series', 'Graphique indisponible', 'Chart.js n’est pas chargé dans la page.'); return; }
    chartShow('dl-series');
    seriesChart = new Chart($('#dl-series').getContext('2d'), { type: 'line', data: { labels: s.labels, datasets: [{ label: 'Réel', data: s.actual, borderColor: NAVY, backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 }, { label: `Prédit — ${s.model || 'modèle sélectionné sur validation'}`, data: s.predicted, borderColor: RED, borderDash: [5, 4], backgroundColor: 'transparent', pointRadius: 0, tension: 0.25 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } }, title: { display: !!s.zone, text: s.zone ? `Zone ${s.zone} · modèle sélectionné sur validation (${s.model || '—'}) · RMSE test=${s.rmse ?? '—'}` : '', font: { size: 11 } } } } });
  }

  function renderAttention(att) {
    const box = $('#dl-attention');
    if (!box) return;
    if (!att || !att.weights) { box.innerHTML = '<div class="cmp-empty-card"><div><strong>Attention indisponible</strong>Aucun artifact d’attention réel n’est disponible pour ce run.</div></div>'; return; }
    const w = att.weights; let max = -Infinity; let min = Infinity;
    w.forEach((row) => row.forEach((v) => { if (v > max) max = v; if (v < min) min = v; }));
    const span = (max - min) || 1; const cell = (v) => `hsl(210, 65%, ${92 - ((v - min) / span) * 62}%)`;
    const rows = w.length; const cols = rows && Array.isArray(w[0]) ? w[0].length : 0;
    if (!rows || !cols) { box.innerHTML = '<div class="muted">Matrice d’attention réelle vide.</div>'; return; }
    let html = `<div class="attn-grid" style="grid-template-columns:22px repeat(${cols}, 1fr);"><div class="attn-corner"></div>`;
    for (let j = 0; j < cols; j++) html += `<div class="attn-hdr">${j % 6 === 0 ? j : ''}</div>`;
    for (let i = 0; i < rows; i++) { html += `<div class="attn-rowhdr">${i % 6 === 0 ? i : ''}</div>`; for (let j = 0; j < cols; j++) html += `<div class="attn-cell" style="background:${cell(w[i][j])}" title="h${i}←h${j} : ${esc(w[i][j])}"></div>`; }
    html += '</div><div class="attn-legend muted small">Axe X = heures observées · Axe Y = heure prédite · matrice réelle ${rows}×${cols} · plus sombre = plus influent</div>';
    box.innerHTML = html;
  }

  async function load() {
    let d;
    try {
      const r = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      d = await r.json();
    } catch (e) {
      const body = $('#dl-table')?.querySelector('tbody');
      if (body) body.innerHTML = `<tr><td colspan="11"><div class="cmp-empty-card"><div><strong>Erreur réelle du backend</strong>${esc(e.message)}. Vérifiez le journal PHP.</div></div></td></tr>`;
      renderVs([]); renderPredictions([]); renderSeries(null); renderAttention(null); renderArchitecture([], null, []); return;
    }
    const dlRows = (d.models || []).filter((m) => DL_MODELS.includes(modelName(m)));
    const status = $('#dl-data-status');
    if (status) status.innerHTML = d.trained && dlRows.length
      ? `<b>Résultats Deep Learning réels</b><div class="muted small" style="margin-top:5px">${dlRows.length} lignes DL · cinq modèles autorisés uniquement · source open_data · aucune donnée de démonstration.</div>`
      : `<b>Résultats Deep Learning indisponibles</b><div class="muted small" style="margin-top:5px">${esc(d.message || 'Aucun artifact ou métrique DL réel disponible.')}</div>`;
    renderTable(dlRows);
    renderVs(dlRows);
    const predictions = (d.predictions || []).map((p) => ({ ...p, horizons: (p.horizons || []).filter((h) => DL_MODELS.includes(h.model)) })).filter((p) => p.horizons.length);
    renderPredictions(predictions);
    renderArchitecture(dlRows, d.series, predictions);
    renderSeries(d.series);
    renderAttention(d.attention);
  }

  const btn = document.getElementById('dl-refresh');
  if (btn) btn.addEventListener('click', load);
  await load();
};
