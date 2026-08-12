/* =========================================================
   Facebook-style Profile page logic (Nafass)
   Exposes initProfile() called by the SPA router.
   ========================================================= */
'use strict';

let _pfData = null;
let _pfWired = false;
let _pfPostFile = null;
let _pfFeedOldest = 0;
let _pfFeedLoading = false;
let _pfFeedHasMore = true;
let _pfFeedObserver = null;
let _pfIsMe = true;
let _pfViewUserId = 0;
let _pfIsFollowingOwner = false;

const _PF_REACTIONS = [
  { e: '\u{1F44D}', label: 'Like' },
  { e: '\u{2764}\u{FE0F}', label: 'Love' },
  { e: '\u{1F602}', label: 'Haha' },
  { e: '\u{1F62E}', label: 'Wow' },
  { e: '\u{1F622}', label: 'Sad' },
  { e: '\u{1F621}', label: 'Angry' },
  { e: '\u{1F44F}', label: 'Applause' },
  { e: '\u{1F525}', label: 'Fire' },
  { e: '\u{1F389}', label: 'Celebrate' },
  { e: '\u{1F4AF}', label: 'Excellent' },
];
const _PF_ROLE = { citizen: 'Normal User', health: 'Doctor', school: 'School', admin: 'Admin', super_admin: 'Super Admin', analyst: 'Analyst', user: 'User' };
const _PF_LANG = { ar: '\u0627\u0644\u0639\u0631\u0628\u064a\u0629', fr: 'Fran\u00e7ais', en: 'English' };

/* ---------- small helpers ---------- */
async function _pfCsrfToken() {
  try { const r = await GT.api.get('auth.php', { action: 'csrf' }); return (r && (r.csrf || r.token)) || ''; }
  catch (e) { return ''; }
}
function _pfToast(m, t) { if (window.GT && GT.toast) { GT.toast(m, t); return; } if (t === 'error') alert(m); }
function _pfEsc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function _pfLinkify(s) {
  // escape first, then turn #hashtags into styled spans
  return _pfEsc(s).replace(/(^|\s)(#[\p{L}0-9_]+)/gu, '$1<span class="pf2-tag">$2</span>');
}
function _pfFmtDate(s) { if (!s) return '\u2014'; const d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return _pfEsc(s); return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
function _pfTimeAgo(s) {
  if (!s) return ''; const d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return _pfEsc(s);
  const sec = Math.floor((Date.now() - d.getTime()) / 1000);
  if (sec < 60) return 'just now';
  if (sec < 3600) return Math.floor(sec / 60) + 'm';
  if (sec < 86400) return Math.floor(sec / 3600) + 'h';
  if (sec < 604800) return Math.floor(sec / 86400) + 'd';
  return _pfFmtDate(s);
}
function _pfHumanSize(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(1) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }
function _pfInitial(name) { return (String(name || '?').trim().charAt(0) || '?').toUpperCase(); }
function _pfKind(name) {
  const e = (String(name).split('.').pop() || '').toLowerCase();
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(e)) return 'image';
  if (['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'].includes(e)) return 'video';
  return 'file';
}

/* ---------- entry ---------- */
async function initProfile() {
  // The router replaces the DOM on every navigation, so re-wire and reset feed
  // state each time — this makes switching between different users' profiles work.
  _pfWired = false;
  if (_pfFeedObserver) { try { _pfFeedObserver.disconnect(); } catch (e) {} _pfFeedObserver = null; }
  _pfPostFile = null; _pfFeedOldest = 0; _pfFeedLoading = false; _pfFeedHasMore = true;
  _pfApplyTheme();

  const params = (typeof window !== 'undefined' && window.__routeParams) ? window.__routeParams : new URLSearchParams();
  const uParam = parseInt(params.get('u') || '0', 10);

  try {
    let r;
    if (uParam > 0) {
      r = await GT.api.get('profile.php', { action: 'view', user_id: uParam });
      if (r && r.ok !== false && r.is_me) {
        // Viewing my own shared link -> load the full editable profile instead.
        r = await GT.api.get('profile.php', { action: 'me' });
        _pfIsMe = true;
      } else {
        _pfIsMe = false;
      }
    } else {
      r = await GT.api.get('profile.php', { action: 'me' });
      _pfIsMe = true;
    }
    if (!r || r.ok === false) throw new Error((r && r.error) || 'Failed to load profile.');
    _pfData = r;
    _pfViewUserId = (r.profile && r.profile.id) ? parseInt(r.profile.id, 10) : uParam;
    _pfIsFollowingOwner = !!r.is_following;
    _pfFill();
    _pfApplyMode();
    _pfWire();
    _pfSetupInfiniteScroll();
    _pfLoadFeed(true);
    _pfLoadFriends();
  } catch (e) {
    const root = document.getElementById('pf-root');
    if (root) root.innerHTML = '<div class="pf2-panel" style="color:#b91c1c">' + _pfEsc(e.message) + '</div>';
  }
}

/* ---------- owner vs. visitor mode ---------- */
function _pfApplyMode() {
  const me = _pfIsMe;
  const root = document.getElementById('pf-root');
  if (root) root.classList.toggle('pf2-viewing', !me);
  // Owner-only controls are hidden when viewing someone else's page.
  ['pf-edit-btn', 'pf-avatar-btn', 'pf-cover-btn', 'pf-upload-btn', 'pf-composer'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = me ? '' : 'none';
  });
  const actions = document.querySelector('#pf-root .pf2-actions');
  let followBtn = document.getElementById('pf-owner-follow');
  if (!me && actions) {
    if (!followBtn) {
      followBtn = document.createElement('button');
      followBtn.id = 'pf-owner-follow';
      followBtn.className = 'btn primary';
      followBtn.type = 'button';
      actions.insertBefore(followBtn, actions.firstChild);
      followBtn.addEventListener('click', _pfToggleOwnerFollow);
    }
    followBtn.classList.toggle('on', !!_pfIsFollowingOwner);
    followBtn.textContent = _pfIsFollowingOwner ? '\u2713 Following' : '+ Follow';
    followBtn.style.display = '';
    let msgBtn = document.getElementById('pf-owner-msg');
    if (!msgBtn) {
      msgBtn = document.createElement('button');
      msgBtn.id = 'pf-owner-msg';
      msgBtn.className = 'btn';
      msgBtn.type = 'button';
      msgBtn.textContent = 'Message';
      actions.insertBefore(msgBtn, followBtn.nextSibling);
      msgBtn.addEventListener('click', function () {
        if (!window.Messenger || !_pfViewUserId) return;
        const nm = (document.getElementById('pf-name') || {}).textContent || 'User';
        const av = (_pfData && _pfData.profile && _pfData.profile.avatar_path) || null;
        window.Messenger.open(_pfViewUserId, nm, av);
      });
    }
    msgBtn.style.display = '';
  } else {
    if (followBtn) followBtn.style.display = 'none';
    const mb2 = document.getElementById('pf-owner-msg');
    if (mb2) mb2.style.display = 'none';
  }
}
async function _pfToggleOwnerFollow() {
  if (!_pfViewUserId) return;
  try {
    const r = await GT.api.post('follow.php?action=toggle', { user_id: _pfViewUserId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Failed.', 'error'); return; }
    _pfIsFollowingOwner = !!r.following;
    const btn = document.getElementById('pf-owner-follow');
    if (btn) { btn.classList.toggle('on', _pfIsFollowingOwner); btn.textContent = _pfIsFollowingOwner ? '\u2713 Following' : '+ Follow'; }
    const el = document.getElementById('pf-stat-followers');
    if (el) { const n = parseInt(el.textContent, 10) || 0; el.textContent = Math.max(0, n + (_pfIsFollowingOwner ? 1 : -1)); }
  } catch (e) { _pfToast('Failed.', 'error'); }
}

/* ---------- Friends zone: followers / following / mutual (Facebook-style) ---------- */
let _pfFriendsData = null;
let _pfFriendsTab = 'friends';
async function _pfLoadFriends() {
  const grid = document.getElementById('pf-friends-grid');
  if (!grid) return;
  _pfWireFriendsTabs();
  try {
    const r = await GT.api.get('follow.php', { action: 'list', user_id: _pfViewUserId || 0 });
    if (!r || r.ok === false) { grid.innerHTML = '<div class="empty">Could not load.</div>'; return; }
    _pfFriendsData = r;
    const c = r.counts || { friends: 0, followers: 0, following: 0 };
    const setc = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    setc('pf-ft-friends', c.friends || 0);
    setc('pf-ft-followers', c.followers || 0);
    setc('pf-ft-following', c.following || 0);
    const total = document.getElementById('pf-friends-count');
    if (total) total.textContent = (c.friends || 0) ? '\u00b7 ' + c.friends : '';
    _pfRenderFriends();
  } catch (e) { grid.innerHTML = '<div class="empty">Could not load.</div>'; }
}
function _pfRenderFriends() {
  const grid = document.getElementById('pf-friends-grid');
  if (!grid || !_pfFriendsData) return;
  const list = _pfFriendsData[_pfFriendsTab] || [];
  if (!list.length) {
    const msg = _pfFriendsTab === 'friends' ? 'No mutual friends yet.'
      : _pfFriendsTab === 'followers' ? 'No followers yet.' : 'Not following anyone yet.';
    grid.innerHTML = '<div class="empty">' + msg + '</div>';
    return;
  }
  grid.innerHTML = list.map(function (p) {
    const av = p.avatar_path
      ? '<img src="' + _pfEsc(GT.api.asset(p.avatar_path)) + '" alt="">'
      : '<span>' + _pfEsc(_pfInitial(p.full_name || p.username)) + '</span>';
    const role = _PF_ROLE[p.role] || '';
    const name = _pfEsc(p.full_name || p.username || 'User');
    const href = '#/profile?u=' + p.id;
    let btn = '';
    if (!p.is_me) {
      btn = '<div class="pf2-fr-actions">'
        + '<button class="pf2-fr-msg" data-msg="' + p.id + '" data-name="' + name + '" data-avatar="' + _pfEsc(p.avatar_path || '') + '" type="button" title="Send message">Message</button>'
        + '<button class="pf2-fr-follow' + (p.is_following ? ' on' : '') + '" data-follow="' + p.id + '" type="button">'
        + (p.is_following ? 'Following' : '+ Follow') + '</button></div>';
    }
    return '<div class="pf2-fr-card">'
      + '<a class="pf2-fr-av" href="' + href + '">' + av + '</a>'
      + '<div class="pf2-fr-info">'
      + '<a class="pf2-fr-name" href="' + href + '">' + name + '</a>'
      + (role ? '<span class="pf2-fr-role">' + _pfEsc(role) + '</span>' : '')
      + '</div>' + btn + '</div>';
  }).join('');
}
function _pfWireFriendsTabs() {
  const tabs = document.getElementById('pf-friends-tabs');
  if (tabs && !tabs.__wired) {
    tabs.__wired = true;
    tabs.addEventListener('click', function (e) {
      const b = e.target.closest('.pf2-ftab');
      if (!b) return;
      _pfFriendsTab = b.dataset.ftab || 'friends';
      tabs.querySelectorAll('.pf2-ftab').forEach(x => x.classList.toggle('active', x === b));
      _pfRenderFriends();
    });
  }
  const grid = document.getElementById('pf-friends-grid');
  if (grid && !grid.__wired) {
    grid.__wired = true;
    grid.addEventListener('click', function (e) {
      const mb = e.target.closest('[data-msg]');
      if (mb) { e.preventDefault(); if (window.Messenger) window.Messenger.open(parseInt(mb.dataset.msg, 10), mb.dataset.name, mb.dataset.avatar || null); return; }
      const fb = e.target.closest('[data-follow]');
      if (fb) { e.preventDefault(); _pfToggleFollow(parseInt(fb.dataset.follow, 10), fb); }
    });
  }
}

/* ---------- fill header / sidebar ---------- */
function _pfFill() {
  const u = (_pfData && _pfData.profile) || {};
  const st = (_pfData && _pfData.stats) || { posts: 0, followers: 0, following: 0 };
  const name = (u.full_name || '').trim() || ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username || 'User';
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

  set('pf-name', name);
  set('pf-username', '@' + (u.username || 'user'));
  set('pf-stat-posts', st.posts || 0);
  set('pf-stat-followers', st.followers || 0);
  set('pf-stat-following', st.following || 0);

  const bioLine = document.getElementById('pf-bio-line');
  if (bioLine) bioLine.textContent = u.bio || '';

  // meta row: role, city/country, join date
  const meta = document.getElementById('pf-meta');
  if (meta) {
    const IC = {
      role: '<svg class="pf2-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>',
      loc:  '<svg class="pf2-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
      lang: '<svg class="pf2-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/></svg>',
      date: '<svg class="pf2-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
    };
    const bits = [];
    bits.push('<span>' + IC.role + _pfEsc(_PF_ROLE[u.role] || u.role || 'User') + '</span>');
    const loc = [u.city, u.country].filter(Boolean).join(', ');
    if (loc) bits.push('<span>' + IC.loc + _pfEsc(loc) + '</span>');
    if (u.language && _PF_LANG[u.language]) bits.push('<span>' + IC.lang + _pfEsc(_PF_LANG[u.language]) + '</span>');
    bits.push('<span>' + IC.date + 'Joined ' + _pfFmtDate(u.created_at) + '</span>');
    meta.innerHTML = bits.join('');
  }

  _pfRenderAvatar(u);
  _pfRenderCover(u);
  _pfRenderComposerAvatar(u);
  _pfRenderAbout(u);
  _pfRenderFiles(_pfData.files || []);
}

function _pfRenderAvatar(u) {
  const box = document.getElementById('pf-avatar');
  if (!box) return;
  if (u && u.avatar_path) box.innerHTML = '<img src="' + _pfEsc(GT.api.asset(u.avatar_path)) + '?t=' + Date.now() + '" alt="avatar">';
  else box.innerHTML = '<span id="pf-avatar-fallback">' + _pfEsc(_pfInitial(u.full_name || u.username)) + '</span>';
}
function _pfRenderCover(u) {
  const box = document.getElementById('pf-cover');
  if (!box) return;
  if (u && u.cover_path) { box.style.backgroundImage = 'url("' + _pfEsc(GT.api.asset(u.cover_path)) + '?t=' + Date.now() + '")'; }
}
function _pfRenderComposerAvatar(u) {
  const box = document.getElementById('pf-composer-avatar');
  if (!box) return;
  if (u && u.avatar_path) box.innerHTML = '<img src="' + _pfEsc(GT.api.asset(u.avatar_path)) + '" alt="me">';
  else box.innerHTML = '<span>' + _pfEsc(_pfInitial(u.full_name || u.username)) + '</span>';
}
function _pfRenderAbout(u) {
  const el = document.getElementById('pf-about');
  if (!el) return;
  const S = function (p) { return '<span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg></span>'; };
  const IC = {
    bio:   S('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M9 7h7M9 11h5"/>'),
    loc:   S('<path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'),
    phone: S('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L7.6 9.8a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6A2 2 0 0 1 22 16.9z"/>'),
    mail:  S('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 6 10 7 10-7"/>'),
    age:   S('<path d="M4 21h16M4 21v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6M4 15h16M12 8V5M9 8V6M15 8V6"/>'),
    user:  S('<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>'),
    date:  S('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>')
  };
  const rows = [];
  if (u.bio) rows.push('<li>' + IC.bio + _pfEsc(u.bio) + '</li>');
  const loc = [u.city, u.country].filter(Boolean).join(', ');
  if (loc) rows.push('<li>' + IC.loc + 'Lives in ' + _pfEsc(loc) + '</li>');
  if (u.phone) rows.push('<li>' + IC.phone + _pfEsc(u.phone) + '</li>');
  if (u.email) rows.push('<li>' + IC.mail + _pfEsc(u.email) + '</li>');
  if (u.age) rows.push('<li>' + IC.age + _pfEsc(u.age) + ' years</li>');
  rows.push('<li>' + IC.user + '@' + _pfEsc(u.username || 'user') + '</li>');
  rows.push('<li>' + IC.date + 'Joined ' + _pfFmtDate(u.created_at) + '</li>');
  el.innerHTML = rows.join('') || '<li class="muted">No details yet</li>';
}
function _pfRenderFiles(files) {
  const wrap = document.getElementById('pf-files');
  const photos = document.getElementById('pf-photos');
  if (photos) {
    window._pfFileImgs = (files || []).filter(f => _pfKind(f.original_name) === 'image').map(f => GT.api.asset(f.stored_path));
    _pfRenderPhotosBox();
  }
  if (!wrap) return;
  if (!files || !files.length) { wrap.innerHTML = '<div class="empty">No files yet</div>'; return; }
  wrap.innerHTML = files.map(f => {
    const url = _pfEsc(GT.api.asset(f.stored_path));
    const ext = (f.original_name.split('.').pop() || 'F').toUpperCase().slice(0, 4);
    return '<div class="pf2-file"><div class="fi">' + _pfEsc(ext) + '</div>'
      + '<div class="fm"><a href="' + url + '" target="_blank" rel="noopener" title="' + _pfEsc(f.original_name) + '">' + _pfEsc(f.original_name) + '</a>'
      + '<span>' + _pfHumanSize(f.size) + ' \u00b7 ' + _pfFmtDate(f.created_at) + '</span></div>'
      + '<button class="pf2-mini danger" data-del="' + f.id + '" type="button">\u2715</button></div>';
  }).join('');
  wrap.querySelectorAll('button[data-del]').forEach(b => b.addEventListener('click', () => _pfDeleteFile(parseInt(b.dataset.del, 10))));
}

/* ---------- theme (light / dark) ---------- */
function _pfApplyTheme() {
  // Light/dark toggle removed from the profile — always keep the clean light theme.
  const root = document.getElementById('pf-root');
  if (!root) return;
  root.classList.remove('pf2-dark');
}
function _pfToggleTheme() {
  const dark = localStorage.getItem('pf_theme') !== 'dark';
  localStorage.setItem('pf_theme', dark ? 'dark' : 'light');
  _pfApplyTheme();
}

/* ---------- wiring (once) ---------- */
function _pfWire() {
  if (_pfWired) return;
  _pfWired = true;

  document.getElementById('pf-theme-btn')?.addEventListener('click', _pfToggleTheme);
  document.getElementById('pf-share-btn')?.addEventListener('click', _pfShareProfile);

  // avatar / cover
  const avBtn = document.getElementById('pf-avatar-btn'), avIn = document.getElementById('pf-avatar-input');
  avBtn?.addEventListener('click', () => avIn?.click());
  avIn?.addEventListener('change', () => { if (avIn.files && avIn.files[0]) _pfUploadImage('avatar', avIn.files[0]); });
  const cvBtn = document.getElementById('pf-cover-btn'), cvIn = document.getElementById('pf-cover-input');
  cvBtn?.addEventListener('click', () => cvIn?.click());
  cvIn?.addEventListener('change', () => { if (cvIn.files && cvIn.files[0]) _pfUploadImage('cover', cvIn.files[0]); });

  // sidebar file upload
  const upBtn = document.getElementById('pf-upload-btn'), upIn = document.getElementById('pf-file-input');
  upBtn?.addEventListener('click', () => upIn?.click());
  upIn?.addEventListener('change', () => { if (upIn.files && upIn.files[0]) _pfUploadFile(upIn.files[0]); });

  // edit modal
  document.getElementById('pf-edit-btn')?.addEventListener('click', _pfOpenEdit);
  document.getElementById('pf-saved-btn')?.addEventListener('click', _pfOpenSaved);
  document.getElementById('pf-edit-close')?.addEventListener('click', _pfCloseEdit);
  document.getElementById('pf-edit-cancel')?.addEventListener('click', _pfCloseEdit);
  document.getElementById('pf-edit-backdrop')?.addEventListener('click', _pfCloseEdit);
  document.getElementById('pf-save')?.addEventListener('click', _pfSave);

  // composer
  document.getElementById('pf-post-media-btn')?.addEventListener('click', () => document.getElementById('pf-post-media')?.click());
  document.getElementById('pf-post-doc-btn')?.addEventListener('click', () => document.getElementById('pf-post-doc')?.click());
  document.getElementById('pf-post-media')?.addEventListener('change', (e) => { _pfPostFile = (e.target.files && e.target.files[0]) || null; _pfRenderPreview(); });
  document.getElementById('pf-post-doc')?.addEventListener('change', (e) => { _pfPostFile = (e.target.files && e.target.files[0]) || null; _pfRenderPreview(); });
  document.getElementById('pf-post-btn')?.addEventListener('click', _pfCreatePost);

  // drag & drop onto composer
  const comp = document.getElementById('pf-composer');
  if (comp) {
    ['dragenter', 'dragover'].forEach(ev => comp.addEventListener(ev, (e) => { e.preventDefault(); comp.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(ev => comp.addEventListener(ev, (e) => { e.preventDefault(); if (ev === 'drop' || e.target === comp) comp.classList.remove('dragging'); }));
    comp.addEventListener('drop', (e) => { const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if (f) { _pfPostFile = f; _pfRenderPreview(); } });
  }

  // feed delegation
  const feed = document.getElementById('pf-feed');
  feed?.addEventListener('click', _pfFeedClick);
  feed?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter' && !ev.shiftKey && ev.target.classList && ev.target.classList.contains('pf2-comment-input')) {
      ev.preventDefault();
      _pfComment(parseInt(ev.target.dataset.post, 10), ev.target, ev.target.dataset.parent ? parseInt(ev.target.dataset.parent, 10) : 0);
    }
  });
  // close open menus/popovers when clicking elsewhere (bind once at document level)
  if (!window.__pfDocWired) {
    window.__pfDocWired = true;
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.pf2-menu-wrap')) document.querySelectorAll('.pf2-menu').forEach(m => m.remove());
      if (!e.target.closest('.pf2-act.like-wrap')) document.querySelectorAll('.pf2-rx-pop').forEach(p => p.remove());
    });
  }
}

/* ---------- edit profile ---------- */
function _pfOpenEdit() {
  const u = (_pfData && _pfData.profile) || {};
  const val = (id, v) => { const el = document.getElementById(id); if (el) el.value = (v == null ? '' : v); };
  val('pf-first', u.first_name); val('pf-last', u.last_name); val('pf-phone', u.phone);
  val('pf-age', u.age); val('pf-city', u.city); val('pf-country', u.country);
  val('pf-language', u.language || ''); val('pf-email-ro', u.email); val('pf-bio', u.bio);
  const err = document.getElementById('pf-err'); if (err) err.style.display = 'none';
  const m = document.getElementById('pf-edit-modal'); if (m) m.hidden = false;
}
function _pfCloseEdit() { const m = document.getElementById('pf-edit-modal'); if (m) m.hidden = true; }
async function _pfSave() {
  const err = document.getElementById('pf-err');
  const show = (m) => { if (err) { err.textContent = m; err.style.display = 'block'; } };
  const g = (id) => (document.getElementById(id)?.value || '').trim();
  const payload = {
    first_name: g('pf-first'), last_name: g('pf-last'), phone: g('pf-phone'),
    age: g('pf-age'), city: g('pf-city'), country: g('pf-country'),
    language: g('pf-language'), bio: g('pf-bio'), csrf: await _pfCsrfToken(),
  };
  if (!payload.first_name || !payload.last_name) { show('First and last name are required.'); return; }
  try {
    const r = await GT.api.post('profile.php?action=update', payload);
    if (!r || !r.ok) { show((r && r.error) || 'Could not save.'); return; }
    _pfToast('Profile updated.', 'success');
    _pfCloseEdit();
    await initProfile();
  } catch (e) { show('Request failed.'); }
}

/* ---------- avatar / cover / file uploads ---------- */
async function _pfUploadImage(kind, file) {
  try {
    const fd = new FormData(); fd.append('file', file); fd.append('csrf', await _pfCsrfToken());
    const r = await GT.api.postForm('profile.php?action=' + kind, fd);
    if (!r || !r.ok) return _pfToast((r && r.error) || 'Upload failed.', 'error');
    _pfToast(kind === 'cover' ? 'Cover updated.' : 'Photo updated.', 'success');
    if (_pfData && _pfData.profile) { if (kind === 'cover') _pfData.profile.cover_path = r.cover_path; else _pfData.profile.avatar_path = r.avatar_path; }
    _pfRenderAvatar(_pfData.profile); _pfRenderCover(_pfData.profile); _pfRenderComposerAvatar(_pfData.profile);
  } catch (e) { _pfToast('Upload failed.', 'error'); }
}
async function _pfUploadFile(file) {
  try {
    const fd = new FormData(); fd.append('file', file); fd.append('csrf', await _pfCsrfToken());
    const r = await GT.api.postForm('profile.php?action=upload', fd);
    if (!r || !r.ok) return _pfToast((r && r.error) || 'Upload failed.', 'error');
    _pfToast('File uploaded.', 'success');
    await initProfile();
  } catch (e) { _pfToast('Upload failed.', 'error'); }
}
async function _pfDeleteFile(id) {
  if (!confirm('Delete this file?')) return;
  try {
    const r = await GT.api.post('profile.php?action=delete-file', { file_id: id, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) return _pfToast((r && r.error) || 'Delete failed.', 'error');
    _pfToast('File deleted.', 'success');
    await initProfile();
  } catch (e) { _pfToast('Delete failed.', 'error'); }
}

/* ---------- share profile ---------- */
function _pfShareProfile() {
  const link = location.origin + location.pathname + '#/profile' + (_pfViewUserId ? ('?u=' + _pfViewUserId) : '');
  _pfCopy(link, 'Profile link copied!');
}
function _pfCopy(text, okMsg) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => _pfToast(okMsg, 'success')).catch(() => prompt('Copy this link:', text));
  } else { prompt('Copy this link:', text); }
}

/* ---------- composer preview ---------- */
function _pfRenderPreview() {
  const box = document.getElementById('pf-post-preview');
  if (!box) return;
  if (!_pfPostFile) { box.hidden = true; box.innerHTML = ''; return; }
  box.hidden = false;
  const k = _pfKind(_pfPostFile.name);
  const url = URL.createObjectURL(_pfPostFile);
  let inner;
  if (k === 'image') inner = '<img src="' + url + '" alt="preview">';
  else if (k === 'video') inner = '<video src="' + url + '" controls></video>';
  else inner = '<div class="pf2-doc-chip">\u{1F4CE} ' + _pfEsc(_pfPostFile.name) + ' \u00b7 ' + _pfHumanSize(_pfPostFile.size) + '</div>';
  box.innerHTML = inner + '<button class="pf2-x" id="pf-preview-x" type="button">\u2715</button>';
  document.getElementById('pf-preview-x')?.addEventListener('click', () => {
    _pfPostFile = null;
    const a = document.getElementById('pf-post-media'); if (a) a.value = '';
    const b = document.getElementById('pf-post-doc'); if (b) b.value = '';
    _pfRenderPreview();
  });
}

/* ---------- infinite scroll ---------- */
function _pfSetupInfiniteScroll() {
  const sentinel = document.getElementById('pf-feed-sentinel');
  if (!sentinel || _pfFeedObserver) return;
  _pfFeedObserver = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && _pfFeedHasMore && !_pfFeedLoading) _pfLoadFeed(false);
  }, { rootMargin: '400px' });
  _pfFeedObserver.observe(sentinel);
}

/* ---------- feed load/render ---------- */
async function _pfLoadFeed(reset) {
  const wrap = document.getElementById('pf-feed');
  const spin = document.getElementById('pf-feed-spinner');
  if (!wrap) return;
  if (_pfFeedLoading) return;
  _pfFeedLoading = true;
  if (reset) { _pfFeedOldest = 0; _pfFeedHasMore = true; }
  if (spin) spin.hidden = false;
  try {
    const params = { action: 'list' };
    if (_pfViewUserId) params.user_id = _pfViewUserId; // show only this profile owner's posts
    if (!reset && _pfFeedOldest) params.before = _pfFeedOldest;
    const r = await GT.api.get('feed.php', params);
    if (!r || r.ok === false) { if (reset) wrap.innerHTML = '<div class="empty" style="color:#b91c1c">' + _pfEsc((r && r.error) || 'Failed to load the feed.') + '</div>'; _pfFeedHasMore = false; return; }
    const posts = r.posts || [];
    window._pfPostCache = window._pfPostCache || {};
    posts.forEach(p => { window._pfPostCache[p.id] = p; });
    if (reset) {
      const ownerId = _pfViewUserId || (window.GT_USER && GT_USER.id) || (_pfData && _pfData.profile && _pfData.profile.id) || 0;
      const imgs = [];
      posts.forEach(p => {
        if (ownerId && p.user_id !== ownerId) return;
        if (p.attach && p.attach.kind === 'image' && p.attach.path) imgs.push(GT.api.asset(p.attach.path));
        if (p.shared && p.shared.attach && p.shared.attach.kind === 'image' && p.shared.attach.path) imgs.push(GT.api.asset(p.shared.attach.path));
      });
      window._pfPostImgs = imgs;
      _pfRenderPhotosBox();
    }
    if (reset && !posts.length) { wrap.innerHTML = '<div class="empty">No posts yet \u2014 be the first to share something!</div>'; }
    else {
      const html = posts.map(_pfPostCard).join('');
      if (reset) wrap.innerHTML = html; else wrap.insertAdjacentHTML('beforeend', html);
    }
    if (posts.length) _pfFeedOldest = posts[posts.length - 1].id;
    _pfFeedHasMore = !!r.has_more && posts.length > 0;
  } catch (e) {
    if (reset) wrap.innerHTML = '<div class="empty" style="color:#b91c1c">Request failed.</div>';
    _pfFeedHasMore = false;
  } finally {
    _pfFeedLoading = false;
    if (spin) spin.hidden = true;
  }
}

function _pfAvatarHtml(avatar, name, cls, userId) {
  const inner = avatar
    ? '<div class="pf2-avatar ' + cls + '"><img src="' + _pfEsc(GT.api.asset(avatar)) + '" alt=""></div>'
    : '<div class="pf2-avatar ' + cls + '"><span>' + _pfEsc(_pfInitial(name)) + '</span></div>';
  return userId ? '<a class="pf2-ulink" href="#/profile?u=' + userId + '">' + inner + '</a>' : inner;
}

function _pfMediaHtml(att) {
  if (!att || !att.path) return '';
  const url = _pfEsc(GT.api.asset(att.path));
  if (att.kind === 'image') return '<div class="pf2-media"><a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt=""></a></div>';
  if (att.kind === 'video') return '<div class="pf2-media"><video src="' + url + '" controls preload="metadata"></video></div>';
  return '<a class="pf2-doc" href="' + url + '" target="_blank" rel="noopener" download>\u{1F4C4} ' + _pfEsc(att.name || 'file') + ' <span>' + _pfHumanSize(att.size) + '</span></a>';
}

function _pfCommentHtml(c, postId, depth) {
  const del = c.can_delete ? '<button data-del-comment="' + c.id + '" type="button">Delete</button>' : '';
  const edit = c.can_edit ? '<button data-edit-comment="' + c.id + '" type="button">Edit</button>' : '';
  const reply = depth === 0 ? '<button data-reply="' + c.id + '" data-post="' + postId + '" type="button">Reply</button>' : '';
  const edited = c.edited ? ' <span class="muted">(edited)</span>' : '';
  const replies = (c.replies && c.replies.length)
    ? '<div class="pf2-replies">' + c.replies.map(rc => _pfCommentHtml(rc, postId, 1)).join('') + '</div>' : '';
  return '<div class="pf2-comment" data-comment="' + c.id + '">'
    + _pfAvatarHtml(c.avatar, c.author, 'xs', c.user_id)
    + '<div class="pf2-c-main">'
    + '<div class="pf2-c-bubble"><b><a class="pf2-uname" href="#/profile?u=' + c.user_id + '">' + _pfEsc(c.author) + '</a></b><span data-body="' + c.id + '">' + _pfLinkify(c.body) + '</span></div>'
    + '<div class="pf2-c-actions"><span>' + _pfTimeAgo(c.created_at) + edited + '</span>' + reply + edit + del + '</div>'
    + replies
    + '</div></div>';
}

function _pfPostCard(p) {
  const roleLbl = _PF_ROLE[p.role] || p.role || '';
  const followBtn = (!p.is_mine)
    ? '<button class="pf2-follow-btn' + (p.is_following_author ? ' on' : '') + '" data-follow="' + p.user_id + '" type="button">' + (p.is_following_author ? 'Following' : '+ Follow') + '</button>'
    : '';

  // options menu
  const menu = '<div class="pf2-menu-wrap"><button class="pf2-menu-btn" data-menu="' + p.id + '" type="button">\u22EF</button></div>';

  // reactions summary
  const rx = p.reactions || {};
  const rxKeys = Object.keys(rx);
  const rxIcons = rxKeys.slice(0, 3).map(e => '<span>' + e + '</span>').join('');
  const total = p.reaction_total || 0;
  const commentCount = (p.comments || []).reduce((n, c) => n + 1 + ((c.replies && c.replies.length) || 0), 0);
  const countsLeft = total ? '<button class="pf2-rx-summary" data-reactors="' + p.id + '" type="button"><span class="rx-icons">' + rxIcons + '</span> ' + total + '</button>' : '';
  const countsRight = (commentCount ? commentCount + ' comments' : '') + (p.save_count ? '  \u00b7  ' + p.save_count + ' saved' : '');

  // my reaction label on the Like button
  const myR = p.my_reaction;
  const myReaction = myR ? (_PF_REACTIONS.find(x => x.e === myR) || { e: myR, label: 'Like' }) : null;
  const likeLabel = myReaction ? (myReaction.e + ' ' + myReaction.label) : '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg> Like';

  const comments = (p.comments || []).map(c => _pfCommentHtml(c, p.id, 0)).join('');

  return '<article class="pf2-post" data-post="' + p.id + '">'
    + '<header class="pf2-post-head">' + _pfAvatarHtml(p.avatar, p.author, 'sm', p.user_id)
    + '<div class="pf2-post-who"><b><a class="pf2-uname" href="#/profile?u=' + p.user_id + '">' + _pfEsc(p.author) + '</a></b><span class="sub">'
    + _pfEsc(roleLbl) + (roleLbl ? ' \u00b7 ' : '') + _pfTimeAgo(p.created_at) + '</span></div>'
    + followBtn + menu + '</header>'
    + (p.body ? '<div class="pf2-post-body">' + _pfLinkify(p.body) + '</div>' : '')
    + _pfSharedHtml(p.shared, p.shared_from)
    + _pfMediaHtml(p.attach)
    + '<div class="pf2-counts"><span>' + countsLeft + '</span><span>' + countsRight + '</span></div>'
    + '<div class="pf2-actionsbar">'
    +   '<button class="pf2-act like-wrap' + (myR ? ' on' : '') + '" data-like="' + p.id + '" type="button">' + likeLabel + '</button>'
    +   '<button class="pf2-act" data-focus-comment="' + p.id + '" type="button"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Comment</button>'
    +   '<button class="pf2-act" data-share="' + p.id + '" type="button"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg> Share</button>'
    +   '<button class="pf2-act' + (p.saved ? ' on' : '') + '" data-save="' + p.id + '" type="button">' + (p.saved ? '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Saved' : '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Save') + '</button>'
    + '</div>'
    + '<div class="pf2-comments">' + comments + '</div>'
    + '<div class="pf2-comment-new">' + _pfAvatarHtml((window.GT_USER && GT_USER.avatar), (window.GT_USER && (GT_USER.full_name || GT_USER.name)), 'xs')
    +   '<input class="pf2-comment-input" data-post="' + p.id + '" placeholder="Write a comment\u2026" maxlength="2000">'
    +   '<button class="pf2-mini" data-comment-btn="' + p.id + '" type="button">Send</button></div>'
    + '</article>';
}

/* ---------- feed interactions ---------- */
function _pfFeedClick(ev) {
  const t = ev.target;
  const feed = document.getElementById('pf-feed');
  let el;
  if ((el = t.closest('[data-react-pick]'))) { _pfReact(parseInt(el.dataset.post, 10), el.dataset.reactPick); document.querySelectorAll('.pf2-rx-pop').forEach(p => p.remove()); return; }
  if ((el = t.closest('[data-reactors]'))) { _pfShowReactors(parseInt(el.dataset.reactors, 10)); return; }
  if ((el = t.closest('[data-like]'))) { _pfShowReactions(el, parseInt(el.dataset.like, 10)); return; }
  if ((el = t.closest('[data-save]'))) { _pfToggleSave(parseInt(el.dataset.save, 10), el); return; }
  if ((el = t.closest('[data-share]'))) { _pfSharePost(parseInt(el.dataset.share, 10)); return; }
  if ((el = t.closest('[data-menu]'))) { _pfOpenMenu(el, parseInt(el.dataset.menu, 10)); return; }
  if ((el = t.closest('[data-edit-post]'))) { _pfEditPost(parseInt(el.dataset.editPost, 10)); return; }
  if ((el = t.closest('[data-del-post]'))) { _pfDeletePost(parseInt(el.dataset.delPost, 10)); return; }
  if ((el = t.closest('[data-report]'))) { _pfReport(parseInt(el.dataset.report, 10)); return; }
  if ((el = t.closest('[data-copy]'))) { _pfCopyLink(parseInt(el.dataset.copy, 10)); return; }
  if ((el = t.closest('[data-follow]'))) { _pfToggleFollow(parseInt(el.dataset.follow, 10), el); return; }
  if ((el = t.closest('[data-comment-btn]'))) { const inp = feed.querySelector('.pf2-comment-input[data-post="' + el.dataset.commentBtn + '"]'); _pfComment(parseInt(el.dataset.commentBtn, 10), inp, 0); return; }
  if ((el = t.closest('[data-focus-comment]'))) { const inp = feed.querySelector('.pf2-comment-input[data-post="' + el.dataset.focusComment + '"]'); inp && inp.focus(); return; }
  if ((el = t.closest('[data-reply]'))) { _pfShowReplyBox(el, parseInt(el.dataset.post, 10), parseInt(el.dataset.reply, 10)); return; }
  if ((el = t.closest('[data-edit-comment]'))) { _pfEditComment(parseInt(el.dataset.editComment, 10), el); return; }
  if ((el = t.closest('[data-del-comment]'))) { _pfDeleteComment(parseInt(el.dataset.delComment, 10)); return; }
}

function _pfShowReactions(anchor, postId) {
  document.querySelectorAll('.pf2-rx-pop').forEach(p => p.remove());
  const pop = document.createElement('div');
  pop.className = 'pf2-rx-pop';
  pop.innerHTML = _PF_REACTIONS.map(r => '<button data-react-pick="' + r.e + '" data-post="' + postId + '" title="' + r.label + '" type="button">' + r.e + '</button>').join('');
  anchor.appendChild(pop);
}

async function _pfReact(postId, emoji) {
  try {
    const r = await GT.api.post('feed.php?action=react', { post_id: postId, emoji: emoji, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Reaction failed.', 'error'); return; }
    _pfReloadPost(postId);
  } catch (e) { _pfToast('Reaction failed.', 'error'); }
}

async function _pfToggleSave(postId, btn) {
  const saving = !(btn.classList.contains('on'));
  try {
    const r = await GT.api.post('feed.php?action=' + (saving ? 'save' : 'unsave'), { post_id: postId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Failed.', 'error'); return; }
    _pfToast(saving ? 'Saved.' : 'Removed from saved.', 'success');
    _pfReloadPost(postId);
  } catch (e) { _pfToast('Failed.', 'error'); }
}

async function _pfSharePost(postId) {
  if (!confirm('Share this post to your profile?')) return;
  try {
    const r = await GT.api.post('feed.php?action=share', { post_id: postId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Share failed.', 'error'); return; }
    _pfToast('Shared to your profile.', 'success');
    document.querySelectorAll('.pf2-menu').forEach(m => m.remove());
    _pfLoadFeed(true);
  } catch (e) { _pfToast('Share failed.', 'error'); }
}
function _pfCopyLink(postId) {
  const link = location.origin + location.pathname + '#/profile?post=' + postId;
  _pfCopy(link, 'Post link copied!');
}
function _pfSharedHtml(shared, sharedFrom) {
  if (!sharedFrom) return '';
  if (!shared) return '<div class="pf2-shared pf2-shared-gone">\u{1F517} The original post is no longer available.</div>';
  return '<div class="pf2-shared">'
    + '<div class="pf2-shared-head">' + _pfAvatarHtml(shared.avatar, shared.author, 'xs', shared.user_id)
    +   '<div class="pf2-shared-who"><b><a class="pf2-uname" href="#/profile?u=' + shared.user_id + '">' + _pfEsc(shared.author) + '</a></b>'
    +   '<span class="sub">@' + _pfEsc(shared.username || 'user') + ' \u00b7 ' + _pfTimeAgo(shared.created_at) + '</span></div></div>'
    + (shared.body ? '<div class="pf2-shared-body">' + _pfLinkify(shared.body) + '</div>' : '')
    + _pfMediaHtml(shared.attach)
    + '</div>';
}
async function _pfShowReactors(postId) {
  let data = null;
  try { data = await GT.api.get('feed.php', { action: 'reactors', post_id: postId }); } catch (e) { /* ignore */ }
  const list = (data && data.reactors) || [];
  document.querySelectorAll('.pf2-modal-back').forEach(m => m.remove());
  const groups = {};
  list.forEach(r => { (groups[r.emoji] = groups[r.emoji] || []).push(r); });
  const tabs = Object.keys(groups).map(e => '<span class="pf2-rx-tab">' + e + ' ' + groups[e].length + '</span>').join('');
  const rows = list.length
    ? list.map(r => '<div class="pf2-rx-row"><div class="pf2-rx-ava">' + _pfAvatarHtml(r.avatar, r.name, 'xs', r.user_id) + '<span class="pf2-rx-badge">' + r.emoji + '</span></div><a class="pf2-uname pf2-rx-nm" href="#/profile?u=' + r.user_id + '">' + _pfEsc(r.name) + '</a></div>').join('')
    : '<div class="empty" style="padding:18px;text-align:center">No reactions yet.</div>';
  const back = document.createElement('div');
  back.className = 'pf2-modal-back';
  back.innerHTML = '<div class="pf2-modal" role="dialog"><div class="pf2-modal-h"><b>People who reacted</b><button class="pf2-modal-x" type="button">\u2715</button></div>'
    + (tabs ? '<div class="pf2-rx-tabs">' + tabs + '</div>' : '')
    + '<div class="pf2-rx-people">' + rows + '</div></div>';
  document.body.appendChild(back);
  back.addEventListener('click', (e) => { if (e.target === back || e.target.closest('.pf2-modal-x') || e.target.closest('.pf2-uname')) back.remove(); });
}

async function _pfOpenSaved() {
  document.querySelectorAll('.pf2-saved-back').forEach(m => m.remove());
  const back = document.createElement('div');
  back.className = 'pf2-saved-back';
  back.innerHTML = '<div class="pf2-saved" role="dialog"><div class="pf2-saved-h"><b><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Saved posts</b><button class="pf2-saved-x" type="button">\u2715</button></div><div class="pf2-saved-body" id="pf-saved-body"><div class="empty" style="padding:24px;text-align:center">Loading\u2026</div></div></div>';
  document.body.appendChild(back);
  back.addEventListener('click', (e) => { if (e.target === back || e.target.closest('.pf2-saved-x')) back.remove(); });
  const body = back.querySelector('#pf-saved-body');
  body.addEventListener('click', _pfFeedClick);
  let r = null;
  try { r = await GT.api.get('feed.php', { action: 'list', saved: 1 }); } catch (e) { /* ignore */ }
  const posts = (r && r.posts) || [];
  window._pfPostCache = window._pfPostCache || {};
  posts.forEach(p => { window._pfPostCache[p.id] = p; });
  body.innerHTML = posts.length
    ? posts.map(_pfPostCard).join('')
    : '<div class="empty" style="padding:32px;text-align:center">You haven\u2019t saved any posts yet. Tap \u{1F516} Save on any post to keep it here.</div>';
}

async function _pfReport(postId) {
  if (!confirm('Report this post to the administrators?')) return;
  try {
    const r = await GT.api.post('feed.php?action=report', { post_id: postId, csrf: await _pfCsrfToken() });
    _pfToast((r && r.ok) ? 'Thanks \u2014 reported.' : 'Failed.', (r && r.ok) ? 'success' : 'error');
  } catch (e) { _pfToast('Failed.', 'error'); }
}

function _pfOpenMenu(anchor, postId) {
  const existing = anchor.parentElement.querySelector('.pf2-menu');
  document.querySelectorAll('.pf2-menu').forEach(m => m.remove());
  if (existing) return;
  const post = _pfFindPost(postId);
  const canDelete = post && post.can_delete;
  const canEdit = post && post.can_edit;
  const menu = document.createElement('div');
  menu.className = 'pf2-menu';
  menu.innerHTML =
    '<button data-copy="' + postId + '" type="button">\u{1F517} Copy link</button>'
    + '<button data-share="' + postId + '" type="button"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg> Share</button>'
    + '<button data-report="' + postId + '" type="button">\u{1F6A9} Report</button>'
    + (canEdit ? '<button data-edit-post="' + postId + '" type="button">\u270F\uFE0F Edit post</button>' : '')
    + (canDelete ? '<button class="danger" data-del-post="' + postId + '" type="button">\u{1F5D1}\uFE0F Delete post</button>' : '');
  anchor.parentElement.appendChild(menu);
}

async function _pfToggleFollow(userId, btn) {
  try {
    const r = await GT.api.post('follow.php?action=toggle', { user_id: userId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Failed.', 'error'); return; }
    // update every follow button for this author + refresh follower stat
    document.querySelectorAll('[data-follow="' + userId + '"]').forEach(b => {
      b.classList.toggle('on', r.following);
      b.textContent = r.following ? 'Following' : '+ Follow';
    });
  } catch (e) { _pfToast('Failed.', 'error'); }
}

async function _pfComment(postId, inputEl, parentId) {
  const text = (inputEl && inputEl.value || '').trim();
  if (!text) return;
  try {
    const body = { post_id: postId, body: text, csrf: await _pfCsrfToken() };
    if (parentId) body.parent_id = parentId;
    const r = await GT.api.post('feed.php?action=comment', body);
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Comment failed.', 'error'); return; }
    if (inputEl) inputEl.value = '';
    _pfReloadPost(postId);
  } catch (e) { _pfToast('Comment failed.', 'error'); }
}

function _pfShowReplyBox(anchor, postId, parentId) {
  const cmt = anchor.closest('.pf2-comment');
  if (!cmt) return;
  if (cmt.querySelector(':scope > .pf2-c-main > .pf2-reply-box')) { cmt.querySelector('.pf2-reply-box').remove(); return; }
  const box = document.createElement('div');
  box.className = 'pf2-reply-box pf2-comment-new';
  box.innerHTML = '<input class="pf2-comment-input" data-post="' + postId + '" data-parent="' + parentId + '" placeholder="Write a reply\u2026" maxlength="2000">'
    + '<button class="pf2-mini" data-comment-reply-btn="' + parentId + '" data-post="' + postId + '" type="button">Reply</button>';
  cmt.querySelector(':scope > .pf2-c-main').appendChild(box);
  const inp = box.querySelector('input'); inp && inp.focus();
  box.querySelector('[data-comment-reply-btn]').addEventListener('click', () => _pfComment(postId, inp, parentId));
}

async function _pfEditComment(commentId, anchor) {
  const span = document.querySelector('span[data-body="' + commentId + '"]');
  if (!span) return;
  const current = span.textContent;
  const next = prompt('Edit your comment:', current);
  if (next == null) return;
  const text = next.trim();
  if (!text) return;
  try {
    const r = await GT.api.post('feed.php?action=edit-comment', { comment_id: commentId, body: text, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Edit failed.', 'error'); return; }
    const post = anchor.closest('.pf2-post');
    if (post) _pfReloadPost(parseInt(post.dataset.post, 10));
  } catch (e) { _pfToast('Edit failed.', 'error'); }
}

async function _pfDeleteComment(commentId) {
  if (!confirm('Delete this comment?')) return;
  const post = document.querySelector('.pf2-comment[data-comment="' + commentId + '"]')?.closest('.pf2-post');
  try {
    const r = await GT.api.post('feed.php?action=delete-comment', { comment_id: commentId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Delete failed.', 'error'); return; }
    if (post) _pfReloadPost(parseInt(post.dataset.post, 10));
  } catch (e) { _pfToast('Delete failed.', 'error'); }
}

async function _pfDeletePost(postId) {
  if (!confirm('Delete this post?')) return;
  try {
    const r = await GT.api.post('feed.php?action=delete', { post_id: postId, csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Delete failed.', 'error'); return; }
    _pfToast('Post deleted.', 'success');
    if (window._pfPostCache) delete window._pfPostCache[postId];
    document.querySelector('.pf2-post[data-post="' + postId + '"]')?.remove();
    const el = document.getElementById('pf-stat-posts'); if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 1) - 1);
    _pfLoadFeed(true); // full refresh so reposts of this post disappear too (no trace)
  } catch (e) { _pfToast('Delete failed.', 'error'); }
}

async function _pfCreatePost() {
  const bodyEl = document.getElementById('pf-post-body');
  const err = document.getElementById('pf-post-err');
  const btn = document.getElementById('pf-post-btn');
  const body = (bodyEl && bodyEl.value || '').trim();
  if (err) err.textContent = '';
  if (!body && !_pfPostFile) { if (err) err.textContent = 'Write something or attach a file.'; return; }
  if (btn) btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('body', body);
    if (_pfPostFile) fd.append('file', _pfPostFile);
    fd.append('csrf', await _pfCsrfToken());
    const r = await GT.api.postForm('feed.php?action=create', fd);
    if (!r || !r.ok) { if (err) err.textContent = (r && r.error) || 'Could not post.'; return; }
    if (bodyEl) bodyEl.value = '';
    _pfPostFile = null;
    const a = document.getElementById('pf-post-media'); if (a) a.value = '';
    const b = document.getElementById('pf-post-doc'); if (b) b.value = '';
    _pfRenderPreview();
    _pfToast('Posted!', 'success');
    const el = document.getElementById('pf-stat-posts'); if (el) el.textContent = (parseInt(el.textContent, 10) || 0) + 1;
    await _pfLoadFeed(true);
  } catch (e) {
    if (err) err.textContent = 'Request failed.';
  } finally {
    if (btn) btn.disabled = false;
  }
}

/* ---------- reload a single post in place (keeps scroll position) ---------- */
function _pfFindPost(id) { return (window._pfPostCache && window._pfPostCache[id]) || null; }
async function _pfEditPost(postId) {
  document.querySelectorAll('.pf2-menu').forEach(m => m.remove());
  const post = _pfFindPost(postId);
  const cur = (post && post.body) || '';
  const next = prompt('Edit your post:', cur);
  if (next == null) return;
  try {
    const r = await GT.api.post('feed.php?action=edit', { post_id: postId, body: next.trim(), csrf: await _pfCsrfToken() });
    if (!r || !r.ok) { _pfToast((r && r.error) || 'Edit failed.', 'error'); return; }
    _pfToast('Post updated.', 'success');
    _pfReloadPost(postId);
  } catch (e) { _pfToast('Edit failed.', 'error'); }
}
function _pfRenderPhotosBox() {
  const photos = document.getElementById('pf-photos');
  if (!photos) return;
  const fileImgs = window._pfFileImgs || [];
  const postImgs = window._pfPostImgs || [];
  const seen = {}; const all = [];
  postImgs.concat(fileImgs).forEach(u => { if (u && !seen[u]) { seen[u] = 1; all.push(u); } });
  const top = all.slice(0, 9);
  photos.innerHTML = top.length
    ? top.map(u => { const e = _pfEsc(u); return '<a href="' + e + '" target="_blank" rel="noopener"><img src="' + e + '" alt=""></a>'; }).join('')
    : '<div class="empty">No photos yet</div>';
}
async function _pfReloadPost(postId) {
  try {
    const r = await GT.api.get('feed.php', { action: 'list', before: postId + 1 });
    if (!r || !r.ok || !r.posts) return;
    const fresh = r.posts.find(p => p.id === postId);
    const node = document.querySelector('.pf2-post[data-post="' + postId + '"]');
    if (fresh && node) {
      const tmp = document.createElement('div');
      tmp.innerHTML = _pfPostCard(fresh);
      node.replaceWith(tmp.firstElementChild);
    } else if (!fresh && node) {
      node.remove();
    }
  } catch (e) { /* ignore */ }
}
