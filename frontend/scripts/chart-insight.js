/* ============================================================================
 * chart-insight.js — Rapport Business Intelligence AUTOMATIQUE sous chaque
 * graphique. Style premium, sections structurées.
 *
 * «NO FAKE» : toutes les valeurs sont CALCULÉES à partir des données réelles
 * du graphique (Chart.getChart) — moyenne, tendance, écart-type, z-score des
 * anomalies, régression linéaire pour la prévision, corrélation. Rien n'est
 * inventé. Si le graphique est vide -> état premium « Aucune donnée ».
 *
 * S'applique à TOUS les graphiques (>= 4 points) de toutes les pages.
 * ========================================================================== */
(function () {
  'use strict';

  function route() {
    var h = location.hash || '';
    var m = h.match(/#\/?([a-z0-9-]+)/i);
    return m ? m[1].toLowerCase() : 'dashboard';
  }

  /* ---------------------------------------------------------------- CSS -- */
  function injectCss() {
    if (document.getElementById('cix-style')) return;
    var css = [
      '.cix{margin:16px 0 6px;border:1px solid #e5e9f0;border-radius:16px;background:#fff;box-shadow:0 2px 10px rgba(16,24,40,.05);overflow:hidden;animation:cixIn .35s ease}',
      '@keyframes cixIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}',
      '.cix-hd{display:flex;align-items:center;gap:11px;padding:13px 16px;background:linear-gradient(120deg,#0d3b66,#1d4e89);color:#fff}',
      '.cix-hd .cix-ic{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.16);display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}',
      '.cix-hd .cix-ic svg{width:18px;height:18px}',
      '.cix-hd h4{margin:0;font-size:14px;font-weight:700;letter-spacing:.2px}',
      '.cix-hd .cix-hd-sub{font-size:11px;opacity:.85;margin-top:1px}',
      '.cix-badges{margin-left:auto;display:flex;gap:7px;flex-wrap:wrap}',
      '.cix-bdg{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:rgba(255,255,255,.15);color:#fff}',
      '.cix-bdg .d{width:7px;height:7px;border-radius:50%;background:#fff}',
      '.cix-body{padding:14px 16px}',
      '.cix-exec{display:flex;gap:10px;align-items:flex-start;background:#f4f8ff;border:1px solid #dbe7fb;border-left:4px solid #1d4e89;border-radius:10px;padding:11px 13px;margin-bottom:14px}',
      '.cix-exec svg{width:17px;height:17px;color:#1d4e89;flex:0 0 auto;margin-top:1px}',
      '.cix-exec b{color:#0d3b66}',
      '.cix-exec p{margin:0;font-size:13px;line-height:1.55;color:#243044}',
      '.cix-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:11px}',
      '@media (max-width:720px){.cix-grid{grid-template-columns:1fr}}',
      '.cix-sec{border:1px solid #eceef3;border-radius:11px;padding:11px 12px;background:#fcfdff}',
      '.cix-sec.acc-blue{border-left:3px solid #2563eb}.cix-sec.acc-green{border-left:3px solid #16a34a}',
      '.cix-sec.acc-amber{border-left:3px solid #d97706}.cix-sec.acc-red{border-left:3px solid #dc2626}',
      '.cix-sec.acc-violet{border-left:3px solid #7c3aed}.cix-sec.acc-teal{border-left:3px solid #0d9488}.cix-sec.acc-slate{border-left:3px solid #64748b}',
      '.cix-sec-hd{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.4px;margin-bottom:7px}',
      '.cix-sec-hd svg{width:15px;height:15px}',
      '.cix-sec.acc-blue .cix-sec-hd svg{color:#2563eb}.cix-sec.acc-green .cix-sec-hd svg{color:#16a34a}',
      '.cix-sec.acc-amber .cix-sec-hd svg{color:#d97706}.cix-sec.acc-red .cix-sec-hd svg{color:#dc2626}',
      '.cix-sec.acc-violet .cix-sec-hd svg{color:#7c3aed}.cix-sec.acc-teal .cix-sec-hd svg{color:#0d9488}.cix-sec.acc-slate .cix-sec-hd svg{color:#64748b}',
      '.cix-sec ul{list-style:none;margin:0;padding:0;display:grid;gap:5px}',
      '.cix-sec li{font-size:12.5px;line-height:1.5;color:#2a3346;display:flex;gap:6px;align-items:flex-start}',
      '.cix-sec li:before{content:"";width:5px;height:5px;border-radius:50%;background:#cbd5e1;margin-top:6px;flex:0 0 auto}',
      '.cix-sec li b{color:#0d3b66}',
      '.cix-sec p{margin:0;font-size:12.5px;line-height:1.55;color:#2a3346}',
      '.cix-chip{display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:700}',
      '.c-up{background:#e7f6ee;color:#15803d}.c-down{background:#fdeaea;color:#b91c1c}.c-flat{background:#eef1f6;color:#475569}',
      '.c-lo{background:#e7f6ee;color:#15803d}.c-md{background:#fef3c7;color:#92400e}.c-hi{background:#ffedd5;color:#c2410c}.c-crit{background:#fee2e2;color:#991b1b}',
      '.cix-meter{height:6px;border-radius:999px;background:#eceef3;overflow:hidden;margin-top:6px}',
      '.cix-meter i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#1d4e89,#3b82f6)}',
      '.cix-empty{display:flex;gap:10px;align-items:center;padding:16px;color:#64748b;font-size:13px}',
      '.cix-empty svg{width:20px;height:20px;color:#94a3b8}',
      '.pf2-dark .cix,.df-dark .cix{background:#242526;border-color:#3a3b3c}',
      '.pf2-dark .cix-sec{background:#2b2c2d;border-color:#3a3b3c}',
      '.pf2-dark .cix-sec li,.pf2-dark .cix-sec p{color:#d5d8dd}',
      '.pf2-dark .cix-exec{background:#1f2b3a;border-color:#2f4763}.pf2-dark .cix-exec p{color:#d5d8dd}'
    ].join('');
    var st = document.createElement('style');
    st.id = 'cix-style'; st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  /* --------------------------------------------------------------- icons - */
  function I(p, c) { return '<svg viewBox="0 0 24 24" fill="none" stroke="' + (c || 'currentColor') + '" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; }
  var IC = {
    report: '<path d="M4 4h16v16H4z"/><path d="M8 15v-3"/><path d="M12 15V9"/><path d="M16 15v-5"/>',
    exec: '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    insight: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    trend: '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    pattern: '<path d="M3 12c3 0 3-7 6-7s3 14 6 14 3-7 6-7"/>',
    anomaly: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13.5"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/>',
    risk: '<path d="M12 2l9 4v6c0 5-3.8 8.5-9 10-5.2-1.5-9-5-9-10V6z"/>',
    impact: '<path d="M3 3v18h18"/><path d="M7 13l3-3 4 4 5-6"/>',
    reco: '<path d="M9 18h6"/><path d="M10 21h4"/><path d="M8.5 15a5.5 5.5 0 1 1 7 0c-.7.6-1 1.2-1 2H9.5c0-.8-.3-1.4-1-2z"/>',
    quality: '<path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 14.8 7.2 17l.9-5.4L4.2 7.7l5.4-.8z"/>',
    forecast: '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
    obs: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    conf: '<path d="M12 2a10 10 0 1 0 10 10"/><path d="M22 4L12 14l-3-3"/>',
    empty: '<path d="M3 3h18v14H3z"/><path d="M3 21h18"/><path d="M8 13l3-3 2 2 3-4"/>'
  };

  /* ---------------------------------------------------------------- math - */
  function nums(arr) {
    var out = [];
    (arr || []).forEach(function (v) {
      var n;
      if (Array.isArray(v)) {                 // barres flottantes [debut, fin] (waterfall) -> amplitude reelle
        n = Number(v[1]) - Number(v[0]);
      } else if (v && typeof v === 'object') {
        n = (v.y != null ? v.y : (v.v != null ? v.v : v.x));
      } else {
        n = v;
      }
      n = Number(n); if (isFinite(n)) out.push(n);
    });
    return out;
  }
  function fmt(n) {
    if (n == null || !isFinite(n)) return '—';
    var a = Math.abs(n);
    if (a >= 1000) return n.toFixed(0);
    if (a >= 10) return n.toFixed(1);
    if (a >= 1) return n.toFixed(2);
    return n.toFixed(3);
  }
  function mean(a) { for (var i = 0, s = 0; i < a.length; i++) s += a[i]; return a.length ? s / a.length : 0; }
  function std(a, m) { if (a.length < 2) return 0; m = (m == null ? mean(a) : m); for (var i = 0, s = 0; i < a.length; i++) s += (a[i] - m) * (a[i] - m); return Math.sqrt(s / (a.length - 1)); }
  function extent(a) { var mn = Infinity, mx = -Infinity, mi = 0, xi = 0; for (var i = 0; i < a.length; i++) { if (a[i] < mn) { mn = a[i]; mi = i; } if (a[i] > mx) { mx = a[i]; xi = i; } } return { min: mn, max: mx, minI: mi, maxI: xi }; }
  function linreg(a) { var n = a.length, sx = 0, sy = 0, sxx = 0, sxy = 0; for (var i = 0; i < n; i++) { sx += i; sy += a[i]; sxx += i * i; sxy += i * a[i]; } var d = n * sxx - sx * sx; if (!d) return null; var b = (n * sxy - sx * sy) / d; var c = (sy - b * sx) / n; var ssTot = 0, ssRes = 0, my = sy / n; for (var j = 0; j < n; j++) { var f = b * j + c; ssRes += (a[j] - f) * (a[j] - f); ssTot += (a[j] - my) * (a[j] - my); } var r2 = ssTot ? 1 - ssRes / ssTot : 0; return { slope: b, intercept: c, r2: r2 }; }
  function pearson(a, b) { var n = Math.min(a.length, b.length); if (n < 3) return null; var sa = 0, sb = 0, saa = 0, sbb = 0, sab = 0; for (var i = 0; i < n; i++) { sa += a[i]; sb += b[i]; saa += a[i] * a[i]; sbb += b[i] * b[i]; sab += a[i] * b[i]; } var den = Math.sqrt((n * saa - sa * sa) * (n * sbb - sb * sb)); return den ? (n * sab - sa * sb) / den : null; }
  function mae(a, b) { var n = Math.min(a.length, b.length), s = 0; for (var i = 0; i < n; i++) s += Math.abs(a[i] - b[i]); return n ? s / n : 0; }

  function labelAt(chart, i) { var L = chart.data.labels || []; var v = L[i]; return v == null ? ('#' + (i + 1)) : String(v); }
  function sec(acc, icon, title, inner) { return '<div class="cix-sec acc-' + acc + '"><div class="cix-sec-hd">' + I(icon) + title + '</div>' + inner + '</div>'; }
  function ul(items) { return '<ul>' + items.map(function (x) { return '<li>' + x + '</li>'; }).join('') + '</ul>'; }

  /* ------------------------------------------------------------- analyze - */
  function analyze(chart) {
    var type = (chart.config && chart.config.type) || 'line';
    var dss = (chart.data && chart.data.datasets) || [];
    var series = [];
    var expected = 0;
    dss.forEach(function (d) {
      var raw = d.data || [];
      expected = Math.max(expected, raw.length);
      var a = nums(raw);
      if (a.length) series.push({ label: (d.label || '').trim(), a: a, raw: raw.length });
    });
    if (!series.length) return { empty: true };
    var totalPts = series.reduce(function (s, x) { return s + x.a.length; }, 0);
    if (totalPts < 4) return { skip: true };

    var main = series.slice().sort(function (a, b) { return b.a.length - a.a.length; })[0];
    var A = main.a, n = A.length;
    var m = mean(A), sd = std(A, m), ext = extent(A), range = ext.max - ext.min;
    var first = A[0], last = A[n - 1], delta = last - first;
    var pctChange = first ? (delta / Math.abs(first)) * 100 : 0;
    var cv = m ? Math.abs(sd / m) : 0;                 // volatilité
    var pos = range ? (last - ext.min) / range : 0.5;  // position de la dernière valeur
    var trend = Math.abs(delta) < (Math.abs(m) * 0.03 + 1e-9) ? 'flat' : (delta > 0 ? 'up' : 'down');
    var nm = main.label ? ('« ' + main.label + ' »') : 'la série principale';

    // anomalies (z-score > 2)
    var anomalies = [], worst = null;
    if (sd > 0) {
      for (var i = 0; i < n; i++) {
        var z = (A[i] - m) / sd;
        if (Math.abs(z) > 2) { anomalies.push(i); if (!worst || Math.abs(z) > Math.abs(worst.z)) worst = { i: i, z: z, v: A[i] }; }
      }
    }

    // 2 séries : réel vs prédit / généré
    var realIdx = -1, predIdx = -1;
    series.forEach(function (s, i) {
      if (/réel|reel|real|actual|observ|mesur/i.test(s.label)) realIdx = i;
      if (/préd|pred|génér|gener|modèle|model|estim|forecast|prév/i.test(s.label)) predIdx = i;
    });
    var fit = null;
    if (realIdx >= 0 && predIdx >= 0 && realIdx !== predIdx) {
      var rr = series[realIdx].a, pp = series[predIdx].a;
      fit = { mae: mae(rr, pp), r: pearson(rr, pp), gen: /génér|gener/i.test(series[predIdx].label) };
    }

    /* ---- Confidence (réelle : dépend de n, complétude, volatilité) ---- */
    var completeness = expected ? Math.min(1, main.a.length / expected) : 1;
    var conf = 35 + Math.min(45, n * 2.2);
    conf *= completeness;
    conf -= Math.min(22, cv * 22);
    conf = Math.max(20, Math.min(96, Math.round(conf)));

    /* ---- Data quality ---- */
    var missing = Math.max(0, expected - main.a.length);
    var qPct = expected ? Math.round((main.a.length / expected) * 100) : 100;
    var qLbl = qPct >= 95 ? 'Excellente' : qPct >= 80 ? 'Bonne' : qPct >= 60 ? 'Moyenne' : 'Faible';

    /* ---- Risk (lecture statistique transparente) ---- */
    var rs = 0;
    if (pos > 0.85) rs += 2; else if (pos > 0.7) rs += 1;
    if (cv > 0.5) rs += 2; else if (cv > 0.25) rs += 1;
    if (anomalies.length > 2) rs += 2; else if (anomalies.length > 0) rs += 1;
    if (trend === 'up' && Math.abs(pctChange) > 15) rs += 1;
    var risk = rs >= 5 ? ['Critique', 'c-crit'] : rs >= 3 ? ['Élevé', 'c-hi'] : rs >= 1 ? ['Modéré', 'c-md'] : ['Faible', 'c-lo'];

    /* ---- Forecast (régression linéaire réelle) ---- */
    var fc = null;
    if ((type === 'line' || type === 'bar') && n >= 8) {
      var lr = linreg(A);
      if (lr) { fc = { next: lr.slope * n + lr.intercept, next3: lr.slope * (n + 2) + lr.intercept, r2: lr.r2, slope: lr.slope }; }
    }

    /* ------------------------------------------------ build sections ---- */
    var trendChip = trend === 'up' ? '<span class="cix-chip c-up">↗ hausse</span>' : trend === 'down' ? '<span class="cix-chip c-down">↘ baisse</span>' : '<span class="cix-chip c-flat">→ stable</span>';

    var execTxt = 'Sur <b>' + n + '</b> points, ' + nm + ' évolue de <b>' + fmt(first) + '</b> à <b>' + fmt(last) + '</b> (' +
      (delta >= 0 ? '+' : '') + fmt(delta) + ', ' + (pctChange >= 0 ? '+' : '') + fmt(pctChange) + '%) ' + trendChip +
      '. Moyenne <b>' + fmt(m) + '</b>, volatilité ' + (cv > 0.5 ? 'élevée' : cv > 0.25 ? 'modérée' : 'faible') +
      ' (CV ' + fmt(cv) + '). Niveau de risque statistique : <b>' + risk[0] + '</b>.';

    var secs = [];

    // Key insights
    var ki = [
      'Maximum : <b>' + fmt(ext.max) + '</b> (' + labelAt(chart, ext.maxI) + ')',
      'Minimum : <b>' + fmt(ext.min) + '</b> (' + labelAt(chart, ext.minI) + ')',
      'Moyenne : <b>' + fmt(m) + '</b> · écart-type : <b>' + fmt(sd) + '</b>',
      'Amplitude : <b>' + fmt(range) + '</b>'
    ];
    if (fit) { ki.push('Écart réel/' + (fit.gen ? 'généré' : 'prédit') + ' (MAE) : <b>' + fmt(fit.mae) + '</b>' + (fit.r != null ? ' · r = <b>' + fmt(fit.r) + '</b>' : '')); }
    secs.push(sec('blue', IC.insight, 'Key Insights', ul(ki)));

    // Trend analysis
    var slopePer = 'pente moyenne <b>' + fmt(delta / Math.max(1, n - 1)) + '</b>/point';
    secs.push(sec(trend === 'down' ? 'red' : 'green', IC.trend, 'Trend Analysis',
      '<p>Direction ' + trendChip + '. Variation totale <b>' + (delta >= 0 ? '+' : '') + fmt(delta) + '</b> (<b>' + (pctChange >= 0 ? '+' : '') + fmt(pctChange) + '%</b>), ' + slopePer + '.</p>'));

    // Pattern detection
    var patt = [];
    patt.push('Volatilité : <b>' + (cv > 0.5 ? 'forte' : cv > 0.25 ? 'modérée' : 'faible') + '</b> (CV ' + fmt(cv) + ')');
    var incCount = 0; for (var k = 1; k < n; k++) if (A[k] >= A[k - 1]) incCount++;
    var monoR = incCount / Math.max(1, n - 1);
    patt.push(monoR > 0.8 ? 'Progression <b>quasi‑monotone croissante</b>' : monoR < 0.2 ? 'Progression <b>quasi‑monotone décroissante</b>' : 'Alternance hausses/baisses (' + Math.round(monoR * 100) + '% de hausses)');
    var lbls = (chart.data.labels || []).map(String).join(' ');
    if (/\b([01]?\d|2[0-3])h?\b/.test(lbls) && /h/i.test(lbls)) patt.push('Axe horaire détecté → <b>cycle journalier</b>, pic vers <b>' + labelAt(chart, ext.maxI) + '</b>');
    secs.push(sec('violet', IC.pattern, 'Pattern Detection', ul(patt)));

    // Anomaly detection
    var anoInner;
    if (anomalies.length) {
      anoInner = '<p><b>' + anomalies.length + '</b> anomalie(s) détectée(s) (|z| &gt; 2). Plus marquée : <b>' + fmt(worst.v) + '</b> (' + labelAt(chart, worst.i) + ', z = ' + fmt(worst.z) + ').</p>';
    } else {
      anoInner = '<p>Aucune anomalie statistique (toutes les valeurs dans ±2 écarts‑types). Signal stable.</p>';
    }
    secs.push(sec(anomalies.length ? 'amber' : 'green', IC.anomaly, 'Anomaly Detection', anoInner));

    // Risk
    secs.push(sec(rs >= 3 ? 'red' : rs >= 1 ? 'amber' : 'green', IC.risk, 'Risk Level',
      '<p>Niveau : <span class="cix-chip ' + risk[1] + '">' + risk[0] + '</span> — basé sur la position de la dernière valeur (' + Math.round(pos * 100) + '% de l’échelle), la volatilité et les anomalies. Lecture statistique, non médicale.</p>'));

    // Business impact
    var impact;
    if (trend === 'up') impact = 'La hausse de ' + fmt(Math.abs(pctChange)) + '% appelle une <b>surveillance rapprochée</b> : anticiper les seuils et préparer les mesures préventives.';
    else if (trend === 'down') impact = 'La baisse de ' + fmt(Math.abs(pctChange)) + '% est <b>favorable</b> : les actions en cours semblent efficaces, à confirmer dans la durée.';
    else impact = 'Situation <b>stable</b> : pas de changement significatif, maintenir le suivi de routine.';
    secs.push(sec('slate', IC.impact, 'Business Impact', '<p>' + impact + '</p>'));

    // Recommendations
    var recos = [];
    if (anomalies.length) recos.push('Investiguer les <b>' + anomalies.length + '</b> point(s) hors norme (cause capteur / événement réel).');
    if (cv > 0.4) recos.push('Volatilité forte → <b>lisser</b> (moyenne mobile) et vérifier la qualité des capteurs.');
    if (trend === 'up') recos.push('Mettre en place une <b>alerte de seuil</b> avant d’atteindre le maximum observé (' + fmt(ext.max) + ').');
    if (fit && fit.r != null && Math.abs(fit.r) < 0.75) recos.push('Alignement modèle/réel perfectible → <b>ré‑entraîner</b> ou enrichir les variables.');
    if (!recos.length) recos.push('Continuer le <b>suivi standard</b> ; aucun signal critique.');
    secs.push(sec('teal', IC.reco, 'Recommendations', ul(recos)));

    // Forecast
    if (fc) {
      secs.push(sec('teal', IC.forecast, 'Forecast (régression linéaire)',
        '<p>Projection prochaine valeur ≈ <b>' + fmt(fc.next) + '</b> (à +3 : ≈ <b>' + fmt(fc.next3) + '</b>). Qualité d’ajustement <b>R² = ' + fmt(fc.r2) + '</b>' + (fc.r2 < 0.4 ? ' — faible, projection indicative.' : '.') + '</p>'));
    }

    // Data quality
    secs.push(sec('slate', IC.quality, 'Data Quality',
      '<p>Complétude : <b>' + qPct + '%</b> (' + main.a.length + '/' + (expected || main.a.length) + ' points' + (missing ? ', ' + missing + ' manquant(s)' : '') + ') — qualité <b>' + qLbl + '</b>.</p>'));

    // Observations
    var obs = [];
    obs.push('Dernière valeur à <b>' + Math.round(pos * 100) + '%</b> de l’amplitude observée.');
    if (n >= 3) obs.push('Série de <b>' + n + '</b> points analysés.');
    if (series.length > 1) obs.push('<b>' + series.length + '</b> séries comparées sur ce graphique.');
    secs.push(sec('blue', IC.obs, 'Observations', ul(obs)));

    var sig = type + '|' + series.map(function (s) { return s.label + ':' + s.a.length + ':' + fmt(s.a[s.a.length - 1]); }).join('|');
    return {
      empty: false, sig: sig, conf: conf, risk: risk, exec: execTxt, secs: secs
    };
  }

  /* --------------------------------------------------------------- DOM --- */
  function ensurePanel(canvas) {
    var id = canvas.id || (canvas.id = 'cix-cv-' + Math.random().toString(36).slice(2, 8));
    var panel = document.querySelector('.cix[data-for="' + id + '"]');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'cix'; panel.setAttribute('data-for', id);
      var host = canvas.closest('.chart-wrap') || canvas.parentElement;
      if (host && host.parentElement) host.parentElement.insertBefore(panel, host.nextSibling);
      else if (host) host.appendChild(panel);
    }
    return panel;
  }
  function render(panel, info) {
    if (info.skip) { return; }
    if (info.empty) {
      if (panel.getAttribute('data-sig') === 'EMPTY') return;
      panel.setAttribute('data-sig', 'EMPTY');
      panel.innerHTML = '<div class="cix-hd"><span class="cix-ic">' + I(IC.report, '#fff') + '</span><div><h4>Business Intelligence</h4><div class="cix-hd-sub">Interprétation automatique</div></div></div>' +
        '<div class="cix-empty">' + I(IC.empty) + '<div><b>Aucune donnée disponible</b><br>Aucune valeur réelle à interpréter (modèle non entraîné ou période vide).</div></div>';
      return;
    }
    if (panel.getAttribute('data-sig') === info.sig) return;
    panel.setAttribute('data-sig', info.sig);
    panel.innerHTML =
      '<div class="cix-hd"><span class="cix-ic">' + I(IC.report, '#fff') + '</span>' +
      '<div><h4>Business Intelligence — Interprétation</h4><div class="cix-hd-sub">Analyse automatique des données réelles du graphique</div></div>' +
      '<div class="cix-badges">' +
      '<span class="cix-bdg">' + I(IC.conf, '#fff') + 'Confiance ' + info.conf + '%</span>' +
      '<span class="cix-bdg"><span class="d"></span>Risque ' + info.risk[0] + '</span>' +
      '</div></div>' +
      '<div class="cix-body">' +
      '<div class="cix-exec">' + I(IC.exec) + '<p><b>Executive Summary.</b> ' + info.exec + '</p></div>' +
      '<div class="cix-grid">' + info.secs.join('') + '</div>' +
      '</div>';
  }

  function tick() {
    if (!window.Chart || !Chart.getChart) return;
    injectCss();
    var canvases = document.querySelectorAll('canvas');
    for (var i = 0; i < canvases.length; i++) {
      var cv = canvases[i];
      if (cv.offsetWidth === 0 && !cv.offsetParent) continue;
      var ch = null; try { ch = Chart.getChart(cv); } catch (e) { ch = null; }
      if (!ch) continue;
      try { render(ensurePanel(cv), analyze(ch)); } catch (e) { /* silencieux */ }
    }
  }

  function start() {
    setInterval(tick, 1300);
    window.addEventListener('hashchange', function () { setTimeout(tick, 400); });
    setTimeout(tick, 800);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
