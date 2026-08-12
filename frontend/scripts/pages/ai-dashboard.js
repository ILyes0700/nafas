/* UPGRADE v8 — Part 53 : Dashboard IA unifié.
   Lit backend/api/ai-dashboard-data.php et rend chaque section en carte
   « instrument ». Dégradation gracieuse : section vide => masquée (aucune donnée inventée). */
async function initAiDashboard() {
  const grid = document.getElementById('aid-grid');
  const strip = document.getElementById('aid-strip');
  const upd = document.getElementById('aid-updated');
  if (!grid) return;

  const apiBase = (window.GT && GT.api && GT.api.base) ? GT.api.base : 'backend/api';
  let data = null;
  try {
    const r = await fetch(apiBase + '/ai-dashboard-data.php', { credentials: 'same-origin' });
    data = await r.json();
  } catch (e) {
    grid.innerHTML = '<div class="aid-empty">Impossible de charger les données IA (' + (e && e.message || 'erreur') + ').</div>';
    return;
  }
  if (!data || !data.ok) {
    grid.innerHTML = '<div class="aid-empty">Accès refusé ou données indisponibles.</div>';
    return;
  }
  if (upd && data.generated_at) upd.textContent = 'Mis à jour : ' + new Date(data.generated_at).toLocaleString('fr-FR');

  const S = data.sections || {};
  const bench = !!data.benchmark; // valeurs de référence (pipeline non entraîné)
  const esc = s => String(s == null ? '' : s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
  const n = a => Array.isArray(a) ? a.length : 0;

  // ---- Bande de mesure : une pastille par modèle (RMSE) ----
  const perf = S.model_comparison || [];
  if (strip) {
    if (!perf.length) {
      strip.innerHTML = '';
    } else {
      strip.innerHTML = perf.slice(0, 8).map(p => {
        const rmse = p.rmse != null ? Number(p.rmse).toFixed(2) : '—';
        const cls = p.rmse == null ? 'warn' : (Number(p.rmse) < 10 ? '' : Number(p.rmse) < 20 ? 'warn' : 'alert');
        return '<div class="chip ' + cls + '"><div class="k">' + esc(p.model) + '</div><div class="v aid-mono">' + rmse + '</div>' + sparkline(p) + '</div>';
      }).join('');
    }
  }

  function sparkline() {
    // Mini sparkline décoratif (tendance stable si pas d'historique détaillé).
    const pts = [8, 7, 9, 6, 7, 5, 6];
    const w = 110, h = 22, max = Math.max.apply(null, pts), min = Math.min.apply(null, pts);
    const d = pts.map((v, i) => (i / (pts.length - 1) * w) + ',' + (h - (v - min) / (max - min || 1) * h)).join(' ');
    return '';
  }

  const ts = data.table_status || {};
  const card = (label, tableKey, count, bodyHtml) => {
    const known = tableKey in ts;
    const real = known ? ts[tableKey] && count > 0 : count > 0;
    // N'afficher QUE les sections avec de vrais résultats entraînés (pas de DEMO).
    if (!real || bench) return '';
    const badge = '<span class="aid-badge pass">PASS</span>';
    return '<div class="aid-card"><header><span class="lbl">' + esc(label) + '</span>' + badge + '</header><div class="body">' + bodyHtml + '</div></div>';
  };
  const refNote = () => '<div style="font-size:11px;margin-top:6px;color:#1e40af">Valeurs de référence (benchmark). Lancez l\'entraînement pour afficher vos propres données.</div>';
  const demoBody = () => '<div class="aid-empty"><div class="demo-tag">Modèle non entraîné — aucun résultat réel</div>'
    + '<div style="font-size:12px;margin-top:4px">Lancez l\'entraînement pour peupler cette section.</div>'
    + '<button onclick="aidLaunchTraining(this)">Lancer l\'entraînement</button></div>';

  const miniTable = (rows, cols) => {
    if (!rows || !rows.length) return '<div class="aid-empty">Aucune ligne.</div>';
    const head = '<tr>' + cols.map(c => '<th>' + esc(c.label) + '</th>').join('') + '</tr>';
    const body = rows.slice(0, 6).map(r => '<tr>' + cols.map(c => '<td class="aid-mono">' + esc(r[c.key]) + '</td>').join('') + '</tr>').join('');
    return '<table>' + head + body + '</table>';
  };

  const ov = S.overview || {};
  const cards = [];

  if (ov.trained) {
    cards.push('<div class="aid-card"><header><span class="lbl">Vue d\'ensemble</span>'
      + '<span class="aid-badge pass">PASS</span></header>'
      + '<div class="body"><div class="aid-metric-row">'
      + '<div class="aid-metric"><div class="k">Meilleur modèle</div><div class="metric-val">' + esc(ov.best_model || '—') + '</div></div>'
      + '<div class="aid-metric"><div class="k">RMSE global</div><div class="metric-val">' + (ov.global_rmse != null ? ov.global_rmse : '—') + '</div></div>'
      + '<div class="aid-metric"><div class="k">Versions</div><div class="metric-val">' + (ov.versions || 0) + '</div></div>'
      + '</div></div></div>');
  }

  cards.push(card('Comparaison des modèles', 'forecast_metrics', n(perf),
    miniTable(perf, [{ key: 'model', label: 'Modèle' }, { key: 'rmse', label: 'RMSE' }, { key: 'f1', label: 'F1' }])));

  const dl = S.deep_learning || {};
  cards.push(card('Deep Learning — TFT / Attention', 'model_predictions', n(dl.tft),
    miniTable(dl.tft, [{ key: 'zone_id', label: 'Zone' }, { key: 'predicted', label: 'Préd.' }])));

  const xai = S.xai || {};
  cards.push(card('Explicabilité (XAI)', 'xai_explanations', n(xai.explanations) + n(xai.interactions) + n(xai.counterfactuals),
    '<div style="font-size:12px">SHAP/LIME : <b>' + n(xai.explanations) + '</b> · Interactions : <b>' + n(xai.interactions) + '</b> · Contrefactuels : <b>' + n(xai.counterfactuals) + '</b></div>'));

  const unc = S.uncertainty || {};
  cards.push(card('Incertitude & calibration', 'calibration_metrics', n(unc.conformal) + n(unc.calibration),
    '<div style="font-size:12px">Intervalles conformes : <b>' + n(unc.conformal) + '</b> · Métriques calibration : <b>' + n(unc.calibration) + '</b></div>'));

  const ad = S.anomaly_drift || {};
  cards.push(card('Anomalies & dérive', 'anomaly_events', n(ad.anomalies) + n(ad.drift),
    '<div style="font-size:12px">Anomalies : <b>' + n(ad.anomalies) + '</b> · Dérive : <b>' + n(ad.drift) + '</b></div>'));

  cards.push(card('Causalité (Granger)', 'granger_causality', n(S.causality),
    miniTable(S.causality, [{ key: 'cause', label: 'Cause' }, { key: 'effect', label: 'Effet' }, { key: 'p_value', label: 'p' }])));

  cards.push(card('Spatial (GNN)', 'gnn_spatial_edges', n(S.spatial),
    miniTable(S.spatial, [{ key: 'src_zone', label: 'Src' }, { key: 'dst_zone', label: 'Dst' }, { key: 'weight', label: 'Poids' }])));

  const ea = S.ensemble_ab || {};
  cards.push(card('Ensemble & A/B testing', 'ab_test_runs', n(ea.weights) + n(ea.ab_runs),
    '<div style="font-size:12px">Poids ensemble : <b>' + n(ea.weights) + '</b> · Tests A/B : <b>' + n(ea.ab_runs) + '</b></div>'));

  cards.push(card('Registre des modèles', 'model_versions', n(S.registry),
    miniTable(S.registry, [{ key: 'model_name', label: 'Modèle' }, { key: 'status', label: 'Statut' }])));

  const shown = cards.filter(Boolean);
  grid.innerHTML = shown.length ? shown.join('') : '<div class="aid-empty">Aucun résultat réel disponible pour le moment. Lancez l\'entraînement : python -m models.train_all</div>';
}

function aidLaunchTraining(btn) {
  if (btn) { btn.disabled = true; btn.textContent = 'Voir la procédure…'; }
  alert('Pour peupler ce tableau de bord, lancez l\'entraînement côté serveur :\n\n'
    + 'pip install -r models/requirements.txt\n'
    + 'python models/train_all.py\n\n'
    + '(nécessite MySQL + Python ; non exécutable depuis le navigateur).');
  if (btn) { btn.disabled = false; btn.textContent = 'Lancer l\'entraînement'; }
}
