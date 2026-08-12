/** ML module (PART 4): XAI 2026 - TreeSHAP + DeepSHAP + DLIME + PDP + Permutation + Decision Plot. Data: /backend/api/forecast-ml.php */
window.initForecastMl = async function () {
  const API = '../backend/api'; const charts = [];
  const $ = (s) => document.querySelector(s);
  const round2 = (v) => Math.round(v * 100) / 100;
  const kill = () => { charts.forEach(c => { try { c.destroy(); } catch (e) {} }); charts.length = 0; };
  const mk = (id, cfg) => { const el = document.getElementById(id); if (el && typeof Chart !== 'undefined') charts.push(new Chart(el.getContext('2d'), cfg)); };
  const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } };

  async function load() {
    let d; try { const r = await fetch(`${API}/forecast-ml.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#ml-table').querySelector('tbody').innerHTML = `<tr><td colspan="12" class="muted">Erreur : ${e.message}</td></tr>`; return; }
    $('#ml-demo-badge').style.display = d.demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(d.demo)) return;
    let bestRmse = Math.min.apply(null, d.models.map(m => m.rmse));
    $('#ml-table').querySelector('tbody').innerHTML = d.models.map(m => `<tr class="${m.rmse === bestRmse ? 'sci-row-best' : ''}"><td><b>${m.model}</b></td><td>${m.acc}</td><td>${m.prec}</td><td>${m.rec}</td><td class="${m.rmse === bestRmse ? 'cell-best' : ''}">${m.f1}</td><td>${m.mae}</td><td class="${m.rmse === bestRmse ? 'cell-best' : ''}">${m.rmse}</td><td>${m.mape}</td><td>${m.smape}</td><td>${m.r2}</td><td>${m.auc}</td><td class="muted">${m.latency}</td></tr>`).join('');
    const cv = d.cv; $('#ml-cv').innerHTML = `Walk-Forward CV (${cv.folds} folds) : F1 = ${cv.f1_mean} \u00b1 ${cv.f1_std} \u00b7 RMSE = ${cv.rmse_mean} \u00b1 ${cv.rmse_std}`;
    $('#ml-shap-caption').textContent = `M\u00e9thode : ${d.xai_method || 'SHAP OLS'} \u00b7 valeur de base ${d.shap.base_value} \u2192 pr\u00e9diction ${d.shap.predicted}`;
    const opt = d.optuna_best || {};
    $('#ml-optuna').innerHTML = Object.keys(opt).length
      ? Object.entries(opt).map(([k, v]) => `<div class="forecast-card"><h4>${k}</h4><div class="table-wrap"><table class="basic-table sci-table"><tbody>${Object.entries(v).map(([kk, vv]) => `<tr><td class="muted">${kk}</td><td><b>${vv}</b></td></tr>`).join('')}</tbody></table></div></div>`).join('')
      : '<div class="muted">Mod\u00e8le non entra\u00een\u00e9 \u2014 aucun r\u00e9glage r\u00e9el disponible. Lancez : python -m models.train_all</div>';
    kill();
    // ---- ROC ----
    const rocDs = d.roc.classes.map(c => ({ label: `${c.name} (AUC=${c.auc})`, data: c.fpr.map((f, i) => ({ x: f, y: c.tpr[i] })), borderColor: c.color, backgroundColor: 'transparent', pointRadius: 0, tension: 0.2 }));
    rocDs.push({ label: `${d.roc.macro.name} (AUC=${d.roc.macro.auc})`, data: d.roc.macro.fpr.map((f, i) => ({ x: f, y: d.roc.macro.tpr[i] })), borderColor: d.roc.macro.color, borderDash: [6, 4], pointRadius: 0 });
    rocDs.push({ label: 'R\u00e9f\u00e9rence', data: [{ x: 0, y: 0 }, { x: 1, y: 1 }], borderColor: '#9ca3af', borderDash: [3, 3], pointRadius: 0 });
    mk('ml-roc', { type: 'scatter', data: { datasets: rocDs }, options: Object.assign({}, base, { showLine: true, scales: { x: { title: { display: true, text: 'False Positive Rate' }, min: 0, max: 1 }, y: { title: { display: true, text: 'True Positive Rate' }, min: 0, max: 1 } }, plugins: { legend: { labels: { boxWidth: 10, font: { size: 9 } } } } }) });
    // ---- TreeSHAP global ----
    mk('ml-shap-global', { type: 'bar', data: { labels: d.shap.global.map(x => x.feature), datasets: [{ data: d.shap.global.map(x => x.importance), backgroundColor: d.shap.global.map((x, i) => `rgba(13,59,102,${Math.max(0.35, 1 - i * 0.07)})`), borderRadius: 5, barPercentage: 0.85 }] }, options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Importance TreeSHAP (arbres, normalis\u00e9e)' } } } }) });
    // ---- SHAP local : WATERFALL ----
    (function () {
      const loc = d.shap.local || [];
      const wfLabels = ['Base (moyenne)']; const wfBars = [[0, d.shap.base_value]]; const wfColors = ['#94a3b8'];
      let cum = d.shap.base_value;
      loc.forEach(x => { const start = cum; const end = cum + x.contribution; wfLabels.push(x.feature); wfBars.push([start, end]); wfColors.push(x.contribution >= 0 ? '#dc2626' : '#2563eb'); cum = end; });
      wfLabels.push('Pr\u00e9diction'); wfBars.push([0, cum]); wfColors.push('#0d3b66');
      mk('ml-shap-local', { type: 'bar', data: { labels: wfLabels, datasets: [{ data: wfBars, backgroundColor: wfColors }] }, options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: false } } }) });
    })();
    // ---- SHAP local : DECISION PLOT (chemin cumule base -> prediction) ----
    (function () {
      const loc = (d.shap.local || []).slice().reverse();
      if (!loc.length) return;
      const labels = ['Base']; const vals = [d.shap.base_value]; let cum = d.shap.base_value;
      loc.forEach(x => { cum = cum + x.contribution; labels.push(x.feature); vals.push(round2(cum)); });
      labels.push('Pr\u00e9diction'); vals.push(round2(cum));
      const pc = vals.map((v, i) => i === 0 ? '#94a3b8' : (i === vals.length - 1 ? '#0d3b66' : (vals[i] >= vals[i - 1] ? '#dc2626' : '#2563eb')));
      mk('ml-decision', { type: 'line', data: { labels, datasets: [{ label: 'Chemin de d\u00e9cision', data: vals, borderColor: '#0d3b66', backgroundColor: 'rgba(13,59,102,.08)', fill: true, tension: 0.25, pointRadius: 5, pointBackgroundColor: pc }] }, options: Object.assign({}, base, { plugins: { legend: { display: false } }, scales: { y: { title: { display: true, text: 'AQI cumul\u00e9' } } } }) });
    })();
    // ---- DLIME ----
    mk('ml-lime', { type: 'bar', data: { labels: d.lime.map(x => x.feature), datasets: [{ data: d.lime.map(x => x.weight), backgroundColor: d.lime.map(x => x.direction === 'positive' ? '#dc2626' : '#16a34a') }] }, options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: false } } }) });
    const lNote = $('#ml-lime-note'); if (lNote) lNote.textContent = 'M\u00e9thode : ' + (d.lime_method || 'DLIME') + ' \u00b7 explication locale d\u00e9terministe (stable \u00e0 chaque ex\u00e9cution).';

    // ---- Graphe 2 : DeepSHAP vs TreeSHAP ----
    if (d.shap.deep && d.shap.deep.length) {
      const deep = d.shap.deep;
      const treeMap = {}; (d.shap.global || []).forEach(x => { treeMap[x.feature] = x.importance; });
      const labels = deep.map(x => x.feature);
      const deepVals = deep.map(x => x.importance);
      const treeVals = labels.map(f => (treeMap[f] != null ? treeMap[f] : 0));
      mk('ml-shap-deep', { type: 'bar', data: { labels, datasets: [
        { label: 'TreeSHAP (arbres)', data: treeVals, backgroundColor: '#cbd5e1', borderRadius: 5, barPercentage: 0.92, categoryPercentage: 0.78 },
        { label: 'DeepSHAP (r\u00e9seau profond) \u2605', data: deepVals, backgroundColor: '#7c3aed', borderRadius: 5, barPercentage: 0.92, categoryPercentage: 0.78 }
      ] }, options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } } }, scales: { x: { title: { display: true, text: 'Importance normalis\u00e9e' } } } }) });
    }

    // ---- PDP (Partial Dependence) ----
    (function () {
      const note = $('#ml-pdp-note');
      if (d.pdp && d.pdp.length) {
        const palette = ['#0d3b66', '#7c3aed', '#16a34a', '#d97706'];
        const ds = d.pdp.map((p, i) => ({ label: p.feature, data: p.grid.map((g, j) => ({ x: g, y: p.values[j] })), borderColor: palette[i % palette.length], backgroundColor: 'transparent', tension: 0.3, pointRadius: 0, borderWidth: 2 }));
        mk('ml-pdp', { type: 'scatter', data: { datasets: ds }, options: Object.assign({}, base, { showLine: true, scales: { x: { title: { display: true, text: 'Valeur du polluant (unit\u00e9 r\u00e9elle)' } }, y: { title: { display: true, text: 'AQI moyen pr\u00e9dit' } } } }) });
        if (note) note.textContent = 'Chaque courbe montre l\u2019effet marginal moyen d\u2019un polluant sur l\u2019AQI, toutes choses \u00e9gales par ailleurs. Une pente forte = influence forte ; un coude = effet de seuil.';
      } else if (note) { note.textContent = 'Disponible apr\u00e8s entra\u00eenement r\u00e9el (python -m models.train_all avec shap install\u00e9).'; }
    })();

    // ---- Permutation Importance ----
    (function () {
      const note = $('#ml-perm-note');
      if (d.permutation && d.permutation.length) {
        const pm = d.permutation;
        mk('ml-permutation', { type: 'bar', data: { labels: pm.map(x => x.feature), datasets: [{ data: pm.map(x => x.importance), backgroundColor: pm.map((x, i) => `rgba(217,119,6,${Math.max(0.35, 1 - i * 0.07)})`), borderRadius: 5, barPercentage: 0.85 }] }, options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Chute de performance si variable m\u00e9lang\u00e9e (importance)' } } } }) });
        if (note) note.textContent = 'Mesure model-agnostic : on m\u00e9lange al\u00e9atoirement une variable et on observe la d\u00e9gradation de l\u2019erreur. Plus la barre est longue, plus la variable est indispensable.';
      } else if (note) { note.textContent = 'Disponible apr\u00e8s entra\u00eenement r\u00e9el (python -m models.train_all).'; }
    })();

    // ---- Beeswarm ----
    if (d.shap.beeswarm && d.shap.beeswarm.length) {
      const bw = d.shap.beeswarm;
      const bwData = [];
      bw.forEach((f, yi) => { (f.points || []).forEach(p => { bwData.push({ x: p.v, y: yi + (Math.random() - 0.5) * 0.6, _c: p.c }); }); });
      const bwColors = bwData.map(p => { const r = Math.round(37 + p._c * 183); const b = Math.round(220 - p._c * 182); return `rgb(${r},80,${b})`; });
      mk('ml-beeswarm', { type: 'scatter', data: { datasets: [{ data: bwData, parsing: false, pointRadius: 3, pointHoverRadius: 5, backgroundColor: bwColors }] }, options: Object.assign({}, base, { plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Valeur SHAP (impact sur AQI)' } }, y: { min: -0.6, max: bw.length - 0.4, ticks: { stepSize: 1, callback: (v) => (bw[v] ? bw[v].feature : '') } } } }) });
    }

    // ---- Comparaison scientifique TreeSHAP vs DeepSHAP (style BI, donnees reelles) ----
    (function () {
      const cmp = d.comparison;
      const cmpEl = $('#ml-comparison');
      if (!cmpEl) return;
      if (!cmp) { cmpEl.innerHTML = '<div class="mlcmp-empty">Comparaison indisponible — modèle non entraîné (python -m models.train_all).</div>'; return; }
      const tree = cmp.linear_top3 || [];
      const deep = cmp.deep_top3 || [];
      const inter = tree.filter(function (f) { return deep.indexOf(f) !== -1; });
      const overlap = tree.length ? Math.round(inter.length / tree.length * 100) : 0;
      const sameTop = tree.length && deep.length && tree[0] === deep[0];
      const agr = overlap >= 66 ? ['Fort', 'c-lo'] : overlap >= 33 ? ['Moyen', 'c-md'] : ['Faible', 'c-hi'];
      const li = function (arr) { return arr.length ? arr.map(function (f, i) { return '<li><span class="mlcmp-rank">' + (i + 1) + '</span><b>' + f + '</b>' + (i === 0 ? ' <span class="mlcmp-tag">moteur #1</span>' : '') + '</li>'; }).join('') : '<li class="muted">—</li>'; };
      cmpEl.innerHTML =
        '<div class="mlcmp">'
        + '<div class="mlcmp-cols">'
        +   '<div class="mlcmp-card acc-slate"><div class="mlcmp-h">TreeSHAP <span class="mlcmp-sub">arbres</span></div><ol class="mlcmp-list">' + li(tree) + '</ol></div>'
        +   '<div class="mlcmp-card acc-violet"><div class="mlcmp-h">DeepSHAP <span class="mlcmp-sub">réseau profond</span></div><ol class="mlcmp-list">' + li(deep) + '</ol></div>'
        + '</div>'
        + '<div class="mlcmp-metrics">'
        +   '<div class="mlcmp-metric"><span class="mlcmp-mlbl">Accord du Top-3</span><span class="mlcmp-mval">' + overlap + '%</span><span class="cix-chip ' + agr[1] + '">' + agr[0] + '</span><div class="mlcmp-meter"><i style="width:' + overlap + '%"></i></div></div>'
        +   '<div class="mlcmp-metric"><span class="mlcmp-mlbl">Variable n°1 commune</span><span class="mlcmp-mval">' + (sameTop ? 'Oui' : 'Non') + '</span><span class="cix-chip ' + (sameTop ? 'c-lo' : 'c-md') + '">' + (sameTop ? tree[0] : 'divergence') + '</span></div>'
        +   '<div class="mlcmp-metric"><span class="mlcmp-mlbl">Méthode retenue</span><span class="mlcmp-mval">' + (cmp.winner || '—') + '</span><span class="cix-chip c-lo">recommandée</span></div>'
        + '</div>'
        + '<div class="mlcmp-interp"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><p><b>Lecture scientifique.</b> ' + (cmp.text || '') + (inter.length ? ' Les deux méthodes s\'accordent sur ' + inter.length + ' variable(s) du Top-3 (' + inter.join(', ') + '), ce qui renforce la robustesse du diagnostic.' : ' Les deux méthodes divergent sur le Top-3 : le réseau profond capte des effets non-linéaires que les arbres lissent.') + '</p></div>'
        + '</div>';
    })();

    // ---- Recommandations IA ----
    const ai = d.ai_reco || {};
    const recoEl = $('#ml-reco');
    const badgeEl = $('#ml-reco-badge');
    const interpEl = $('#ml-reco-interp');
    const prioMeta = { haute: { c: '#dc2626', t: 'Priorit\u00e9 haute' }, moyenne: { c: '#d97706', t: 'Priorit\u00e9 moyenne' }, basse: { c: '#16a34a', t: 'Priorit\u00e9 basse' } };
    if (ai.source === 'groq' && Array.isArray(ai.recommendations) && ai.recommendations.length) {
      if (badgeEl) badgeEl.innerHTML = `<span class="pill success">\ud83e\udd16 G\u00e9n\u00e9r\u00e9 par IA \u00b7 ${ai.model || 'Llama 3.3 70B'}</span> <span class="muted">\u00e0 partir des donn\u00e9es r\u00e9elles (TreeSHAP \u00b7 DeepSHAP \u00b7 DLIME \u00b7 m\u00e9triques)</span>`;
      if (interpEl) interpEl.innerHTML = ai.interpretation ? `<p style="margin:0;line-height:1.55">${ai.interpretation}</p>` : '';
      if (recoEl) recoEl.innerHTML = ai.recommendations.map(r => {
        const pm = prioMeta[r.priority] || prioMeta.moyenne;
        const zone = r.zone ? ` <span class="muted small">\u00b7 ${r.zone}</span>` : '';
        const seg = (icon, label, txt, col) => txt ? `<div style="margin-top:7px"><span style="font-size:10.5px;font-weight:700;letter-spacing:.03em;color:${col}">${icon} ${label}</span><div class="muted" style="margin-top:1px;line-height:1.5">${txt}</div></div>` : '';
        const body = (r.rationale || r.action || r.impact)
          ? (seg('\ud83d\udd2c', 'M\u00c9CANISME', r.rationale, '#7c3aed') + seg('\ud83d\udee0\ufe0f', 'ACTION', r.action, '#0d3b66') + seg('\ud83d\udcc8', 'IMPACT ATTENDU', r.impact, '#16a34a'))
          : `<div class="muted" style="margin-top:3px;line-height:1.5">${r.detail || ''}</div>`;
        return `<div style="display:flex;gap:12px;align-items:flex-start;padding:15px 0;border-top:1px solid #eef2f7"><span style="flex:0 0 auto;margin-top:6px;width:10px;height:10px;border-radius:50%;background:${pm.c};box-shadow:0 0 0 4px ${pm.c}22"></span><div style="flex:1;min-width:0"><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b style="font-size:15px">${r.title}</b>${zone}<span class="pill" style="background:${pm.c}1a;color:${pm.c}">${pm.t}</span></div>${body}</div></div>`;
      }).join('');
    } else {
      if (badgeEl) badgeEl.innerHTML = '<span class="muted small">Recommandations locales (IA hors ligne \u2014 analyse d\u00e9terministe TreeSHAP/DLIME).</span>';
      if (interpEl) interpEl.innerHTML = '';
      if (recoEl) recoEl.innerHTML = (d.recommendations && d.recommendations.length) ? '<ul>' + d.recommendations.map(r => `<li>${r}</li>`).join('') + '</ul>' : '<div class="muted">Aucune recommandation (mod\u00e8le non entra\u00een\u00e9).</div>';
    }

    // ---- Interpretations courtes dynamiques ----
    const gNote = $('#ml-shap-global-note');
    if (gNote) gNote.textContent = (ai.shap_note || (d.shap.global.length ? `Interpr\u00e9tation : ${d.shap.global[0].feature} est la variable la plus d\u00e9terminante de l'AQI selon TreeSHAP.` : ''));
    const dNote = $('#ml-shap-deep-note');
    if (dNote) dNote.textContent = (ai.deep_note || ((d.shap.deep && d.shap.deep.length) ? `Interpr\u00e9tation : DeepSHAP place ${d.shap.deep[0].feature} en t\u00eate via les effets non-lin\u00e9aires et de seuil.` : ''));
  }
  const b = document.getElementById('ml-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
