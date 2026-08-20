/* Nafass — صفحة ML/XAI: تعرض النتائج الحقيقية المحفوظة فقط. */
window.initForecastMl = async function () {
  const API = '../backend/api';
  const charts = [];
  const $ = (selector) => document.querySelector(selector);
  const round2 = (value) => Math.round(Number(value) * 100) / 100;
  const ALLOWED_MODELS = ['Random Forest', 'XGBoost + Fuzzy', 'LSTM', 'BiLSTM Simple', 'BiLSTM+MultiHead Attn', 'BiLSTM+AE', 'CNN+AE'];

  const destroyCharts = () => {
    charts.forEach((chart) => {
      try { chart.destroy(); } catch (e) { /* لا نوقف تحديث الصفحة */ }
    });
    charts.length = 0;
  };

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
  const clearCanvas = (id) => {
    const canvas = document.getElementById(id);
    if (canvas) {
      const ctx = canvas.getContext('2d');
      if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
      chartShow(id);
    }
  };

  const makeChart = (id, config) => {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return false;
    const ctx = canvas.getContext('2d');
    if (!ctx) return false;
    chartShow(id);
    charts.push(new Chart(ctx, config));
    return true;
  };

  const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
  };

  const setNote = (id, text) => {
    const el = document.getElementById(id);
    if (el) el.textContent = text || '';
  };

  const setEmptyNotes = () => {
    setNote('ml-shap-global-note', 'Aucun artefact TreeSHAP réel disponible pour cet entraînement.');
    setNote('ml-shap-deep-note', 'Aucun artefact DeepSHAP réel disponible pour cet entraînement.');
    setNote('ml-pdp-note', 'Aucun artefact PDP réel disponible pour cet entraînement.');
    setNote('ml-perm-note', 'Aucun artefact de permutation réel disponible pour cet entraînement.');
    setNote('ml-lime-note', 'Aucun artefact LIME réel disponible pour cet entraînement.');
    setNote('ml-decision-note', 'Aucun artefact Decision Plot réel n’est fourni par l’endpoint actuel.');
  };

  const renderStatus = (data) => {
    const box = $('#ml-data-status');
    if (!box) return;
    const horizon = data && data.horizon ? data.horizon : $('#ml-horizon')?.value || '1h';
    if (data && data.data_status === 'real') {
      box.innerHTML = `<b>Résultats réels</b><div class="muted small" style="margin-top:5px">Horizon ${horizon} · données issues de model_performance, model_predictions et/ou xai_artifacts. Aucun nombre de démonstration.</div>`;
      return;
    }
    const message = (data && data.message) || 'Aucun résultat réel disponible.';
    const isError = data && data.data_status === 'error';
    box.innerHTML = `<b>${isError ? 'Erreur backend ML' : 'Résultats réels indisponibles'}</b><div class="muted small" style="margin-top:5px">${message}</div>`;
  };

  const renderModels = (models) => {
    const body = $('#ml-table')?.querySelector('tbody');
    if (!body) return;
    const ranked = (Array.isArray(models) ? models : [])
      .filter((model) => ALLOWED_MODELS.includes(model.model))
      .sort((a, b) => Number(a.rmse) - Number(b.rmse));
    if (!ranked.length) {
      body.innerHTML = '<tr><td colspan="12" class="muted">Aucun résultat réel pour cet horizon.</td></tr>';
      return;
    }
    body.innerHTML = ranked.map((model, index) => {
      const isBest = index === 0;
      return `<tr class="${isBest ? 'sci-row-best' : ''}">
        <td><b>#${index + 1} ${model.model || '—'}</b></td>
        <td>${model.acc ?? '—'}</td><td>${model.prec ?? '—'}</td><td>${model.rec ?? '—'}</td>
        <td class="${isBest ? 'cell-best' : ''}">${model.f1 ?? '—'}</td>
        <td>${model.mae ?? '—'}</td><td class="${isBest ? 'cell-best' : ''}">${model.rmse ?? '—'}</td>
        <td>${model.mape ?? '—'}</td><td>${model.smape ?? '—'}</td><td>${model.r2 ?? '—'}</td>
        <td>${model.auc ?? '—'}</td><td class="muted">${model.latency ?? '—'}</td>
      </tr>`;
    }).join('');
  };

  const renderCv = (cv) => {
    const el = $('#ml-cv');
    if (!el) return;
    if (!cv || !cv.folds) { el.textContent = 'Dispersion réelle indisponible pour cet horizon.'; return; }
    el.textContent = `Dispersion réelle sur ${cv.folds} lignes : F1 ${cv.f1_mean} ± ${cv.f1_std} · RMSE ${cv.rmse_mean} ± ${cv.rmse_std}`;
  };

  const renderRoc = (roc) => {
    if (!roc || !Array.isArray(roc.classes) || !roc.classes.length) {
      chartEmpty('ml-roc', 'ROC indisponible', 'Aucune courbe ROC réelle n’est persistée pour cet horizon.');
      return;
    }
    const datasets = roc.classes.map((serie) => ({
      label: `${serie.name} (AUC=${serie.auc})`,
      data: (serie.fpr || []).map((x, i) => ({ x, y: (serie.tpr || [])[i] })),
      borderColor: serie.color || '#0d3b66', backgroundColor: 'transparent',
      pointRadius: 0, tension: 0.1, showLine: true,
    }));
    makeChart('ml-roc', {
      type: 'scatter',
      data: { datasets },
      options: Object.assign({}, baseOptions, {
        scales: { x: { title: { display: true, text: 'False Positive Rate' }, min: 0, max: 1 }, y: { title: { display: true, text: 'True Positive Rate' }, min: 0, max: 1 } },
      }),
    });
  };

  const renderXai = (data) => {
    const shap = data.shap || {};
    if (Array.isArray(shap.global) && shap.global.length) {
      makeChart('ml-shap-global', {
        type: 'bar',
        data: { labels: shap.global.map((item) => item.feature), datasets: [{ data: shap.global.map((item) => item.importance), backgroundColor: '#0d3b66', borderRadius: 5 }] },
        options: Object.assign({}, baseOptions, { indexAxis: 'y', plugins: { legend: { display: false } } }),
      });
      setNote('ml-shap-global-note', `Artefact réel : ${data.xai_method || 'TreeSHAP'}.`);
    } else {
      chartEmpty('ml-shap-global', 'TreeSHAP indisponible', 'Aucun artefact TreeSHAP réel n’est enregistré pour ce run.');
    }

    if (Array.isArray(shap.deep) && shap.deep.length && Array.isArray(shap.global) && shap.global.length) {
      const tree = Object.fromEntries(shap.global.map((item) => [item.feature, item.importance]));
      makeChart('ml-shap-deep', {
        type: 'bar',
        data: {
          labels: shap.deep.map((item) => item.feature),
          datasets: [
            { label: 'TreeSHAP réel', data: shap.deep.map((item) => tree[item.feature] ?? 0), backgroundColor: '#cbd5e1' },
            { label: 'DeepSHAP réel', data: shap.deep.map((item) => item.importance), backgroundColor: '#7c3aed' },
          ],
        },
        options: Object.assign({}, baseOptions, { indexAxis: 'y' }),
      });
      setNote('ml-shap-deep-note', 'Comparaison calculée à partir des deux artefacts réellement persistés.');
    } else {
      chartEmpty('ml-shap-deep', 'DeepSHAP indisponible', 'Les artefacts TreeSHAP et DeepSHAP réels ne sont pas tous disponibles.');
    }

    if (Array.isArray(shap.local) && shap.local.length && Number.isFinite(Number(shap.base_value))) {
      const labels = ['Base'];
      const values = [[0, Number(shap.base_value)]];
      const colors = ['#94a3b8'];
      let cumulative = Number(shap.base_value);
      shap.local.forEach((item) => {
        const contribution = Number(item.contribution) || 0;
        labels.push(item.feature || 'variable');
        values.push([cumulative, cumulative + contribution]);
        colors.push(contribution >= 0 ? '#dc2626' : '#2563eb');
        cumulative += contribution;
      });
      labels.push('Prédiction'); values.push([0, cumulative]); colors.push('#0d3b66');
      makeChart('ml-shap-local', { type: 'bar', data: { labels, datasets: [{ data: values, backgroundColor: colors }] }, options: Object.assign({}, baseOptions, { indexAxis: 'y', plugins: { legend: { display: false } } }) });
      setNote('ml-shap-caption', `Base réelle ${shap.base_value} → prédiction réelle ${shap.predicted ?? '—'}`);
    } else {
      chartEmpty('ml-shap-local', 'Waterfall indisponible', 'Aucun artefact SHAP local réel n’est enregistré.');
    }

    if (Array.isArray(data.lime) && data.lime.length) {
      makeChart('ml-lime', { type: 'bar', data: { labels: data.lime.map((item) => item.feature), datasets: [{ data: data.lime.map((item) => item.weight), backgroundColor: data.lime.map((item) => item.direction === 'positive' ? '#dc2626' : '#16a34a') }] }, options: Object.assign({}, baseOptions, { indexAxis: 'y', plugins: { legend: { display: false } } }) });
      setNote('ml-lime-note', `Artefact réel : ${data.xai_method || 'LIME'}.`);
    } else {
      chartEmpty('ml-lime', 'LIME indisponible', 'Aucun artefact LIME réel n’est enregistré.');
    }

    if (Array.isArray(data.pdp) && data.pdp.length) {
      makeChart('ml-pdp', { type: 'scatter', data: { datasets: data.pdp.map((item, index) => ({ label: item.feature, data: (item.grid || []).map((x, i) => ({ x, y: (item.values || [])[i] })), borderColor: ['#0d3b66', '#7c3aed', '#16a34a', '#d97706'][index % 4], backgroundColor: 'transparent', showLine: true, pointRadius: 0 })) }, options: Object.assign({}, baseOptions, { scales: { x: { title: { display: true, text: 'Valeur réelle' } }, y: { title: { display: true, text: 'AQI prédit réel' } } } }) });
      setNote('ml-pdp-note', 'Artefact PDP réellement calculé pendant l’entraînement.');
    } else {
      chartEmpty('ml-pdp', 'PDP indisponible', 'Aucun artefact PDP réel n’est enregistré.');
    }

    if (Array.isArray(data.permutation) && data.permutation.length) {
      makeChart('ml-permutation', { type: 'bar', data: { labels: data.permutation.map((item) => item.feature), datasets: [{ data: data.permutation.map((item) => item.importance), backgroundColor: '#d97706', borderRadius: 5 }] }, options: Object.assign({}, baseOptions, { indexAxis: 'y', plugins: { legend: { display: false } } }) });
      setNote('ml-perm-note', 'Importance de permutation réellement calculée sur les observations persistées.');
    } else {
      chartEmpty('ml-permutation', 'Permutation indisponible', 'Aucun artefact de permutation réel n’est enregistré.');
    }

    if (Array.isArray(shap.beeswarm) && shap.beeswarm.length) {
      const points = [];
      shap.beeswarm.forEach((feature, y) => (feature.points || []).forEach((point, index) => points.push({ x: point.v, y: y + ((index % 5) - 2) * 0.08, color: point.c })));
      makeChart('ml-beeswarm', { type: 'scatter', data: { datasets: [{ data: points, parsing: false, pointRadius: 3, backgroundColor: points.map((point) => `rgb(${Math.round(37 + (point.color || 0) * 183)},80,${Math.round(220 - (point.color || 0) * 182)})`) }] }, options: Object.assign({}, baseOptions, { plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Valeur SHAP réelle' } }, y: { min: -0.6, max: shap.beeswarm.length - 0.4, ticks: { stepSize: 1, callback: (value) => shap.beeswarm[value]?.feature || '' } } } }) });
    } else {
      chartEmpty('ml-beeswarm', 'Beeswarm indisponible', 'Aucun artefact SHAP par instance réel n’est enregistré.');
    }
  };

  const renderDecision = () => {
    chartEmpty('ml-decision', 'Decision Plot indisponible', 'L’endpoint ne fournit pas d’artefact Decision Plot réel pour ce run.');
  };

  const renderOptuna = (data) => {
    const box = $('#ml-optuna');
    if (!box) return;
    const values = Array.isArray(data?.optuna_best) ? data.optuna_best : [];
    if (!values.length) {
      box.innerHTML = '<div class="cmp-empty-card"><div><strong>Hyperparamètres Optuna indisponibles</strong>Aucun historique Optuna réel n’est fourni. Aucun réglage ne sera inventé.</div></div>';
      return;
    }
    box.innerHTML = values.map((item) => `<div class="real-config-card"><b>${item.model || item.name || 'Modèle'}</b><span class="muted small">${item.rmse != null ? `RMSE réel : ${item.rmse}` : 'Configuration réellement persistée.'}</span></div>`).join('');
  };

  const renderComparison = (comparison) => {
    const el = $('#ml-comparison');
    if (!el) return;
    if (!comparison) { el.innerHTML = '<div class="muted">Comparaison indisponible : artefacts XAI réels insuffisants.</div>'; return; }
    el.innerHTML = `<div class="muted">Accord réel du Top-3 : <b>${comparison.agreement_percent ?? 0}%</b>. ${comparison.text || ''}</div>`;
  };

  const renderRecommendations = (data) => {
    const box = $('#ml-reco');
    const badge = $('#ml-reco-badge');
    if (badge) badge.textContent = data.recommendations?.length ? 'Recommandations dérivées des artefacts XAI réels.' : 'Aucune recommandation réelle disponible.';
    if (!box) return;
    if (!Array.isArray(data.recommendations) || !data.recommendations.length) { box.innerHTML = '<div class="muted">Aucune recommandation : artefacts XAI absents.</div>'; return; }
    box.innerHTML = data.recommendations.map((item) => `<div style="padding:12px 0;border-top:1px solid #eef2f7"><b>${item.title || 'Recommandation'}</b><div class="muted small" style="margin-top:5px">${item.rationale || ''}</div><div class="muted small" style="margin-top:4px"><b>Action :</b> ${item.action || ''}</div><div class="muted small" style="margin-top:4px"><b>Impact :</b> ${item.impact || ''}</div></div>`).join('');
  };

  const fetchData = async () => {
    const horizon = $('#ml-horizon')?.value || '1h';
    const response = await fetch(`${API}/forecast-ml.php?horizon=${encodeURIComponent(horizon)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = await response.text();
    let data;
    try { data = JSON.parse(body); } catch (error) {
      throw new Error(`Réponse non JSON du backend (HTTP ${response.status}) : ${body.slice(0, 240)}`);
    }
    if (!response.ok || !data.ok) throw Object.assign(new Error(data.message || data.error || `HTTP ${response.status}`), { payload: data });
    return data;
  };

  const load = async () => {
    destroyCharts();
    setEmptyNotes();
    ['ml-roc', 'ml-shap-global', 'ml-shap-deep', 'ml-beeswarm', 'ml-pdp', 'ml-permutation', 'ml-shap-local', 'ml-decision', 'ml-lime'].forEach(clearCanvas);
    try {
      const data = await fetchData();
      renderStatus(data);
      renderModels(data.models);
      renderCv(data.cv);
      if (data.data_status !== 'real') return;
      renderRoc(data.roc);
      renderXai(data);
      renderComparison(data.comparison);
      renderRecommendations(data);
      renderDecision(data);
      renderOptuna(data);
    } catch (error) {
      const payload = error.payload || { data_status: 'error', message: error.message };
      renderStatus(payload);
      renderModels([]);
      renderComparison(null);
      renderRecommendations({ recommendations: [] });
      renderDecision({});
      renderOptuna({});
    }
  };

  $('#ml-refresh')?.addEventListener('click', load);
  $('#ml-horizon')?.addEventListener('change', load);
  await load();
};
