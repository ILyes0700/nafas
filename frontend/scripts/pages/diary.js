/* B7 — Personal health diary (logged-in user). */

(function () {
  let _chart = null;

  function fmtToday() {
    const d = new Date();
    return d.toLocaleDateString('en-US', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
  }

  function bindRanges(form) {
    form.querySelectorAll('input[type=range]').forEach(input => {
      const out  = form.querySelector(`[data-out="${input.name}"]`);
      const wrap = input.closest('.diary-range');
      const min  = Number(input.getAttribute('min') || 0);
      const max  = Number(input.getAttribute('max') || 5);
      const upd = () => {
        const v = Number(input.value);
        if (out) out.textContent = String(v);
        // applique une teinte selon l'intensité (vert → rouge)
        if (wrap) {
          wrap.classList.remove('dr-low', 'dr-mid', 'dr-high');
          // mood (1..5) : 5 = excellent → low ; cough/etc (0..5) : 0 = bon → low
          const isMood = input.name === 'mood';
          const norm = isMood ? (max - v) / (max - min) : (v - min) / (max - min);
          if (norm >= 0.7) wrap.classList.add('dr-high');
          else if (norm >= 0.4) wrap.classList.add('dr-mid');
          else wrap.classList.add('dr-low');
        }
        // remplit la track du slider via custom property CSS
        const pct = ((v - min) / (max - min)) * 100;
        input.style.setProperty('--dr-pct', pct + '%');
      };
      input.addEventListener('input', upd);
      upd();
    });
  }

  async function loadEntries() {
    const r = await fetch('../backend/api/diary.php?days=30', { credentials: 'same-origin' });
    if (!r.ok) return null;
    return r.json();
  }

  function renderTable(rows) {
    const tbody = document.querySelector('#diary-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="muted">No entries yet.</td></tr>';
      return;
    }
    [...rows].reverse().forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.diary_date}</td>
        <td>${r.mood}/5</td>
        <td>${r.cough}/5</td>
        <td>${r.breath_diff}/5</td>
        <td>${r.eye_irritation}/5</td>
        <td>${r.headache}/5</td>
        <td>${r.fatigue}/5</td>
        <td>${(r.notes || '').replace(/[<>]/g, '')}</td>`;
      tbody.appendChild(tr);
    });
  }

  function renderChart(rows) {
    const c = document.getElementById('diary-chart');
    if (!c || !window.Chart) return;
    if (_chart) { _chart.destroy(); _chart = null; }
    // Empty state — éviter un canvas vide qui s'étire
    if (!rows || rows.length === 0) {
      const ctx = c.getContext('2d');
      const wrap = c.parentElement;
      const w = wrap ? wrap.clientWidth : 600;
      const h = wrap ? wrap.clientHeight : 280;
      c.width = w; c.height = h;
      ctx.clearRect(0, 0, w, h);
      ctx.fillStyle = '#9ca3af';
      ctx.font = '13px system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('No data yet — log your first day.', w / 2, h / 2);
      return;
    }
    const labels = rows.map(r => r.diary_date.slice(5));
    const ds = (label, key, color) => ({
      label, data: rows.map(r => r[key]),
      borderColor: color, backgroundColor: color + '40', tension: 0.25, borderWidth: 2,
    });
    _chart = new Chart(c, {
      type: 'line',
      data: { labels, datasets: [
        ds('Mood',       'mood',           '#3b82f6'),
        ds('Cough',      'cough',          '#ef4444'),
        ds('Breathing',  'breath_diff',    '#f59e0b'),
        ds('Eyes',       'eye_irritation', '#8b5cf6'),
        ds('Headache',   'headache',       '#10b981'),
        ds('Fatigue',    'fatigue',        '#6b7280'),
      ]},
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { min: 0, max: 5, ticks: { stepSize: 1 } } },
        plugins: { legend: { position: 'bottom' } },
      },
    });
  }

  function renderStats(payload) {
    const s = document.getElementById('diary-stats');
    if (!s) return;
    if (!payload.count) {
      s.textContent = 'No data — start by logging today.';
      return;
    }
    const a = payload.average || {};
    s.textContent = `${payload.count} entries · averages — mood ${a.mood} · cough ${a.cough} · breathing ${a.breath_diff} · eyes ${a.eye_irritation} · headache ${a.headache} · fatigue ${a.fatigue}`;
  }

  async function refresh() {
    const data = await loadEntries();
    if (!data || !data.ok) return;
    renderChart(data.entries || []);
    renderTable(data.entries || []);
    renderStats(data);
    loadConsultHistory();
  }

  function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString(undefined, {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  async function loadConsultHistory() {
    const card  = document.getElementById('consult-history-card');
    const list  = document.getElementById('consult-history-list');
    const count = document.getElementById('consult-history-count');
    if (!card || !list) return;
    try {
      const r = await fetch('../backend/api/telemed-history.php', { credentials: 'same-origin' });
      const d = await r.json();
      if (!d.ok) return;
      const items = (d.consultations || []).filter(x => x.post_consult || x.pre_consult);
      if (!items.length) { card.hidden = true; return; }
      card.hidden = false;
      if (count) count.textContent = items.length + (items.length === 1 ? ' record' : ' records');

      list.innerHTML = items.map(c => {
        let pre = null, post = null;
        try { pre  = c.pre_consult  ? JSON.parse(c.pre_consult)  : null; } catch (_) {}
        try { post = c.post_consult ? JSON.parse(c.post_consult) : null; } catch (_) {}

        const status = c.status || '';
        const pillCls = status === 'closed' ? 'safe' : status === 'expired' ? 'warning' : '';

        const preHtml = pre ? `
          <div class="ch-vitals">
            ${pre.temperature != null ? `<span class="dtq-chip"><svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 14.76V3.5a2.5 2.5 0 00-5 0v11.26a4.5 4.5 0 105 0z"/></svg> ${pre.temperature}°C</span>` : ''}
            ${pre.pulse       != null ? `<span class="dtq-chip"><svg class="ico-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg> ${pre.pulse} bpm</span>` : ''}
            ${pre.oxygen_sat  != null ? `<span class="dtq-chip">O₂ ${pre.oxygen_sat}%</span>` : ''}
            ${pre.symptoms    ? `<span class="dtq-chip dtq-chip-soft">${escHtml(pre.symptoms)}</span>` : ''}
          </div>` : '';

        const postHtml = post ? `
          <div class="ch-post">
            ${post.diagnosis       ? `<div class="ch-row"><b>Diagnosis:</b> ${escHtml(post.diagnosis)}</div>` : ''}
            ${post.recommendations ? `<div class="ch-row"><b>Recommendations:</b> ${escHtml(post.recommendations)}</div>` : ''}
            ${post.prescription    ? `<div class="ch-row"><b>Prescription:</b> ${escHtml(post.prescription)}</div>` : ''}
            ${post.follow_up_days  ? `<div class="ch-row"><b>Follow-up:</b> in ${post.follow_up_days} day(s)</div>` : ''}
            ${post.doctor_name     ? `<div class="ch-row muted small">By ${escHtml(post.doctor_name)} · ${escHtml(post.finalized_at || '')}</div>` : ''}
            <button class="btn ghost small ch-pdf-btn" data-id="${c.id}">Download PDF</button>
          </div>` : `<p class="muted small">No summary recorded yet.</p>`;

        return `<article class="ch-item">
          <header class="ch-head">
            <span class="pill ${pillCls}">${escHtml(status)}</span>
            <span class="muted small">${escHtml(fmtDate(c.requested_at))}${c.doctor_name ? ' · Dr ' + escHtml(c.doctor_name) : ''}</span>
          </header>
          ${preHtml}
          ${postHtml}
        </article>`;
      }).join('');

      list.querySelectorAll('.ch-pdf-btn').forEach(b => {
        b.addEventListener('click', () => {
          const id = b.dataset.id;
          window.open('../backend/api/telemed-prescription.php?id=' + encodeURIComponent(id), '_blank');
        });
      });
    } catch (_) { /* silent */ }
  }

  async function onSubmit(ev) {
    ev.preventDefault();
    const form = ev.target;
    const msg = document.getElementById('diary-form-msg');
    if (msg) msg.textContent = 'Saving…';
    const fd = new FormData(form);
    const body = {
      diary_date     : new Date().toISOString().slice(0, 10),
      mood           : Number(fd.get('mood')),
      cough          : Number(fd.get('cough')),
      breath_diff    : Number(fd.get('breath_diff')),
      eye_irritation : Number(fd.get('eye_irritation')),
      headache       : Number(fd.get('headache')),
      fatigue        : Number(fd.get('fatigue')),
      notes          : (fd.get('notes') || '').toString(),
    };
    const r = await fetch('../backend/api/diary.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await r.json().catch(() => ({}));
    if (data.ok) {
      if (msg) msg.textContent = 'Saved.';
      refresh();
    } else if (msg) {
      msg.textContent = data.error || 'Save failed.';
    }
    setTimeout(() => msg && (msg.textContent = ''), 2200);
  }

  /* ============================================================
   * AI Insights — analyses 30-day chart/history and gives advice
   * ============================================================ */
  function fmtTime(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleString(undefined, { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' });
  }

  function setListItems(ulId, items) {
    const ul = document.getElementById(ulId);
    if (!ul) return;
    if (!items || !items.length) {
      ul.innerHTML = '<li class="muted small">—</li>';
      return;
    }
    ul.innerHTML = items.map(t => `<li>${escHtml(t)}</li>`).join('');
  }

  async function runDiaryAI() {
    const btn      = document.getElementById('dai-run');
    const empty    = document.getElementById('dai-empty');
    const loading  = document.getElementById('dai-loading');
    const result   = document.getElementById('dai-result');
    if (!btn || !loading || !result) return;

    btn.disabled = true;
    if (empty)   empty.hidden = true;
    if (result)  result.hidden = true;
    loading.hidden = false;

    try {
      const r = await fetch('../backend/api/diary-ai.php', { credentials: 'same-origin' });
      const d = await r.json();
      loading.hidden = true;
      if (!d || !d.ok) {
        if (empty) {
          empty.hidden = false;
          empty.innerHTML = '<p class="muted">AI service unavailable. Try again later.</p>';
        }
        return;
      }
      renderDiaryAI(d);
    } catch (e) {
      loading.hidden = true;
      if (empty) {
        empty.hidden = false;
        empty.innerHTML = '<p class="muted">Network error — please retry.</p>';
      }
    } finally {
      btn.disabled = false;
    }
  }

  function renderDiaryAI(d) {
    const result = document.getElementById('dai-result');
    if (!result) return;

    const ins = d.insights || {};
    const risk = (ins.risk_level || 'low').toLowerCase();
    const riskEl = document.getElementById('dai-risk');
    if (riskEl) {
      riskEl.textContent = risk;
      riskEl.classList.remove('lvl-low', 'lvl-moderate', 'lvl-high');
      riskEl.classList.add('lvl-' + risk);
    }

    const sumEl = document.getElementById('dai-summary');
    if (sumEl) sumEl.textContent = ins.summary || '—';

    setListItems('dai-trends',   ins.trends);
    setListItems('dai-warnings', ins.warnings);
    setListItems('dai-actions',  ins.actions);

    const meta = document.getElementById('dai-meta');
    if (meta) {
      const stats = d.stats || {};
      meta.textContent = `Based on ${stats.entries || 0} entries · updated ${fmtTime(d.generated_at)}`;
    }
    const src = document.getElementById('dai-source');
    if (src) {
      const baseLabel = d.source === 'groq'
        ? 'Generated by Nafass AI (Groq · llama-3.3)'
        : d.source === 'no-data'
          ? 'No diary entries yet'
          : 'Generated by local heuristics (AI offline)';
      const fz = d.fuzzy;
      src.textContent = baseLabel + (fz && typeof fz.risk_score !== 'undefined'
        ? ` · Fuzzy Type-2 + IA · score ${Number(fz.risk_score).toFixed(1)}/100 (${fz.urgency_level})`
        : '');
    }

    /* Show the fired fuzzy rules so the diary AI is clearly fuzzy-backed */
    const fz = d.fuzzy;
    if (fz && Array.isArray(fz.fired_rules) && fz.fired_rules.length) {
      let panel = document.getElementById('dai-fuzzy');
      if (!panel) {
        panel = document.createElement('div');
        panel.id = 'dai-fuzzy';
        panel.style.cssText = 'margin-top:10px;padding:10px 12px;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border:1px solid #bbf7d0;border-radius:10px;font-size:12.5px;color:#065f46;';
        result.appendChild(panel);
      }
      const items = fz.fired_rules.slice(0, 3).map(r => {
        const pct = Math.round(((r && r.activation) || 0) * 100);
        const lbl = (r && (r.label || r.consequent)) || '';
        const safe = String(lbl).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return `<li><b>R${r.id}</b> · ${pct}% — ${safe}</li>`;
      }).join('');
      panel.innerHTML =
        `<div style="margin-bottom:6px;"><span style="background:#047857;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;">Fuzzy Type-2 + IA</span> <b>score ${Number(fz.risk_score).toFixed(1)}/100 · ${fz.urgency_level}</b></div>`
        + `<ul style="list-style:none;padding:0;margin:0;">${items}</ul>`;
    }

    result.hidden = false;
  }

  window.initDiary = function () {
    const lbl = document.getElementById('diary-date-label');
    if (lbl) lbl.textContent = fmtToday();
    const form = document.getElementById('diary-form');
    if (form) {
      bindRanges(form);
      form.addEventListener('submit', onSubmit);
    }
    const aiBtn = document.getElementById('dai-run');
    if (aiBtn) aiBtn.addEventListener('click', runDiaryAI);
    refresh();
  };
})();
