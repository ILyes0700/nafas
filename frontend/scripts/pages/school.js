/* Mode école — auto-refresh + listener gt:alerts-updated.
   Quand une école change de statut (suspend/reactivate/set_status),
   le backend crée une alerte target=all donc TOUS les utilisateurs
   reçoivent la notification via la cloche dans les ~15s. */

let _schoolTimer = null;
let _schoolFilter = 'all';
let _schoolListenerAttached = false;

// Cache de la liste des écoles (pour le select du formulaire d'absence)
let _schoolsCache = [];
// Absences chargées (pour la liste)
let _absencesCache = [];

const ABSENCE_REASONS = {
  respiratoire: { label: 'Respiratory', cls: 'danger'  },
  asthme:       { label: 'Asthma',      cls: 'danger'  },
  fievre:       { label: 'Fever',       cls: 'warning' },
  allergie:     { label: 'Allergy',     cls: 'warning' },
  oculaire:     { label: 'Eye',         cls: 'warning' },
  digestif:     { label: 'Digestive',   cls: 'warning' },
  autre:        { label: 'Other',       cls: ''        },
  non_precise:  { label: 'Unspecified', cls: ''        },
};

function _ymd(d) {
  const dt = d instanceof Date ? d : new Date(d);
  const m = String(dt.getMonth() + 1).padStart(2, '0');
  const day = String(dt.getDate()).padStart(2, '0');
  return dt.getFullYear() + '-' + m + '-' + day;
}
function _escape(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function _stripAuto(t) { return (t || '').replace(/^\[AUTO:[^\]]+\]\s*/, ''); }

async function loadSchoolPage() {
  let dash, schools;
  try {
    [dash, schools] = await Promise.all([
      GT.api.get('dashboard.php'),
      GT.api.get('schools.php'),
    ]);
  } catch (e) {
    const grid = document.getElementById('schools-grid');
    if (grid) grid.innerHTML = '<div class="empty">Loading error.</div>';
    return;
  }

  const status = dash.global_status;
  const pill = document.getElementById('school-status-pill');
  if (pill) {
    pill.className = 'sh-badge ' + status;
    const label = ({ safe: 'Normal conditions', warning: 'Watch', critical: 'Critical' })[status] || status;
    pill.innerHTML = '<span class="dot"></span><span>' + label + '</span>';
  }
  const score = dash.avg_risk || 0;
  const scoreEl = document.getElementById('school-avg-score');
  if (scoreEl) scoreEl.textContent = score;
  const fill = document.getElementById('school-bar-fill');
  if (fill) fill.style.width = score + '%';
  const hint = document.getElementById('school-hint');
  if (hint) {
    hint.textContent = ({
      safe: 'All schools can operate normally.',
      warning: 'Limit outdoor activities in affected zones.',
      critical: 'Suspension recommended — protect vulnerable children.',
    })[status] || dash.recommendations || '';
  }

  // Bandeau critique
  const banner = document.getElementById('school-banner');
  const bMsg   = document.getElementById('school-banner-msg');
  if (banner && bMsg) {
    if (status === 'critical') {
      banner.style.display = 'flex';
      bMsg.textContent = `Average Risk Score ${score} — ${schools.summary.danger || 0} school(s) in danger, ${schools.summary.suspended || 0} suspended.`;
    } else {
      banner.style.display = 'none';
    }
  }

  // Stats
  const s = schools.summary || {};
  setText('ss-total',     s.total || 0);
  setText('ss-vig',       s.vigilance || 0);
  setText('ss-danger',    s.danger || 0);
  setText('ss-suspended', s.suspended || 0);
  setText('ss-absent',    s.absentees || 0);
  setText('ss-symptoms',  s.symptoms || 0);

  // Cache liste écoles pour le select du formulaire d'absence
  _schoolsCache = schools.schools || [];
  refreshSchoolSelect();

  // Absences du jour renvoyées par l'API GET
  if (Array.isArray(schools.absentees_today)) {
    _absencesCache = schools.absentees_today;
    const selectedDay = document.getElementById('absence-day')?.value;
    if (!selectedDay || selectedDay === _ymd(new Date())) {
      renderAbsenceList(_absencesCache);
    }
  }

  // Liste écoles (filtrée)
  const grid = document.getElementById('schools-grid');
  if (grid) {
    const filtered = _schoolFilter === 'all'
      ? schools.schools
      : schools.schools.filter(x => x.status === _schoolFilter);

    grid.innerHTML = filtered.length === 0
      ? '<div class="empty">No school matches this filter.</div>'
      : filtered.map(school => renderSchoolCard(school)).join('');
  }

  // Historique alertes
  const hist = document.getElementById('school-history-list');
  if (hist) {
    const al = schools.school_alerts || [];
    hist.innerHTML = al.length === 0
      ? '<div class="empty">No recent school alert.</div>'
      : al.map(a => `
          <div class="school-history-item">
            <div class="shi-dot ${a.severity}"></div>
            <div class="shi-body">
              <div class="shi-title">${_stripAuto(a.title)}</div>
              <div class="shi-meta">${a.zone_name || '—'} · ${GT.fmt.date(a.created_at)}</div>
              ${a.message ? `<div class="shi-msg">${a.message}</div>` : ''}
            </div>
          </div>`).join('');
  }
}

function renderSchoolCard(s) {
  const statusLabels = {
    normal:    { txt: 'Normal',    cls: 'safe'     },
    vigilance: { txt: 'Watch',     cls: 'warning'  },
    danger:    { txt: 'Danger',    cls: 'danger'   },
    suspended: { txt: 'Suspended', cls: 'critical' },
  };
  const lbl = statusLabels[s.status] || statusLabels.normal;

  const ICO = {
    suspend:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
    reopen:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    vigilance:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/></svg>',
    notify:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
  };
  const actions = [];
  if (s.status !== 'suspended') {
    actions.push(`<button class="btn danger" onclick="schoolAction(${s.id},'suspend')">${ICO.suspend}<span>Suspend</span></button>`);
  } else {
    actions.push(`<button class="btn" onclick="schoolAction(${s.id},'reactivate')">${ICO.reopen}<span>Reopen</span></button>`);
  }
  if (s.status !== 'vigilance' && s.status !== 'suspended') {
    actions.push(`<button class="btn warning" onclick="schoolSetStatus(${s.id},'vigilance')">${ICO.vigilance}<span>Watch</span></button>`);
  }
  actions.push(`<button class="btn" onclick="schoolNotify(${s.id})">${ICO.notify}<span>Notify parents</span></button>`);

  return `
    <div class="school-card ${s.status}">
      <div class="sc-head">
        <div>
          <div class="sc-name">${s.school_name}</div>
          <div class="sc-zone">${s.zone_name || '—'}${s.zone_name_ar ? ' · ' + s.zone_name_ar : ''}</div>
        </div>
        <span class="pill ${lbl.cls}">${lbl.txt}</span>
      </div>
      <div class="sc-meta">
        <span><b>${s.absentees}</b> absent</span>
        <span><b>${s.symptoms_count}</b> symptoms</span>
        ${s.pollution_level !== null && s.pollution_level !== undefined ? `<span>pollution <b>${s.pollution_level}%</b></span>` : ''}
      </div>
      ${s.notes ? `<div class="sc-notes">${s.notes}</div>` : ''}
      <div class="sc-actions">${actions.join('')}</div>
    </div>`;
}

function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

async function initSchool() {
  if (_schoolTimer) { clearInterval(_schoolTimer); _schoolTimer = null; }
  _schoolFilter = 'all';

  await loadSchoolPage();

  // Filtres
  document.querySelectorAll('[data-school-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      _schoolFilter = btn.dataset.schoolFilter;
      document.querySelectorAll('[data-school-filter]').forEach(b => b.classList.toggle('active', b === btn));
      loadSchoolPage();
    });
  });

  // Suspendre toutes les écoles à risque
  const susAll = document.getElementById('suspend-all');
  if (susAll) susAll.addEventListener('click', async () => {
    if (!confirm('Suspend ALL schools currently in "danger" status? A notification will be sent to all users.')) return;
    const data = await GT.api.get('schools.php');
    const dangerList = data.schools.filter(x => x.status === 'danger');
    for (const s of dangerList) await GT.api.post('schools.php', { action: 'suspend', id: s.id });
    await loadSchoolPage();
  });

  // Rouvrir si zone safe
  const reAll = document.getElementById('reactivate-safe');
  if (reAll) reAll.addEventListener('click', async () => {
    if (!confirm('Reopen all suspended schools whose zone is now safe?')) return;
    const data = await GT.api.get('schools.php');
    const reopenList = data.schools.filter(x => x.status === 'suspended' && x.zone_status === 'safe');
    if (reopenList.length === 0) { alert('No school eligible for reopening.'); return; }
    for (const s of reopenList) await GT.api.post('schools.php', { action: 'reactivate', id: s.id });
    await loadSchoolPage();
  });

  // Broadcast aux parents
  const bSend = document.getElementById('broadcast-send');
  const bMsg  = document.getElementById('broadcast-msg');
  const bSt   = document.getElementById('broadcast-status');
  if (bSend && bMsg && bSt) {
    bSend.addEventListener('click', async () => {
      const msg = bMsg.value.trim();
      if (!msg) { bSt.textContent = 'Type a message.'; return; }
      bSt.textContent = 'Sending…';
      try {
        const r = await GT.api.post('schools.php', { action: 'broadcast_parents', message: msg });
        bSt.innerHTML = `<span class="pill safe">Sent to ${r.count || 0} school(s)</span>`;
        bMsg.value = '';
        setTimeout(() => bSt.textContent = '', 4000);
      } catch (err) {
        bSt.innerHTML = '<span class="pill danger">Error</span>';
      }
    });
  }

  // Auto-refresh
  _schoolTimer = setInterval(() => {
    if (document.getElementById('schools-grid')) loadSchoolPage();
    else { clearInterval(_schoolTimer); _schoolTimer = null; }
  }, 20000);

  // Refresh sur événement de la cloche
  if (!_schoolListenerAttached) {
    document.addEventListener('gt:alerts-updated', () => {
      if (document.getElementById('schools-grid')) loadSchoolPage();
    });
    _schoolListenerAttached = true;
  }

  // Module "Saisie des absents"
  initAbsenceModule();
}

async function schoolAction(id, action) {
  const verb = action === 'suspend' ? 'suspend' : 'reopen';
  if (!confirm(`Confirm: ${verb} this school? A notification will be sent to all users.`)) return;
  await GT.api.post('schools.php', { action, id });
  await loadSchoolPage();
}

async function schoolSetStatus(id, status) {
  await GT.api.post('schools.php', { action: 'set_status', id, status });
  await loadSchoolPage();
}

async function schoolNotify(id) {
  const msg = prompt('Message to parents (leave empty to use default message):', '');
  if (msg === null) return;
  await GT.api.post('schools.php', { action: 'notify_parents', id, message: msg });
  alert('Parents notified.');
}

/* =====================================================================
 *  MODULE : Saisie des absents
 * ===================================================================== */

function refreshSchoolSelect() {
  const sel = document.getElementById('abs-school');
  if (!sel || _schoolsCache.length === 0) return;
  const prev = sel.value;
  sel.innerHTML = '<option value="">— Pick a school —</option>' +
    _schoolsCache.map(s =>
      `<option value="${s.id}">${_escape(s.school_name)}${s.zone_name ? ' · ' + _escape(s.zone_name) : ''}</option>`
    ).join('');
  if (prev && _schoolsCache.some(s => String(s.id) === String(prev))) sel.value = prev;
}

function renderAbsenceList(absences) {
  const list  = document.getElementById('absence-list');
  const count = document.getElementById('abs-list-count');
  if (!list) return;

  if (count) count.textContent = (absences.length || 0) + ' absence(s)';

  if (!absences || absences.length === 0) {
    list.innerHTML = '<div class="empty">No absence recorded.</div>';
    return;
  }

  list.innerHTML = absences.map(a => {
    const r = ABSENCE_REASONS[a.reason] || ABSENCE_REASONS.non_precise;
    const date = a.absent_date || '';
    return `
      <div class="absence-item">
        <div class="ai-main">
          <div class="ai-name">${_escape(a.student_name)}</div>
          <div class="ai-meta">
            <span class="ai-school">${_escape(a.school_name || '—')}</span>
            ${a.student_class ? `<span class="ai-class">${_escape(a.student_class)}</span>` : ''}
            <span class="ai-date">${_escape(date)}</span>
          </div>
          ${a.notes ? `<div class="ai-notes">${_escape(a.notes)}</div>` : ''}
        </div>
        <div class="ai-side">
          <span class="pill ${r.cls}">${r.label}</span>
          <button class="icon-btn danger" title="Delete" onclick="deleteAbsence(${a.id})" aria-label="Delete this absence">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 012-2h2a2 2 0 012 2v2"/></svg>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

async function loadAbsencesForDate(date) {
  const list = document.getElementById('absence-list');
  const dateLbl = document.getElementById('abs-list-date');
  if (list) list.innerHTML = '<div class="empty">Loading…</div>';
  if (dateLbl) {
    const today = _ymd(new Date());
    dateLbl.textContent = date === today ? "today" : date;
  }
  try {
    const r = await GT.api.post('schools.php', { action: 'list_absentees', date });
    _absencesCache = r.absentees || [];
    renderAbsenceList(_absencesCache);
  } catch (e) {
    if (list) list.innerHTML = '<div class="empty">Loading error.</div>';
  }
}

async function submitAbsenceForm(evt) {
  evt.preventDefault();
  const schoolId = document.getElementById('abs-school').value;
  const student  = document.getElementById('abs-student').value.trim();
  const cls      = document.getElementById('abs-class').value.trim();
  const date     = document.getElementById('abs-date').value || _ymd(new Date());
  const reason   = document.getElementById('abs-reason').value;
  const notes    = document.getElementById('abs-notes').value.trim();
  const status   = document.getElementById('abs-status');
  const submit   = document.getElementById('abs-submit');

  if (!schoolId) { status.innerHTML = '<span class="pill danger">Pick a school</span>'; return; }
  if (!student)  { status.innerHTML = '<span class="pill danger">Name required</span>'; return; }

  submit.disabled = true;
  status.innerHTML = '<span class="muted">Sending…</span>';
  try {
    await GT.api.post('schools.php', {
      action:        'add_absentee',
      school_id:     Number(schoolId),
      student_name:  student,
      student_class: cls,
      absent_date:   date,
      reason:        reason,
      notes:         notes,
    });
    status.innerHTML = '<span class="pill safe">Absence saved</span>';
    // Reset du formulaire (on garde l'école + la date pour saisir le suivant rapidement)
    document.getElementById('abs-student').value = '';
    document.getElementById('abs-class').value   = '';
    document.getElementById('abs-notes').value   = '';
    document.getElementById('abs-reason').value  = 'non_precise';
    document.getElementById('abs-student').focus();

    setTimeout(() => { status.textContent = ''; }, 3500);

    // Rafraîchit la liste (pour le jour filtré) et les stats écoles
    const day = document.getElementById('absence-day')?.value || _ymd(new Date());
    await loadAbsencesForDate(day);
    await loadSchoolPage();
  } catch (err) {
    status.innerHTML = '<span class="pill danger">Error: ' + _escape(err?.message || 'try again') + '</span>';
  } finally {
    submit.disabled = false;
  }
}

async function deleteAbsence(id) {
  if (!confirm('Delete this absence? The counter will be updated.')) return;
  try {
    await GT.api.post('schools.php', { action: 'delete_absentee', id });
    const day = document.getElementById('absence-day')?.value || _ymd(new Date());
    await loadAbsencesForDate(day);
    await loadSchoolPage();
  } catch (err) {
    alert('Error while deleting.');
  }
}

function initAbsenceModule() {
  const form = document.getElementById('absence-form');
  if (!form) return;

  // Init des valeurs par défaut (date = aujourd'hui)
  const today = _ymd(new Date());
  const dateInp = document.getElementById('abs-date');
  const dayFilt = document.getElementById('absence-day');
  if (dateInp && !dateInp.value) dateInp.value = today;
  if (dayFilt && !dayFilt.value) dayFilt.value = today;

  refreshSchoolSelect();

  form.addEventListener('submit', submitAbsenceForm);

  const refreshBtn = document.getElementById('absence-refresh');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      const d = dayFilt?.value || today;
      loadAbsencesForDate(d);
    });
  }
  if (dayFilt) {
    dayFilt.addEventListener('change', () => {
      loadAbsencesForDate(dayFilt.value);
    });
  }
}
