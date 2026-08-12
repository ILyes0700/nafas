/* C6 — Weekly AI summary view. */

(function () {
  function escMd(s) { return (s || '').replace(/[<>]/g, ''); }

  /** Minimal Markdown → HTML (h1..h3 + bullets + paragraphes). Pas de gras/italique
   *  pour rester lisible et sécurisé sans dépendance externe. */
  function mdToHtml(md) {
    if (!md) return '';
    const out = [];
    const lines = escMd(md).split(/\r?\n/);
    let buf = [];
    function flush() {
      if (!buf.length) return;
      if (buf[0].startsWith('- ')) {
        out.push('<ul>' + buf.map(l => `<li>${l.slice(2).trim()}</li>`).join('') + '</ul>');
      } else {
        out.push('<p>' + buf.join(' ') + '</p>');
      }
      buf = [];
    }
    for (const raw of lines) {
      const line = raw.trimEnd();
      if (line.startsWith('### ')) { flush(); out.push(`<h3>${line.slice(4)}</h3>`); continue; }
      if (line.startsWith('## '))  { flush(); out.push(`<h3>${line.slice(3)}</h3>`); continue; }
      if (line.startsWith('# '))   { flush(); out.push(`<h2>${line.slice(2)}</h2>`); continue; }
      if (line.startsWith('- '))   { if (buf.length && !buf[0].startsWith('- ')) flush(); buf.push(line); continue; }
      if (line === '')             { flush(); continue; }
      if (buf.length && buf[0].startsWith('- ')) flush();
      buf.push(line);
    }
    flush();
    return out.join('\n');
  }

  function renderMetrics(m) {
    const div = document.getElementById('weekly-metrics');
    if (!div || !m) return;
    const a = m.alerts || {};
    const tz = (m.top_zones || []).slice(0, 3)
      .map(z => `<li><strong>${z.name}</strong> · ${Number(z.avg_s).toFixed(1)}/100 (${z.n})</li>`).join('') ||
      '<li class="muted">No critical zones this week.</li>';
    const ts = (m.top_symptoms || []).slice(0, 3)
      .map(s => `<li>${s.symptom} (${s.n})</li>`).join('') ||
      '<li class="muted">No symptoms reported.</li>';
    div.innerHTML = `
      <div class="metric"><span>Critical alerts</span><strong>${a.critical || 0}</strong></div>
      <div class="metric"><span>Danger alerts</span><strong>${a.danger || 0}</strong></div>
      <div class="metric"><span>Watch alerts</span><strong>${a.warning || 0}</strong></div>
      <div class="metric"><span>Reports</span><strong>${m.reports || 0}</strong></div>
      <div class="metric"><span>Symptoms</span><strong>${m.symptoms || 0}</strong></div>
      <div class="metric metric-list">
        <span>Top zones</span>
        <ul>${tz}</ul>
      </div>
      <div class="metric metric-list">
        <span>Top symptoms</span>
        <ul>${ts}</ul>
      </div>`;
  }

  async function loadWeek(weekStart, regenerate) {
    const params = new URLSearchParams();
    if (weekStart) params.set('week_start', weekStart);
    if (regenerate) params.set('regenerate', '1');
    const url = '../backend/api/weekly-summary.php' + (params.toString() ? `?${params}` : '');
    const r = await fetch(url, { credentials: 'same-origin' });
    return r.json().catch(() => ({}));
  }

  async function refresh(regenerate) {
    const start = (document.getElementById('weekly-week-start') || {}).value || '';
    const summaryEl  = document.getElementById('weekly-summary');
    const metricsEl  = document.getElementById('weekly-metrics');
    const tagEl      = document.getElementById('weekly-model-tag');
    const tsEl       = document.getElementById('weekly-generated-at');
    if (summaryEl) summaryEl.innerHTML = '<p class="muted">Generating… (~5s)</p>';
    if (metricsEl) metricsEl.innerHTML = '<p class="muted">Calculating…</p>';

    const data = await loadWeek(start, regenerate);
    if (data.ok) {
      if (summaryEl) summaryEl.innerHTML = mdToHtml(data.summary_md);
      renderMetrics(data.metrics || {});
      if (tagEl) tagEl.textContent = data.model || '';
      if (tsEl) {
        tsEl.textContent = data.cached
          ? `Cached · week ${data.week_start} → ${data.week_end}`
          : `Generated · week ${data.week_start} → ${data.week_end}`;
      }
    } else {
      if (summaryEl) summaryEl.innerHTML = `<p class="error-text">${data.error || 'Unavailable'}</p>`;
      renderMetrics(data.metrics || {});
    }
  }

  window.initWeekly = function () {
    const role = (window.GT_USER && window.GT_USER.role) || '';
    const regen = document.getElementById('weekly-regen');
    if (regen) regen.hidden = role !== 'admin';
    if (regen) regen.addEventListener('click', () => refresh(true));

    const load = document.getElementById('weekly-load');
    if (load) load.addEventListener('click', () => refresh(false));

    refresh(false);
  };
})();
