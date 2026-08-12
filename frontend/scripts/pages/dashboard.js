/* Main dashboard — auto-refreshes every 20s and listens to the
   'gt:alerts-updated' event dispatched by the notifications bell so it
   reflects newly created alerts in real time. */

let _dashRefreshTimer = null;
let _dashListenerAttached = false;

function _stripAutoTag(title) {
  return (title || '').replace(/^\[AUTO:[^\]]+\]\s*/, '');
}

function _dashEsc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

async function loadDashboardData() {
  try {
    const d = await GT.api.get('dashboard.php');

    // Risk score
    document.getElementById('dash-avg').textContent = d.avg_risk;
    const bar = document.getElementById('dash-avg-bar');
    if (bar) {
      bar.style.width = (+d.avg_risk || 0) + '%';
      // Couleur de la barre selon le statut
      const colors = { safe: '#16a34a', warning: '#d97706', critical: '#dc2626' };
      bar.style.background = colors[d.global_status] || '#0d3b66';
    }

    // Global status
    const status = document.getElementById('dash-status');
    const statusLabels = { safe: 'Normal conditions', warning: 'Watch', critical: 'Critical' };
    if (status) {
      status.textContent = statusLabels[d.global_status] || (d.global_status || '—');
      const colors = { safe: '#166534', warning: '#92400e', critical: '#991b1b' };
      status.style.color = colors[d.global_status] || '#0f172a';
    }
    const hint = document.getElementById('dash-status-hint');
    if (hint) hint.textContent = (d.recommendations || '').slice(0, 80) + ((d.recommendations || '').length > 80 ? '…' : '');

    // Compteurs
    document.getElementById('dash-alerts').textContent   = d.counts.alerts ?? '—';
    document.getElementById('dash-reports').textContent  = d.counts.reports ?? '—';
    document.getElementById('dash-symptoms').textContent = d.counts.symptoms ?? '—';
    document.getElementById('dash-schools').textContent  = d.counts.schools_danger ?? '—';
    document.getElementById('dash-zones').textContent    = d.counts.zones ?? '—';

    // Top zones
    const tzWrap = document.getElementById('dash-top-zones');
    if (tzWrap) {
      tzWrap.innerHTML = (d.top_zones || []).map((z, i) => `
        <div class="dash-zone">
          <div class="dz-rank">${i + 1}</div>
          <div class="dz-body">
            <div class="dz-name">${_dashEsc(z.name)}</div>
            <div class="dz-name-ar">${_dashEsc(z.name_ar || '')}</div>
          </div>
          <div class="dz-bar"><span class="${z.status}" style="width:${+z.pollution_level || 0}%"></span></div>
          <span class="pill ${z.status}">${+z.pollution_level || 0}%</span>
        </div>
      `).join('') || '<div class="empty">No zones to display.</div>';
    }

    // Alertes récentes
    const arWrap = document.getElementById('dash-recent-alerts');
    if (arWrap) {
      const items = d.recent_alerts || [];
      arWrap.innerHTML = items.length === 0
        ? '<div class="empty">No recent alerts.</div>'
        : items.map(a => `
          <div class="dash-alert ${a.severity || 'info'}">
            <div class="da-left"></div>
            <div class="da-body">
              <div class="da-title">${_dashEsc(_stripAutoTag(a.title))}</div>
              <div class="da-meta">${_dashEsc(a.zone_name || '—')} · ${GT.fmt.date(a.created_at)} · <span style="color:#475569;font-weight:600">${a.severity}</span></div>
            </div>
          </div>`).join('');
    }

    // Static recommendations (from backend dashboard.php)
    const reco = document.getElementById('dash-reco-static');
    if (reco) reco.textContent = d.recommendations || '—';

    // Automatic AI recommendation = AI model + Fuzzy Type-2 (all roles)
    renderAiReco(d.ai_reco);

    drawTrend(d.trend);
  } catch (e) {
    const view = document.getElementById('view');
    if (view && !view.querySelector('#dash-recent-alerts')) {
      view.innerHTML = `
        <div class="card"><h3>Connection failed</h3>
        <p>Unable to reach the PHP backend. Make sure <b>WAMP is running</b> and that the <code>gabes_tatenafas</code> database has been imported.</p>
        <p class="muted">${_dashEsc(e.message)}</p></div>`;
    }
  }
}

/* Automatic recommendation card driven by the AI model + Fuzzy Type-2
   result returned by dashboard.php (visible for every role). */
function renderAiReco(ai) {
  const card = document.getElementById('dash-ai-reco');
  if (!card || !ai) return;
  card.hidden = false;
  const clsMap = { low: 'safe', moderate: 'warning', high: 'warning', critical: 'critical' };
  const risk = document.getElementById('dar-risk');
  if (risk) {
    risk.textContent = (ai.urgency_level || '—') + ' · ' + Number(ai.risk_score || 0).toFixed(0) + '/100';
    risk.className = 'pill dar-risk ' + (clsMap[ai.urgency_level] || '');
  }
  const m = ai.model || {};
  const mdl = document.getElementById('dar-model');
  if (mdl) mdl.textContent = (m.best_model || 'AI') + (m.trained ? '' : ' · non entraîné');
  const txt = document.getElementById('dar-text');
  if (txt) txt.textContent = ai.explanation || '—';
  const acts = document.getElementById('dar-actions');
  if (acts) acts.innerHTML = (ai.actions || []).slice(0, 4).map(a => `<li>${_dashEsc(a)}</li>`).join('');
  const t2 = ai.type2 || {};
  const meta = document.getElementById('dar-meta');
  if (meta) meta.textContent =
    `Fuzzy Type-2 (Karnik-Mendel): ${Number(t2.score || 0).toFixed(0)}/100 · uncertainty ±${Number(t2.uncertainty_band || 0).toFixed(0)} · ${t2.risk_level || ''}`;
}

async function initDashboard() {
  if (_dashRefreshTimer) { clearInterval(_dashRefreshTimer); _dashRefreshTimer = null; }
  await loadDashboardData();
  loadDailyTip();
  loadHealthRecommendation();
  loadTelemedQueue();   // H1 v2 — queue pour health/admin
  loadModelsSummary();  // Résultats de chaque modèle IA (admin/health)

  _dashRefreshTimer = setInterval(() => {
    if (document.getElementById('dash-recent-alerts')) {
      loadDashboardData();
      loadTelemedQueue();
    }
    else { clearInterval(_dashRefreshTimer); _dashRefreshTimer = null; }
  }, 20000);

  if (!_dashListenerAttached) {
    document.addEventListener('gt:alerts-updated', () => {
      if (document.getElementById('dash-recent-alerts')) loadDashboardData();
    });
    _dashListenerAttached = true;
  }
}

/* Résultats de chaque modèle IA en un seul endroit (admin/health).
   Lit backend/api/comparison.php et affiche RMSE / F1 / AUC / R² par modèle. */
async function loadModelsSummary() {
  const role = (window.GT_USER && window.GT_USER.role) || '';
  if (role !== 'admin' && role !== 'health') return;
  const card = document.getElementById('dash-models');
  const tbody = document.querySelector('#dash-models-table tbody');
  const tag = document.getElementById('dash-models-tag');
  if (!card || !tbody) return;
  const apiBase = (window.GT && GT.api && GT.api.base) ? GT.api.base : 'backend/api';
  try {
    const r = await fetch(apiBase + '/comparison.php', { credentials: 'same-origin' });
    const d = await r.json();
    if (!d || !d.ok || !Array.isArray(d.master)) { card.hidden = true; return; }
    card.hidden = false;
    if (tag) tag.textContent = d.demo ? 'NON ENTRAÎNÉ' : 'ENTRAÎNÉ';
    const num = (v, dg) => (v === null || v === undefined) ? '—' : Number(v).toFixed(dg);
    tbody.innerHTML = d.master.map(m => {
      const cls = m.recommended ? ' class="dash-model-best"' : (m.best ? ' class="dash-model-best"' : '');
      return '<tr' + cls + '><td>' + (m.model || '—')
        + (m.benchmark ? ' <span class="badge" style="font-size:9px">réf.</span>' : '')
        + '</td><td>' + num(m.rmse, 2) + '</td><td>' + num(m.f1, 3)
        + '</td><td>' + num(m.auc, 3) + '</td><td>' + num(m.r2, 3) + '</td></tr>';
    }).join('');
  } catch (e) {
    card.hidden = true;
  }
}

/* Telemedicine consultation panel
   - health  → live queue with Join/Resume buttons
   - admin   → monitoring panel (read-only, includes recent history) */
async function loadTelemedQueue() {
  const role = (window.GT_USER && window.GT_USER.role) || '';
  if (role !== 'health' && role !== 'admin') return;
  const card  = document.getElementById('dash-telemed-queue');
  const list  = document.getElementById('dash-telemed-list');
  const count = document.getElementById('dash-telemed-count');
  const empty = document.getElementById('dash-telemed-empty');
  if (!card || !list) return;
  card.hidden = false;

  // Adjust card title for role
  const titleEl = card.querySelector('.dtq-title strong');
  if (titleEl) {
    titleEl.textContent = role === 'admin'
      ? 'Consultation Activity (monitoring)'
      : 'Pending Telemedicine Requests';
  }

  try {
    const r = await fetch('../backend/api/telemed-request.php', { credentials: 'same-origin' });
    const d = await r.json();
    let reqs = d.requests || [];

    if (role === 'health') {
      reqs = reqs.filter(x => x.status === 'waiting' || x.status === 'joined');
    }

    if (count) {
      count.textContent = String(
        role === 'health'
          ? reqs.length
          : reqs.filter(x => x.status === 'waiting' || x.status === 'joined').length
      );
    }

    if (!reqs.length) {
      list.innerHTML = '';
      if (empty) {
        empty.hidden = false;
        empty.textContent = role === 'admin'
          ? 'No consultation activity yet.'
          : 'No pending requests.';
      }
      return;
    }
    if (empty) empty.hidden = true;

    list.innerHTML = reqs.map(rq => {
      const remain = Math.max(0, Number(rq.seconds_remaining || 0));
      const mm = String(Math.floor(remain / 60)).padStart(2, '0');
      const ss = String(remain % 60).padStart(2, '0');

      // Map status → human label + pill class
      const statusMap = {
        waiting:  { label: 'Waiting',     cls: 'dtq-pill-waiting' },
        joined:   { label: 'In progress', cls: 'dtq-pill-joined'  },
        closed:   { label: 'Completed',   cls: 'safe'             },
        expired:  { label: 'Expired',     cls: 'warning'          },
      };
      const st = statusMap[rq.status] || { label: rq.status, cls: '' };

      // Optional pre-consult chips (vitals reported by the citizen).
      let preChips = '';
      try {
        const pre = rq.pre_consult ? JSON.parse(rq.pre_consult) : null;
        if (pre) {
          const parts = [];
          if (pre.temperature != null) parts.push(`<span class="dtq-chip">🌡 ${pre.temperature}°C</span>`);
          if (pre.pulse       != null) parts.push(`<span class="dtq-chip">❤ ${pre.pulse} bpm</span>`);
          if (pre.oxygen_sat  != null) parts.push(`<span class="dtq-chip">O₂ ${pre.oxygen_sat}%</span>`);
          if (pre.symptoms)            parts.push(`<span class="dtq-chip dtq-chip-soft">${_dashEsc(pre.symptoms.slice(0, 60))}</span>`);
          if (parts.length) preChips = `<div class="dtq-chips">${parts.join('')}</div>`;
        }
      } catch (_) {}

      let trailing;
      if (role === 'admin') {
        // Admin: read-only, show provider name if joined
        const who = rq.health_name || rq.health_username || '—';
        trailing = `<span class="muted small" style="margin-left:6px">Doctor: ${_dashEsc(who)}</span>`;
      } else {
        // Health staff: Join / Resume + Finalize for active requests
        const btnLabel = rq.status === 'joined' ? 'Resume' : 'Join';
        const showFinalize = (rq.status === 'joined' || rq.status === 'waiting');
        trailing =
          `<button class="btn primary small dtq-join-btn" data-id="${rq.id}">${btnLabel}</button>` +
          (showFinalize ? `<button class="btn ghost small dtq-finalize-btn" data-id="${rq.id}">Finalize</button>` : '');
      }

      const timeline = (rq.status === 'waiting' || rq.status === 'joined')
        ? `expires in ${mm}:${ss}`
        : (rq.requested_at ? GT.fmt.date(rq.requested_at) : '');

      return `<li class="dtq-item">
        <div class="dtq-info">
          <strong>${_dashEsc(rq.citizen_name || rq.citizen_username || 'Citizen')}</strong>
          <span class="muted small">${_dashEsc(rq.zone_name || 'Zone unspecified')} · ${timeline}</span>
          ${preChips}
        </div>
        <span class="pill ${st.cls}">${st.label}</span>
        ${trailing}
      </li>`;
    }).join('');

    if (role === 'health') {
      list.querySelectorAll('.dtq-join-btn').forEach(b => {
        b.addEventListener('click', () => joinTelemedRequest(Number(b.dataset.id)));
      });
      list.querySelectorAll('.dtq-finalize-btn').forEach(b => {
        b.addEventListener('click', () => openFinalizeModal(Number(b.dataset.id)));
      });
    }
  } catch (_) {}
}

/* Finalize modal — health staff records diagnosis + recommendations + prescription */
function openFinalizeModal(id) {
  // Single-shot modal, removed on close.
  const overlay = document.createElement('div');
  overlay.className = 'gt-modal-overlay';
  overlay.innerHTML = `
    <div class="gt-modal" role="dialog" aria-labelledby="fz-title">
      <div class="gt-modal-head">
        <h3 id="fz-title">Finalize Consultation</h3>
        <button class="gt-modal-close" type="button" aria-label="Close">×</button>
      </div>
      <div class="gt-modal-body">
        <label class="gt-modal-field">
          <span>Diagnosis (short)</span>
          <input id="fz-diag" type="text" maxlength="500" placeholder="e.g. Mild asthma exacerbation">
        </label>
        <label class="gt-modal-field">
          <span>Recommendations</span>
          <textarea id="fz-reco" rows="3" maxlength="1500" placeholder="Lifestyle, mask use, follow-up plan…"></textarea>
        </label>
        <label class="gt-modal-field">
          <span>Prescription (free text)</span>
          <textarea id="fz-rx" rows="3" maxlength="1500" placeholder="Medication, dose, duration"></textarea>
        </label>
        <label class="gt-modal-field">
          <span>Follow-up in (days, optional)</span>
          <input id="fz-fu" type="number" min="0" max="365" placeholder="7">
        </label>
      </div>
      <div class="gt-modal-actions">
        <button class="btn ghost gt-modal-cancel" type="button">Cancel</button>
        <button class="btn primary gt-modal-save" type="button">Save & close consultation</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  const close = () => overlay.remove();
  overlay.querySelector('.gt-modal-close').addEventListener('click', close);
  overlay.querySelector('.gt-modal-cancel').addEventListener('click', close);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

  overlay.querySelector('.gt-modal-save').addEventListener('click', async () => {
    const post = {
      diagnosis:       overlay.querySelector('#fz-diag').value.trim(),
      recommendations: overlay.querySelector('#fz-reco').value.trim(),
      prescription:    overlay.querySelector('#fz-rx').value.trim(),
      follow_up_days:  Number(overlay.querySelector('#fz-fu').value) || null,
    };
    try {
      const r = await fetch('../backend/api/telemed-request.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'finalize', id, post_consult: post }),
      });
      const d = await r.json();
      if (!d.ok) {
        alert('Could not save: ' + (d.error || 'unknown'));
        return;
      }
      close();
      loadTelemedQueue();
    } catch (_) {
      alert('Network error');
    }
  });
}

async function joinTelemedRequest(id) {
  try {
    const r = await fetch('../backend/api/telemed-request.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'join', id }),
    });
    const d = await r.json();
    if (!d.ok) {
      const msg =
        d.error === 'request_expired' ? 'The consultation has expired (15 min).'
        : d.error === 'request_closed'  ? 'The consultation has been closed.'
        : d.error === 'health_only'     ? 'Only health staff can join consultations.'
        : ('Error: ' + (d.error || 'unknown'));
      alert(msg);
      loadTelemedQueue();
      return;
    }
    // Open the Jitsi room in a dedicated window for the health professional
    const url = 'https://meet.jit.si/' + encodeURIComponent(d.room)
              + '#userInfo.displayName=' + encodeURIComponent((window.GT_USER && window.GT_USER.full_name) || 'Health Staff');
    window.open(url, 'gt_telemed', 'width=1100,height=720,resizable=yes,scrollbars=yes');
    loadTelemedQueue();
  } catch (e) {
    alert('Network error');
  }
}

/* Daily health tip (all roles, 24h server-side cache) */
async function loadDailyTip() {
  const card = document.getElementById('dash-tip');
  const txt  = document.getElementById('dash-tip-text');
  const tag  = document.getElementById('dash-tip-tag');
  if (!card || !txt) return;
  card.hidden = false; // toujours visible — fallback HTML déjà affiché
  try {
    const r = await fetch('../backend/api/tips.php?lang=en', { credentials: 'same-origin' });
    const data = await r.json();
    if (data.ok && data.tip) {
      txt.textContent = data.tip;
      if (tag) tag.textContent = data.cached ? 'AI · cache' : (data.fallback ? 'fallback' : 'AI · fresh');
    }
  } catch (_) {
    if (tag) tag.textContent = 'fallback';
  }
}

/* Helper — show a small panel with the fuzzy-logic reasoning so the user
   can verify the Mamdani engine is the one driving the recommendation. */
function renderFuzzyDetails(parent, fuzzy) {
  if (!parent || !fuzzy || typeof fuzzy.risk_score === 'undefined') return;
  let panel = parent.querySelector('.dash-reco-fuzzy');
  if (!panel) {
    panel = document.createElement('div');
    panel.className = 'dash-reco-fuzzy';
    parent.appendChild(panel);
  }
  const rules = Array.isArray(fuzzy.fired_rules) ? fuzzy.fired_rules : [];
  const items = rules.slice(0, 3).map(r => {
    const pct  = Math.round(((r && r.activation) || 0) * 100);
    const lbl  = (r && (r.label || r.consequent)) || '';
    return `<li><b>R${r.id}</b> · ${pct}% — ${escapeHtml(lbl)}</li>`;
  }).join('');
  panel.innerHTML =
    `<div class="dash-reco-fuzzy-hd">`
    + `<span class="dash-reco-fuzzy-tag">Fuzzy Type-2 + IA</span>`
    + `<span class="dash-reco-fuzzy-score">score ${Number(fuzzy.risk_score).toFixed(1)} / 100 · ${escapeHtml(fuzzy.urgency || fuzzy.urgency_level || '')}</span>`
    + `</div>`
    + (items ? `<ul class="dash-reco-fuzzy-rules">${items}</ul>` : '');
}
function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* Personalized recommendation (citizens only) */
async function loadHealthRecommendation() {
  const role = (window.GT_USER && window.GT_USER.role) || '';
  if (role !== 'citizen') return;
  const card  = document.getElementById('dash-reco');
  const txt   = document.getElementById('dash-reco-text');
  const badge = document.getElementById('dash-reco-risk');
  if (!card || !txt) return;
  try {
    const r = await fetch('../backend/api/recommendations.php', { credentials: 'same-origin' });
    const data = await r.json();
    // Backend returns both `recommendation`/`risk_level` and `reco`/`risk` —
    // accept either to be resilient to legacy shapes.
    const recoText = data.recommendation || data.reco;
    const riskLvl  = (data.risk_level || data.risk || 'low').toString().toLowerCase();
    if (data.ok && recoText) {
      card.hidden = false;
      txt.textContent = recoText;
      if (badge) {
        badge.textContent = riskLvl;
        badge.className   = 'badge dash-reco-badge dash-reco-risk-' + riskLvl;
      }
      // Inject the fuzzy-logic detail panel so the user can SEE that the
      // recommendation comes from a Mamdani engine (not the LLM alone).
      renderFuzzyDetails(card, data.fuzzy);
    } else {
      // Even on missing/empty payload we still hide the loading state with a fallback
      card.hidden = false;
      txt.textContent = 'No personalized recommendation available right now.';
      if (badge) {
        badge.textContent = 'low';
        badge.className   = 'badge dash-reco-badge dash-reco-risk-low';
      }
    }
  } catch (_) {
    card.hidden = false;
    txt.textContent = 'Unable to load recommendation (network error).';
    if (badge) {
      badge.textContent = 'low';
      badge.className   = 'badge dash-reco-badge dash-reco-risk-low';
    }
  }
}

function drawTrend(trend) {
  const svg = document.getElementById('dash-trend');
  if (!svg) return;
  const W = 600, H = 160, P = 24;
  if (!trend || trend.length === 0) {
    svg.innerHTML = `<text x="50%" y="50%" text-anchor="middle" fill="#9ca3af" font-size="13">No data</text>`;
    return;
  }
  const max = Math.max(...trend.map(t => t.c), 1);
  const step = (W - P * 2) / Math.max(trend.length - 1, 1);
  const points = trend.map((t, i) => {
    const x = P + i * step;
    const y = H - P - (t.c / max) * (H - P * 2);
    return [x, y, t.c];
  });
  const path = points.map((p, i) => (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  const area = path + ` L${points[points.length - 1][0]},${H - P} L${points[0][0]},${H - P} Z`;

  // Lignes de grille horizontales
  const gridLines = [0.25, 0.5, 0.75].map(f => {
    const y = P + f * (H - P * 2);
    return `<line x1="${P}" y1="${y.toFixed(1)}" x2="${W - P}" y2="${y.toFixed(1)}" stroke="#f1f5f9" stroke-width="1"/>`;
  }).join('');

  svg.innerHTML = `
    ${gridLines}
    <path d="${area}" fill="rgba(13,59,102,.08)"/>
    <path d="${path}" fill="none" stroke="#0d3b66" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${points.map(p => `<circle cx="${p[0]}" cy="${p[1]}" r="3.5" fill="#fff" stroke="#0d3b66" stroke-width="2"/>`).join('')}
    ${trend.map((t, i) => `<text x="${P + i * step}" y="${H - 6}" text-anchor="middle" fill="#94a3b8" font-size="10">${t.d.slice(5)}</text>`).join('')}
  `;
}
