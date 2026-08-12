function initSettings() {
  document.getElementById('backend-host').textContent = GT.api.base;

  document.getElementById('ping').addEventListener('click', async () => {
    const out = document.getElementById('ping-result');
    out.textContent = 'Connecting…';
    try {
      const d = await GT.api.get('dashboard.php');
      out.innerHTML = `<span class="pill safe">✓ Connected</span> · ${d.counts.zones} zones, ${d.counts.alerts} alerts (24h)`;
    } catch (e) {
      out.innerHTML = `<span class="pill danger">✗ Failed</span> ${e.message}`;
    }
  });

  document.getElementById('recompute-scores').addEventListener('click', async () => {
    const out = document.getElementById('recompute-result');
    out.textContent = 'Recomputing…';
    try {
      const d = await GT.api.get('risk.php', { action: 'recompute' });
      out.innerHTML = `<span class="pill safe">✓ ${d.updated.length} zones updated</span>`;
    } catch (e) {
      out.innerHTML = `<span class="pill danger">✗ ${e.message}</span>`;
    }
  });

  /* ---------- A6 — profil de fragilité (citoyens uniquement) ---------- */
  const role = (window.GT_USER && window.GT_USER.role) || '';
  if (role === 'citizen') {
    const card = document.getElementById('profile-fragile-card');
    if (card) card.hidden = false;
    initFragileProfile();
    const tcard = document.getElementById('telemed-card');
    if (tcard) tcard.hidden = false;
    initTelemed();
  }

  /* ---------- IQAir (admin/health uniquement) ---------- */
  if (role === 'admin' || role === 'health') {
    const ic = document.getElementById('iqair-card');
    if (ic) ic.hidden = false;
    initIqair();
  }
}

function initIqair() {
  const btn      = document.getElementById('iqair-refresh-btn');
  const btnForce = document.getElementById('iqair-refresh-force-btn');
  const out      = document.getElementById('iqair-result');
  if (!btn || !out) return;

  async function run(force) {
    btn.disabled = true;
    if (btnForce) btnForce.disabled = true;
    out.innerHTML = '<span class="muted">Calling IQAir + WAQI… (may take 10–20 s)</span>';
    try {
      const url = '../backend/api/iqair-refresh.php' + (force ? '?force=1' : '');
      const r = await fetch(url, { credentials: 'same-origin' });

      // Read response as text first — we want to show the raw body if it's
      // not valid JSON (so the user sees the real PHP error from WAMP).
      const text = await r.text();
      let d;
      try {
        d = JSON.parse(text);
      } catch (parseErr) {
        const snippet = (text || '').slice(0, 400).replace(/\s+/g, ' ');
        out.innerHTML = `<span class="pill danger">✗ Non-JSON response (HTTP ${r.status})</span>`
          + `<div class="muted small" style="margin-top:6px">Server returned: ${escapeHtmlIQ(snippet)}…</div>`
          + `<div class="muted small" style="margin-top:6px">Try: <code>php scripts/refresh_pollution.php --force</code> from the project root.</div>`;
        return;
      }

      if (!d.ok) {
        out.innerHTML = `<span class="pill danger">✗ ${escapeHtmlIQ(d.error || 'error')}</span>`;
        return;
      }
      let html = `<div class="iqair-summary">`
        + `<span class="pill safe">${d.success}/${d.total} OK</span> `
        + `<span class="pill">${d.changed} changed</span> `
        + `<span class="pill muted">${d.cached} cached</span>`
        + (d.failed ? ` <span class="pill danger">${d.failed} failed</span>` : '')
        + `</div>`;
      const items = (d.results || []).map(r => {
        if (!r.ok) {
          return `<li class="iqair-row iqair-row-fail">Zone #${r.zone_id} — ${escapeHtmlIQ(r.error || 'error')}</li>`;
        }
        if (r.cached) {
          return `<li class="iqair-row iqair-row-cache">Zone #${r.zone_id} — cache (${r.pollution_level}/100)</li>`;
        }
        const arrow = (r.previous_level === r.pollution_level)
          ? '='
          : (r.previous_level < r.pollution_level ? '↑' : '↓');
        const aqiStr   = (r.aqi !== null && r.aqi !== undefined) ? `AQI <b>${r.aqi}</b> → ` : '';
        const trustStr = (r.trust !== undefined && r.trust !== null)
          ? ` <span class="muted small">trust=${(+r.trust).toFixed(2)}</span>`
          : '';
        return `<li class="iqair-row">`
          + `Zone #${r.zone_id} · ${aqiStr}pollution <b>${r.pollution_level}/100</b> `
          + `<span class="muted small">(${r.previous_level} ${arrow} ${r.pollution_level})</span> `
          + `<span class="muted small">${escapeHtmlIQ(r.station || '')}</span>`
          + trustStr
          + `</li>`;
      }).join('');
      html += `<ul class="iqair-list">${items}</ul>`;
      if (d.last_error) {
        html += `<div class="muted small">Last error: ${escapeHtmlIQ(d.last_error)}</div>`;
      }
      out.innerHTML = html;
    } catch (e) {
      out.innerHTML = `<span class="pill danger">✗ ${escapeHtmlIQ(e.message)}</span>`;
    } finally {
      btn.disabled = false;
      if (btnForce) btnForce.disabled = false;
    }
  }

  btn.addEventListener('click',      () => run(false));
  if (btnForce) btnForce.addEventListener('click', () => run(true));
}

function escapeHtmlIQ(s) {
  return String(s || '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

async function initFragileProfile() {
  const form  = document.getElementById('profile-fragile-form');
  const msg   = document.getElementById('profile-fragile-msg');
  const zoneSelect = document.getElementById('profile-home-zone');
  if (!form) return;

  // Charger la liste des zones (pour le select "ville")
  try {
    const r = await fetch('../backend/api/zones.php', { credentials: 'same-origin' });
    const d = await r.json();
    const zones = d.zones || [];
    if (zoneSelect) {
      zones.forEach(z => {
        const opt = document.createElement('option');
        opt.value = z.id;
        opt.textContent = z.name;
        zoneSelect.appendChild(opt);
      });
    }
  } catch (_) { /* ignore */ }

  // Charger profil + ville actuelle
  try {
    const r = await fetch('../backend/api/profile.php', { credentials: 'same-origin' });
    const data = await r.json();
    if (data.ok) {
      if (data.profile) {
        Object.entries(data.profile).forEach(([k, v]) => {
          const el = form.elements[k];
          if (!el) return;
          if (el.type === 'checkbox') el.checked = !!Number(v);
          else el.value = v == null ? '' : v;
        });
      }
      if (data.home_zone_id && zoneSelect) {
        zoneSelect.value = String(data.home_zone_id);
      }
    }
  } catch (_) {}

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.textContent = 'Saving…';
    const fd = new FormData(form);
    const body = {
      has_asthma:        form.has_asthma.checked        ? 1 : 0,
      has_heart_disease: form.has_heart_disease.checked ? 1 : 0,
      has_allergy:       form.has_allergy.checked       ? 1 : 0,
      is_pregnant:       form.is_pregnant.checked       ? 1 : 0,
      is_child:          form.is_child.checked          ? 1 : 0,
      is_elderly:        form.is_elderly.checked        ? 1 : 0,
      notes:        (fd.get('notes') || '').toString(),
      home_zone_id: Number(fd.get('home_zone_id') || 0) || null,
    };
    try {
      const r = await fetch('../backend/api/profile.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await r.json();
      msg.textContent = data.ok ? 'Profile + city saved.' : (data.error || 'Error');
    } catch (err) {
      msg.textContent = 'Network error';
    }
    setTimeout(() => msg.textContent = '', 2200);
  });
}

/* ============================================================================
 * H1 — Téléconsultation in-app avec salle d'attente 15 min + iframe Jitsi
 * ============================================================================ */
function initTelemed() {
  const btn      = document.getElementById('telemed-request-btn');
  const stat     = document.getElementById('telemed-status');
  const wait     = document.getElementById('telemed-waiting');
  const wrap     = document.getElementById('telemed-frame-wrap');
  const frame    = document.getElementById('telemed-frame');
  const fStatus  = document.getElementById('telemed-frame-status');
  const tEl      = document.getElementById('telemed-timer');
  const cancelB  = document.getElementById('telemed-cancel');
  const leaveB   = document.getElementById('telemed-leave');
  const summary  = document.getElementById('telemed-summary');
  const tsBody   = document.getElementById('ts-body');
  const tsMeta   = document.getElementById('ts-meta');
  const tsPdfBtn = document.getElementById('ts-pdf-btn');
  const tsCloseBtn = document.getElementById('ts-close-btn');
  if (!btn) return;

  let pollTimer  = null;
  let timerEl    = null;
  let jitsiApi   = null;
  let currentReq = null;       // { id, room, url, expires_at }

  function fmtMMSS(sec) {
    sec = Math.max(0, Math.floor(sec));
    const m = Math.floor(sec / 60), s = sec % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  function clearAllTimers() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    if (timerEl)   { clearInterval(timerEl);   timerEl   = null; }
  }

  function resetUI() {
    clearAllTimers();
    if (wait) wait.hidden = true;
    if (wrap) wrap.hidden = true;
    if (frame) frame.innerHTML = '';
    if (summary) summary.hidden = true;
    if (jitsiApi && typeof jitsiApi.dispose === 'function') {
      try { jitsiApi.dispose(); } catch (_) {}
    }
    jitsiApi = null;
    currentReq = null;
    btn.disabled = false;
    btn.style.display = '';
  }

  function escH(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  // Render the consultation summary panel when the doctor finalizes.
  // This is shown to the citizen *immediately* (within 4 s of save) without
  // requiring a manual page refresh.
  function showSummary(req) {
    if (!summary || !tsBody) return;
    let post = null;
    try { post = req.post_consult ? JSON.parse(req.post_consult) : null; } catch (_) {}

    if (!post) {
      tsBody.innerHTML = '<p class="muted">Consultation closed without a written summary.</p>';
    } else {
      const rows = [];
      if (post.diagnosis)       rows.push(`<div class="ts-row"><b>Diagnosis</b><span>${escH(post.diagnosis)}</span></div>`);
      if (post.recommendations) rows.push(`<div class="ts-row"><b>Recommendations</b><span>${escH(post.recommendations)}</span></div>`);
      if (post.prescription)    rows.push(`<div class="ts-row"><b>Prescription</b><span>${escH(post.prescription)}</span></div>`);
      if (post.follow_up_days)  rows.push(`<div class="ts-row"><b>Follow-up</b><span>in ${escH(post.follow_up_days)} day(s)</span></div>`);
      tsBody.innerHTML = rows.join('') || '<p class="muted">No notes from doctor.</p>';
      if (tsMeta) {
        const parts = [];
        if (post.doctor_name)   parts.push('By ' + post.doctor_name);
        if (post.finalized_at)  parts.push(post.finalized_at);
        tsMeta.textContent = parts.join(' · ');
      }
    }

    // Tear down jitsi + waiting state but KEEP the card so the citizen
    // sees the summary right where they were.
    clearAllTimers();
    if (jitsiApi && typeof jitsiApi.dispose === 'function') {
      try { jitsiApi.dispose(); } catch (_) {}
    }
    jitsiApi = null;
    if (wait)  wait.hidden  = true;
    if (wrap)  wrap.hidden  = true;
    if (frame) frame.innerHTML = '';
    btn.style.display = 'none';
    summary.hidden = false;
    summary.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (tsPdfBtn)   tsPdfBtn.onclick   = () => window.open('../backend/api/telemed-prescription.php?id=' + encodeURIComponent(req.id), '_blank');
    if (tsCloseBtn) tsCloseBtn.onclick = () => { if (summary) summary.hidden = true; resetUI(); };
  }

  // Charge l'API Jitsi externe (lazy)
  function loadJitsiScript() {
    return new Promise((resolve, reject) => {
      if (window.JitsiMeetExternalAPI) return resolve();
      const s = document.createElement('script');
      s.src = 'https://meet.jit.si/external_api.js';
      s.onload  = () => resolve();
      s.onerror = () => reject(new Error('jitsi_load_failed'));
      document.head.appendChild(s);
    });
  }

  async function embedJitsi(req) {
    if (!frame) return;
    frame.innerHTML = '';
    if (wrap) wrap.hidden = false;
    if (wait) wait.hidden = true;
    fStatus && (fStatus.textContent = 'connecting…');
    try {
      await loadJitsiScript();
      jitsiApi = new window.JitsiMeetExternalAPI('meet.jit.si', {
        roomName: req.room,
        parentNode: frame,
        width: '100%',
        height: 540,
        userInfo: {
          displayName: (window.GT_USER && window.GT_USER.full_name) || 'Citizen',
        },
        configOverwrite: {
          startWithAudioMuted: false,
          startWithVideoMuted: false,
          prejoinPageEnabled: false,
        },
        interfaceConfigOverwrite: {
          DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,
        },
      });
      jitsiApi.addListener('videoConferenceJoined', () => {
        fStatus && (fStatus.textContent = 'online');
      });
      jitsiApi.addListener('readyToClose', () => leaveCall());
    } catch (e) {
      fStatus && (fStatus.textContent = 'failed — ' + (e.message || ''));
    }
  }

  async function leaveCall() {
    try {
      if (currentReq && currentReq.id) {
        await fetch('../backend/api/telemed-request.php', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'close', id: currentReq.id }),
        });
      }
    } catch (_) {}
    resetUI();
    stat.textContent = 'Consultation ended.';
    setTimeout(() => stat.textContent = '', 3000);
  }

  function startWaiting(req) {
    currentReq = req;
    btn.style.display = 'none';
    if (wait) wait.hidden = false;
    if (wrap) wrap.hidden = true;
    stat.textContent = '';

    // Use server-computed `seconds_remaining` (avoids browser timezone issues
    // when parsing MySQL DATETIME strings, which used to display 00:00 instantly).
    let remain = Math.max(0, Number(req.seconds_remaining));
    if (!Number.isFinite(remain) || remain <= 0) {
      // Fallback: if backend didn't send seconds_remaining (legacy shape),
      // assume a fresh 15-min window for newly-created requests.
      remain = 15 * 60;
    }
    const startedAt = Date.now();

    // Visual countdown
    const tickTimer = () => {
      const elapsed = (Date.now() - startedAt) / 1000;
      const left = remain - elapsed;
      tEl && (tEl.textContent = fmtMMSS(left));
      if (left <= 0) {
        clearAllTimers();
        resetUI();
        stat.textContent = 'No professional available — consultation expired.';
        setTimeout(() => stat.textContent = '', 5000);
      }
    };
    tickTimer();
    timerEl = setInterval(tickTimer, 1000);

    // Sondage status (toutes les 4 s) — continues polling even after Jitsi is
    // embedded so the citizen is notified immediately when the doctor
    // finalizes the consultation (status=closed + post_consult JSON).
    let jitsiStarted = false;
    const tickPoll = async () => {
      try {
        const r = await fetch('../backend/api/telemed-request.php?id=' + req.id, {
          credentials: 'same-origin',
        });
        const d = await r.json();
        if (!d.ok || !d.request) return;
        const st = d.request.status;

        // Status: closed with doctor notes → render the summary panel inline.
        // This is the FIX for "citizen doesn't see post_consult after finalize".
        if (st === 'closed' && d.request.post_consult) {
          showSummary(d.request);
          return;
        }

        if (st === 'joined' && !jitsiStarted) {
          jitsiStarted = true;
          embedJitsi({ id: d.request.id, room: d.request.room });
        } else if (st === 'expired') {
          resetUI();
          stat.textContent = 'No professional available — consultation expired.';
          setTimeout(() => stat.textContent = '', 5000);
        } else if (st === 'closed' && !d.request.post_consult) {
          // closed without notes (e.g. citizen left before doctor wrote summary)
          resetUI();
          stat.textContent = 'Consultation ended.';
          setTimeout(() => stat.textContent = '', 4000);
        }
      } catch (_) {}
    };
    pollTimer = setInterval(tickPoll, 4000);
  }

  function readPreConsult() {
    const num = (id) => {
      const v = document.getElementById(id);
      const n = v && v.value !== '' ? Number(v.value) : null;
      return Number.isFinite(n) ? n : null;
    };
    const txt = (id) => {
      const v = document.getElementById(id);
      return v ? (v.value || '').trim() : '';
    };
    const pre = {
      temperature: num('tp-temp'),
      pulse:       num('tp-pulse'),
      oxygen_sat:  num('tp-spo2'),
      symptoms:    txt('tp-symptoms'),
      notes:       txt('tp-notes'),
    };
    // Drop completely empty payloads so the backend stores NULL instead of "{}".
    const hasAny = pre.temperature !== null || pre.pulse !== null
                || pre.oxygen_sat  !== null || pre.symptoms || pre.notes;
    return hasAny ? pre : null;
  }

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    stat.textContent = 'Creating request…';
    try {
      const pre = readPreConsult();
      const r = await fetch('../backend/api/telemed-request.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', pre_consult: pre }),
      });
      const d = await r.json();
      if (d.ok && d.request) {
        startWaiting(d.request);
      } else {
        stat.textContent = d.error || 'Unavailable';
        btn.disabled = false;
      }
    } catch (e) {
      stat.textContent = 'Network error';
      btn.disabled = false;
    }
  });

  if (cancelB) cancelB.addEventListener('click', leaveCall);
  if (leaveB)  leaveB.addEventListener('click', leaveCall);
}
