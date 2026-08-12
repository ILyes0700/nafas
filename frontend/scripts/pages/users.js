/* User Management page (route #/users).
   Reserved to admin + super_admin. Uses backend/api/admin-users.php
   and backend/api/auth.php (for the CSRF token). */

let _umUsers = [];
let _umCsrf = null;

function _umIsSuper() { return window.GT_USER && GT_USER.role === 'super_admin'; }

function _umToast(msg, type) {
  if (window.GT && GT.toast) { GT.toast(msg, type); return; }
  alert(msg);
}

async function _umCsrfToken() {
  if (_umCsrf) return _umCsrf;
  const r = await GT.api.get('auth.php', { action: 'csrf' });
  _umCsrf = r.csrf;
  return _umCsrf;
}

/** POST to admin-users.php with CSRF injected. */
async function _umPost(action, body = {}) {
  const csrf = await _umCsrfToken();
  return GT.api.post('admin-users.php?action=' + encodeURIComponent(action), { ...body, csrf });
}

const _UM_ROLE_LABEL = {
  citizen: 'Normal User', health: 'Doctor', school: 'School',
  admin: 'Admin', super_admin: 'Super Admin',
};

function _umEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

async function initUsers() {
  const role = window.GT_USER && GT_USER.role;
  if (role !== 'admin' && role !== 'super_admin') {
    document.getElementById('view').innerHTML =
      '<div class="card"><h3>Access denied</h3><div>This page is reserved to administrators.</div></div>';
    return;
  }

  document.getElementById('um-sub').textContent = _umIsSuper()
    ? 'Full access — manage admins and all users.'
    : 'Manage normal users, approve doctors and schools.';

  const createBtn = document.getElementById('um-create-admin');
  if (createBtn && _umIsSuper()) createBtn.style.display = '';

  document.getElementById('um-refresh')?.addEventListener('click', _umLoad);
  document.getElementById('um-search')?.addEventListener('input', _umRender);
  document.getElementById('um-filter-role')?.addEventListener('change', _umRender);
  document.getElementById('um-filter-status')?.addEventListener('change', _umRender);
  createBtn?.addEventListener('click', _umOpenCreateAdmin);
  document.getElementById('um-modal-close')?.addEventListener('click', _umCloseModal);
  document.getElementById('um-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'um-modal') _umCloseModal();
  });

  await _umLoad();
}

async function _umLoad() {
  const tbody = document.getElementById('um-tbody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty">Loading…</td></tr>';
  try {
    const r = await GT.api.get('admin-users.php', { action: 'list' });
    if (r && r.ok === false) { throw new Error(r.error || 'Server error'); }
    _umUsers = (r && r.users) || [];
    _umRenderKpis();
    _umRender();
  } catch (e) {
    const msg = (e && e.message) ? e.message : 'Unable to load users.';
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty" style="color:#b91c1c;white-space:normal;line-height:1.6">' + msg + '</td></tr>';
  }
}

function _umRenderKpis() {
  const el = document.getElementById('um-kpis');
  if (!el) return;
  const total = _umUsers.length;
  const pending = _umUsers.filter(u => u.status === 'pending').length;
  const suspended = _umUsers.filter(u => u.status === 'suspended').length;
  const doctors = _umUsers.filter(u => u.role === 'health').length;
  const schools = _umUsers.filter(u => u.role === 'school').length;
  const admins = _umUsers.filter(u => u.role === 'admin').length;
  const card = (label, val) =>
    `<div class="kpi-card"><div class="kpi-val">${val}</div><div class="kpi-lbl">${label}</div></div>`;
  let html = card('Total Users', total) + card('Pending', pending) +
             card('Suspended', suspended) + card('Doctors', doctors) + card('Schools', schools);
  if (_umIsSuper()) html += card('Admins', admins);
  el.innerHTML = html;
}

function _umRender() {
  const tbody = document.getElementById('um-tbody');
  if (!tbody) return;
  const q = (document.getElementById('um-search')?.value || '').toLowerCase().trim();
  const fRole = document.getElementById('um-filter-role')?.value || '';
  const fStatus = document.getElementById('um-filter-status')?.value || '';

  const rows = _umUsers.filter(u => {
    if (fRole && u.role !== fRole) return false;
    if (fStatus && u.status !== fStatus) return false;
    if (q) {
      const hay = ((u.full_name || '') + ' ' + (u.email || '') + ' ' + (u.phone || '')).toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">No users found.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(u => {
    const name = _umEsc(u.full_name || ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username);
    const verified = u.email_verified_at ? '✓' : '—';
    return `<tr>
      <td>${name}</td>
      <td>${_umEsc(u.email)}</td>
      <td>${_umEsc(u.phone || '—')}</td>
      <td><span class="um-badge role-${_umEsc(u.role)}">${_umEsc(_UM_ROLE_LABEL[u.role] || u.role)}</span></td>
      <td><span class="um-badge st-${_umEsc(u.status || 'active')}">${_umEsc(u.status || 'active')}</span></td>
      <td style="text-align:center">${verified}</td>
      <td class="um-actions-col"><div class="um-row-actions">${_umActionsFor(u)}</div></td>
    </tr>`;
  }).join('');

  tbody.querySelectorAll('button[data-act]').forEach(btn => {
    btn.addEventListener('click', () => _umHandleAction(btn.dataset.act, parseInt(btn.dataset.id, 10), btn.dataset.role));
  });
}

function _umActionsFor(u) {
  const id = u.id;
  const isSuper = _umIsSuper();
  const btns = [];

  if (u.role === 'super_admin') {
    return '<span class="muted" style="font-size:12px">Protected</span>';
  }

  if (u.role === 'admin') {
    // Only super admin manages admins
    if (!isSuper) return '<span class="muted" style="font-size:12px">—</span>';
    if (u.status === 'suspended') {
      btns.push(`<button class="um-mini good" data-act="restore-admin" data-id="${id}">Restore</button>`);
    } else {
      btns.push(`<button class="um-mini danger" data-act="suspend-admin" data-id="${id}">Suspend</button>`);
    }
    btns.push(`<button class="um-mini danger" data-act="delete-admin" data-id="${id}">Delete</button>`);
    return btns.join('');
  }

  // Normal / doctor / school users — admin or super can manage
  const canRole = !!u.email_verified_at;
  if (u.role !== 'health') btns.push(`<button class="um-mini" data-act="role:health" data-id="${id}" ${canRole?'':'disabled title="User must verify email first"'}>Make Doctor</button>`);
  if (u.role !== 'school') btns.push(`<button class="um-mini" data-act="role:school" data-id="${id}" ${canRole?'':'disabled title="User must verify email first"'}>Make School</button>`);
  if (u.role !== 'citizen') btns.push(`<button class="um-mini" data-act="role:citizen" data-id="${id}">Revert to Normal</button>`);
  if (u.status === 'suspended') {
    btns.push(`<button class="um-mini good" data-act="restore-user" data-id="${id}">Restore</button>`);
  } else {
    btns.push(`<button class="um-mini danger" data-act="suspend-user" data-id="${id}">Suspend</button>`);
  }
  return btns.join('');
}

async function _umHandleAction(act, id, _role) {
  try {
    if (act.startsWith('role:')) {
      const newRole = act.split(':')[1];
      const res = await _umPost('set-role', { user_id: id, role: newRole });
      if (!res.ok) return _umToast(res.error || 'Failed', 'error');
      _umToast('Role updated.', 'success');
      return _umLoad();
    }
    const confirmMap = {
      'delete-admin': 'Delete this admin account permanently?',
      'suspend-admin': 'Suspend this admin?',
      'suspend-user': 'Suspend this user?',
    };
    if (confirmMap[act] && !confirm(confirmMap[act])) return;
    const res = await _umPost(act, { user_id: id });
    if (!res.ok) return _umToast(res.error || 'Failed', 'error');
    _umToast('Done.', 'success');
    _umLoad();
  } catch (e) {
    _umToast('Action failed.', 'error');
  }
}

function _umCloseModal() {
  const m = document.getElementById('um-modal');
  if (m) m.hidden = true;
}

function _umOpenCreateAdmin() {
  if (!_umIsSuper()) return;
  const body = document.getElementById('um-modal-body');
  document.getElementById('um-modal-title').textContent = 'Create Admin';
  body.innerHTML = `
    <div class="um-modal-err" id="um-ca-err" style="display:none"></div>
    <div><label>First Name</label><input id="ca-first" type="text"></div>
    <div><label>Last Name</label><input id="ca-last" type="text"></div>
    <div><label>Email</label><input id="ca-email" type="email"></div>
    <div><label>Phone (optional)</label><input id="ca-phone" type="tel"></div>
    <div class="um-note" style="background:#eef3fa;color:#0d3b66;border-radius:8px;padding:10px 12px;font-size:12.5px;margin-top:4px;">&#128273; A strong random password is generated automatically and emailed to the new admin. They will be asked to change it on first login.</div>
    <div class="um-modal-actions">
      <button class="btn outline" id="ca-cancel">Cancel</button>
      <button class="btn primary" id="ca-submit">Create</button>
    </div>`;
  document.getElementById('um-modal').hidden = false;
  document.getElementById('ca-cancel').addEventListener('click', _umCloseModal);
  document.getElementById('ca-submit').addEventListener('click', _umSubmitCreateAdmin);
}

async function _umSubmitCreateAdmin() {
  const err = document.getElementById('um-ca-err');
  const show = (m) => { err.textContent = m; err.style.display = 'block'; };
  const payload = {
    first_name: document.getElementById('ca-first').value.trim(),
    last_name:  document.getElementById('ca-last').value.trim(),
    email:      document.getElementById('ca-email').value.trim(),
    phone:      document.getElementById('ca-phone').value.trim(),
  };
  if (!payload.first_name || !payload.last_name) return show('First and last name are required.');
  if (!payload.email) return show('Email is required.');
  try {
    const res = await _umPost('create-admin', payload);
    if (!res.ok) return show(res.error || 'Failed to create admin.');
    _umToast(res.mailed ? 'Admin created — credentials emailed.' : 'Admin created (email could not be sent — check SMTP).', 'success');
    _umCloseModal();
    _umLoad();
  } catch (e) {
    show('Request failed.');
  }
}
