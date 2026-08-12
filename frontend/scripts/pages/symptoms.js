/* =============================================================
   Suivi des symptômes — vue citoyen (form + mes discussions)
   et vue santé/admin (liste + chat avec filtres status).
   Pas de framework : DOM natif + GT.api.
   ============================================================= */

async function initSymptoms() {
  const role = (window.GT_USER && GT_USER.role) || 'citizen';
  const isStaff = role === 'health' || role === 'admin';

  // bascule des deux vues
  document.getElementById('symp-citizen-view').hidden = isStaff;
  document.getElementById('symp-staff-view').hidden   = !isStaff;

  if (isStaff) {
    await initStaffView();
  } else {
    await initCitizenView();
  }
}

/* =============================================================
   Helpers UI
   ============================================================= */
function formatDate(s) {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T'));
  return d.toLocaleString('en-US', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit', hour12: false });
}
function timeAgo(s) {
  if (!s) return '';
  const d = new Date(s.replace(' ', 'T'));
  const diffM = Math.round((Date.now() - d.getTime()) / 60000);
  if (diffM < 1) return 'just now';
  if (diffM < 60) return `${diffM} min ago`;
  const h = Math.round(diffM / 60);
  if (h < 24) return `${h} h ago`;
  const j = Math.round(h / 24);
  return `${j} d ago`;
}
function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
function severityPill(sev) {
  const cls = sev === 'severe' ? 'danger' : sev === 'moderate' ? 'warning' : 'safe';
  const lbl = sev === 'severe' ? 'Severe' : sev === 'moderate' ? 'Moderate' : 'Mild';
  return `<span class="pill ${cls}">${lbl}</span>`;
}
function statusPill(st) {
  const lbl = ({ new: 'New', in_progress: 'In progress', resolved: 'Resolved' })[st] || st || '—';
  return `<span class="pill status-${st}">${lbl}</span>`;
}
function autoGrow(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 140) + 'px';
}

/* =============================================================
   VUE CITOYEN
   ============================================================= */
async function initCitizenView() {
  const zones = (await GT.api.get('zones.php')).zones;
  document.getElementById('symp-zone').innerHTML =
    zones.map(z => `<option value="${z.id}">${escapeHtml(z.name)}</option>`).join('');

  await refreshCitizen();

  document.getElementById('form-symptom').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.target).entries());
    body.citizen_name = body.citizen_name || (window.GT_USER && (GT_USER.full_name || GT_USER.username)) || 'Anonymous';
    try {
      await GT.api.post('symptoms.php', body);
      document.getElementById('symp-status').innerHTML = '<span class="pill safe">✓ Symptom recorded</span>';
      e.target.reset();
      await refreshCitizen();
    } catch (err) {
      document.getElementById('symp-status').innerHTML = '<span class="pill danger">Error</span>';
    }
  });

  // chat citoyen
  document.getElementById('sc-c-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const txt = document.getElementById('sc-c-input').value.trim();
    if (!txt || !window._scCitizenSymptomId) return;
    try {
      await GT.api.post('symptom-chat.php', {
        action: 'send',
        symptom_id: window._scCitizenSymptomId,
        message: txt,
      });
      document.getElementById('sc-c-input').value = '';
      autoGrow(document.getElementById('sc-c-input'));
      await openCitizenThread(window._scCitizenSymptomId, true);
    } catch (err) {
      alert('Could not send message: ' + err.message);
    }
  });
  document.getElementById('sc-c-input').addEventListener('input', (e) => autoGrow(e.target));
  document.getElementById('sc-c-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('sc-c-form').requestSubmit();
    }
  });

  // refresh polling pour récupérer nouveaux messages santé.
  // On coupe automatiquement si la page Symptoms n'est plus dans le DOM.
  if (window._scCitizenInterval) clearInterval(window._scCitizenInterval);
  window._scCitizenInterval = setInterval(() => {
    if (!document.getElementById('sc-my-list')) {
      clearInterval(window._scCitizenInterval);
      window._scCitizenInterval = null;
      return;
    }
    refreshCitizenThreads();
    if (window._scCitizenSymptomId) openCitizenThread(window._scCitizenSymptomId, false);
  }, 15000);
}

async function refreshCitizen() {
  const data = await GT.api.get('symptoms.php');
  const stats = { mild: 0, moderate: 0, severe: 0 };
  data.stats.forEach(s => stats[s.severity] = +s.c);
  document.getElementById('symp-stats').innerHTML = `
    <div class="card"><h3>Mild</h3><div class="value">${stats.mild}</div></div>
    <div class="card"><h3>Moderate</h3><div class="value" style="color:var(--warning)">${stats.moderate}</div></div>
    <div class="card"><h3>Severe</h3><div class="value" style="color:var(--danger)">${stats.severe}</div></div>
  `;
  document.getElementById('symp-feed').innerHTML = data.symptoms.slice(0, 10).map(s => {
    const triage = s.triage_text
      ? `<div class="symp-triage symp-triage-${escapeHtml(s.triage_urgency || 'low')}">
           <div class="symp-triage-head">
             <span class="badge">AI Triage · ${escapeHtml(s.triage_urgency || 'low')}</span>
             <span class="muted small">${formatDate(s.triage_at)}</span>
           </div>
           <div class="symp-triage-body">${escapeHtml(s.triage_text)}</div>
         </div>`
      : `<button type="button" class="btn ghost small symp-triage-btn" data-id="${s.id}">
           Request AI advice
         </button>
         <span class="symp-triage-status muted small" data-status="${s.id}"></span>`;
    return `
    <div class="symp-row" data-id="${s.id}" style="padding:8px 0;border-bottom:1px solid var(--border-2)">
      <div class="row between">
        <div><strong>${escapeHtml(s.symptom)}</strong> <span class="muted">· ${escapeHtml(s.zone_name || '—')}</span></div>
        ${severityPill(s.severity)}
      </div>
      <div class="muted" style="font-size:12px">${formatDate(s.reported_at)}${s.notes ? ' · ' + escapeHtml(s.notes) : ''}</div>
      ${triage}
    </div>`;
  }).join('') || '<div class="empty">No symptom</div>';

  // Bind triage buttons
  document.querySelectorAll('.symp-triage-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const statusEl = document.querySelector(`[data-status="${id}"]`);
      btn.disabled = true;
      if (statusEl) statusEl.textContent = 'Analyzing… (~5s)';
      try {
        const r = await fetch('../backend/api/triage.php', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ symptom_id: Number(id) }),
        });
        const j = await r.json();
        if (j.ok) {
          if (statusEl) {
            // Surface the fuzzy-logic result inline so the user knows the
            // Mamdani engine is part of the AI advice (not just LLM text).
            if (j.fuzzy && typeof j.fuzzy.risk_score !== 'undefined') {
              statusEl.innerHTML = `<span class="badge" style="background:#047857;color:#fff;font-size:11px;">Fuzzy ${Number(j.fuzzy.risk_score).toFixed(1)} · ${j.fuzzy.urgency_level}</span>`;
            } else {
              statusEl.textContent = '';
            }
          }
          await refreshCitizen();
        } else if (statusEl) {
          statusEl.textContent = j.error || 'Unavailable';
          btn.disabled = false;
        }
      } catch (e) {
        if (statusEl) statusEl.textContent = 'Network error';
        btn.disabled = false;
      }
    });
  });

  await refreshCitizenThreads();
}

async function refreshCitizenThreads() {
  const list = document.getElementById('sc-my-list');
  if (!list) return;
  let data;
  try { data = await GT.api.get('symptom-chat.php', { action: 'my' }); }
  catch (e) { return; }
  const threads = (data && data.threads) || [];
  if (!threads.length) {
    list.innerHTML = '<div class="empty">No open conversation with the health team.</div>';
    document.getElementById('sc-citizen-chat').style.display = 'none';
    window._scCitizenSymptomId = null;
    return;
  }
  list.innerHTML = threads.map(t => `
    <div class="sc-mythread ${window._scCitizenSymptomId == t.id ? 'active' : ''}" data-id="${t.id}">
      <div class="left">
        <div class="symp">${escapeHtml(t.symptom)} ${severityPill(t.severity)} ${statusPill(t.status || 'in_progress')}</div>
        <div class="meta">
          ${escapeHtml(t.zone_name || '—')} · ${formatDate(t.reported_at)}
          ${t.last_msg ? ` · « ${escapeHtml((t.last_msg || '').slice(0, 80))} »` : ''}
        </div>
      </div>
      <div class="right">
        ${+t.unread > 0 ? `<span class="unread-dot" title="${t.unread} unread"></span>` : ''}
        <span class="muted" style="font-size:11px">${timeAgo(t.last_msg_at || t.reported_at)}</span>
      </div>
    </div>
  `).join('');

  list.querySelectorAll('.sc-mythread').forEach(el => {
    el.addEventListener('click', () => openCitizenThread(+el.dataset.id, true));
  });
}

async function openCitizenThread(symptomId, scroll) {
  window._scCitizenSymptomId = symptomId;
  document.querySelectorAll('#sc-my-list .sc-mythread').forEach(x =>
    x.classList.toggle('active', +x.dataset.id === +symptomId));

  const wrap = document.getElementById('sc-citizen-chat');
  wrap.style.display = 'flex';
  wrap.style.flexDirection = 'column';

  let data;
  try {
    data = await GT.api.get('symptom-chat.php', { action: 'thread', symptom_id: symptomId });
  } catch (err) {
    document.getElementById('sc-c-log').innerHTML =
      `<div class="empty">Conversation unavailable (${err.message}).</div>`;
    return;
  }

  const sym = data.symptom;
  document.getElementById('sc-c-avatar').textContent = 'N';
  document.getElementById('sc-c-title').textContent = `Nafass Health Team`;
  document.getElementById('sc-c-sub').innerHTML =
    `${escapeHtml(sym.symptom)} · ${escapeHtml(sym.zone_name || '—')} · ${statusPill(sym.status)}`;

  renderMessages(document.getElementById('sc-c-log'), data.messages, 'citizen', sym);
  if (scroll) {
    const log = document.getElementById('sc-c-log');
    log.scrollTop = log.scrollHeight;
  }
}

/* =============================================================
   VUE SANTÉ / ADMIN
   ============================================================= */
async function initStaffView() {
  // filtres
  document.querySelectorAll('#sc-filters .sc-filter').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('#sc-filters .sc-filter').forEach(x => x.classList.remove('active'));
      el.classList.add('active');
      window._scStatus = el.dataset.status;
      refreshStaffList();
    });
  });
  window._scStatus = 'all';

  // formulaire de réponse
  document.getElementById('sc-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const txt = document.getElementById('sc-input').value.trim();
    if (!txt || !window._scCurrent) return;
    try {
      await GT.api.post('symptom-chat.php', {
        action: 'send',
        symptom_id: window._scCurrent,
        message: txt,
      });
      document.getElementById('sc-input').value = '';
      autoGrow(document.getElementById('sc-input'));
      await openStaffThread(window._scCurrent, true);
      await refreshStaffList();
    } catch (err) {
      alert('Could not send: ' + err.message);
    }
  });
  document.getElementById('sc-input').addEventListener('input', (e) => autoGrow(e.target));
  document.getElementById('sc-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('sc-form').requestSubmit();
    }
  });

  // changement de status
  document.getElementById('sc-status').addEventListener('change', async (e) => {
    if (!window._scCurrent) return;
    try {
      await GT.api.post('symptom-chat.php', {
        action: 'set_status',
        symptom_id: window._scCurrent,
        status: e.target.value,
      });
      await refreshStaffList();
    } catch (err) {
      alert('Status error: ' + err.message);
    }
  });

  // stats globales
  refreshStaffStats();

  // liste + polling
  await refreshStaffList();
  if (window._scStaffInterval) clearInterval(window._scStaffInterval);
  window._scStaffInterval = setInterval(() => {
    // Auto-stop quand l'utilisateur navigue ailleurs (DOM remplacé).
    if (!document.getElementById('sc-threads')) {
      clearInterval(window._scStaffInterval);
      window._scStaffInterval = null;
      return;
    }
    refreshStaffList();
    if (window._scCurrent) openStaffThread(window._scCurrent, false);
  }, 15000);
}

async function refreshStaffStats() {
  // Vue staff/admin uniquement — id séparé de la vue citoyen pour éviter
  // que getElementById retourne le mauvais div quand les deux coexistent
  // (l'un visible, l'autre hidden).
  const target = document.getElementById('symp-staff-stats');
  if (!target) return;
  try {
    const data = await GT.api.get('symptoms.php');
    const stats = { mild: 0, moderate: 0, severe: 0 };
    (data.stats || []).forEach(s => stats[s.severity] = +s.c);
    target.innerHTML = `
      <div class="card"><h3>Mild</h3><div class="value">${stats.mild}</div></div>
      <div class="card"><h3>Moderate</h3><div class="value" style="color:var(--warning)">${stats.moderate}</div></div>
      <div class="card"><h3>Severe</h3><div class="value" style="color:var(--danger)">${stats.severe}</div></div>
    `;
  } catch (e) {
    target.innerHTML = `<div class="empty">Stats unavailable: ${e.message}</div>`;
  }
}

async function refreshStaffList() {
  // Si la page Symptoms n'est plus dans le DOM (l'utilisateur a navigué
  // ailleurs), on coupe le polling et on sort proprement.
  const threadsEl = document.getElementById('sc-threads');
  if (!threadsEl) {
    if (window._scStaffInterval) {
      clearInterval(window._scStaffInterval);
      window._scStaffInterval = null;
    }
    return;
  }

  const status = window._scStatus || 'all';
  let data;
  try {
    data = await GT.api.get('symptom-chat.php', { action: 'threads', status });
  } catch (err) {
    threadsEl.innerHTML = `<div class="empty">Error: ${err.message}</div>`;
    return;
  }
  // compteurs pills (guards : vue citoyen → ces IDs n'existent pas)
  const c = data.counts || {};
  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  setText('sc-f-all',         c.all          ?? 0);
  setText('sc-f-new',         c.new          ?? 0);
  setText('sc-f-in_progress', c.in_progress  ?? 0);
  setText('sc-f-resolved',    c.resolved     ?? 0);
  setText('sc-list-meta',     `${data.threads.length} report(s)`);

  if (!data.threads.length) {
    threadsEl.innerHTML =
      '<div class="empty">No symptom in this category.</div>';
    return;
  }

  threadsEl.innerHTML = data.threads.map(t => {
    const isPending = (t.status === 'new' && +t.health_msg_count === 0);
    const classes = [
      'sc-thread',
      window._scCurrent == t.id ? 'active' : '',
      isPending ? 'is-pending' : '',
    ].filter(Boolean).join(' ');
    return `
    <div class="${classes}" data-id="${t.id}">
      <div class="row1">
        <div class="name">${escapeHtml(t.citizen_name || 'Anonymous')}</div>
        ${statusPill(t.status)}
      </div>
      <div class="symp">
        ${escapeHtml(t.symptom)} ${severityPill(t.severity)}
      </div>
      <div class="meta-line">
        <span>${escapeHtml(t.zone_name || '—')}</span>
        <span>·</span>
        <span>${timeAgo(t.last_msg_at || t.reported_at)}</span>
      </div>
      ${t.last_msg
        ? `<div class="preview">« ${escapeHtml(t.last_msg)} »</div>`
        : '<div class="preview muted">— No reply yet —</div>'}
    </div>`;
  }).join('');

  document.querySelectorAll('#sc-threads .sc-thread').forEach(el => {
    el.addEventListener('click', () => openStaffThread(+el.dataset.id, true));
  });
}

async function openStaffThread(symptomId, scroll) {
  window._scCurrent = symptomId;
  document.querySelectorAll('#sc-threads .sc-thread').forEach(x =>
    x.classList.toggle('active', +x.dataset.id === +symptomId));

  let data;
  try {
    data = await GT.api.get('symptom-chat.php', { action: 'thread', symptom_id: symptomId });
  } catch (err) {
    return;
  }

  document.getElementById('sc-empty').style.display = 'none';
  document.getElementById('sc-chat').hidden = false;

  const sym = data.symptom;
  document.getElementById('sc-avatar').textContent = initials(sym.citizen_name || 'A');
  document.getElementById('sc-title').textContent = sym.citizen_name || 'Anonymous';
  document.getElementById('sc-sub').innerHTML =
    `${escapeHtml(sym.symptom)} · ${escapeHtml(sym.zone_name || '—')} · ${severityPill(sym.severity)}
     ${sym.notes ? `· <span class="muted">${escapeHtml(sym.notes)}</span>` : ''}`;
  document.getElementById('sc-status').value = sym.status || 'new';

  renderMessages(document.getElementById('sc-log'), data.messages, 'staff', sym);
  if (scroll) {
    const log = document.getElementById('sc-log');
    log.scrollTop = log.scrollHeight;
  }
}

/* =============================================================
   Rendu des messages (commun aux deux vues)
   viewer = 'citizen' ou 'staff' — détermine quel côté est "user"
   ============================================================= */
function renderMessages(container, messages, viewer, sym) {
  let html = '';

  // Carte « Symptôme initial » : rappel du contexte en haut du log
  if (sym) {
    html += `
      <div class="sc-initial-card">
        <span class="sc-initial-pill">Initial symptom</span>
        <div class="sc-initial-body">
          <strong>${escapeHtml(sym.symptom || '—')}</strong>
          ${sym.severity ? severityPill(sym.severity) : ''}
          ${sym.notes ? `<div class="sc-initial-note">${escapeHtml(sym.notes)}</div>` : ''}
        </div>
      </div>`;
  }

  if (!messages.length) {
    container.innerHTML = html + `
      <div class="sc-empty" style="margin:auto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <div class="t">No message</div>
        <div class="s">${viewer === 'staff'
          ? 'Send a first message — the conversation will then become visible to the citizen.'
          : 'The health team has not replied yet.'}</div>
      </div>`;
    return;
  }

  let lastDay = '';
  let prevRole = null;
  let prevTime = 0;

  for (const m of messages) {
    const d = new Date(String(m.created_at).replace(' ', 'T'));
    const dayKey = d.toLocaleDateString('en-US', { day: '2-digit', month: 'long', year: 'numeric' });
    if (dayKey !== lastDay) {
      html += `<div class="sc-msg-day">${dayKey}</div>`;
      lastDay = dayKey;
      prevRole = null;
    }

    const isOwn = (viewer === 'staff' && (m.sender_role === 'health' || m.sender_role === 'admin'))
               || (viewer === 'citizen' && m.sender_role === 'citizen');
    const side = isOwn ? 'from-staff' : 'from-citizen';

    // libellé d'auteur (préfère le nom complet du compte si dispo)
    const who = m.sender_role === 'health' ? (m.sender_name || 'Health Team')
              : m.sender_role === 'admin'  ? (m.sender_name || 'Administrator')
              : (m.sender_name || 'Citizen');

    // initiales pour l'avatar circulaire
    const av = m.sender_role === 'health' ? 'DR'
             : m.sender_role === 'admin'  ? 'AD'
             : initials(m.sender_name || 'Citizen');
    const avClass = (m.sender_role === 'health' || m.sender_role === 'admin')
                    ? 'sc-av-staff' : 'sc-av-citizen';

    const time = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });

    // groupage : msgs consécutifs du même auteur en < 5 min → on cache avatar + meta
    const sameGroup = (m.sender_role === prevRole) && (d.getTime() - prevTime < 5 * 60 * 1000);

    const readMark = (isOwn && m.read_at) ? ' <span class="read-check" title="Read">✓✓</span>' : '';

    html += `
      <div class="sc-msg-row ${side}${sameGroup ? ' is-grouped' : ''}">
        <div class="sc-avatar-msg ${avClass}">${sameGroup ? '' : escapeHtml(av)}</div>
        <div class="sc-msg-block">
          <div class="sc-msg">${escapeHtml(m.message)}</div>
          ${sameGroup ? '' : `
            <div class="sc-msg-meta">
              <span class="who">${escapeHtml(who)}</span>
              <span>·</span>
              <span class="when">${time}</span>${readMark}
            </div>`}
        </div>
      </div>`;

    prevRole = m.sender_role;
    prevTime = d.getTime();
  }

  container.innerHTML = html;
}
