/* =========================================================
   Dashboard Community Feed (Facebook-style Home)
   Integrates a live social feed INTO the existing dashboard
   without altering any existing dashboard widgets.
   Reuses backend/api/feed.php (global feed of all users).
   ========================================================= */
'use strict';

let _dfPostFile = null;
let _dfFeedOldest = 0;
let _dfNewest = 0;
let _dfLoading = false;
let _dfHasMore = true;
let _dfObserver = null;
let _dfPoll = null;
let _dfSeen = {};

const _DF_REACTIONS = [
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
const _DF_VERIFIED_ROLES = { admin: 1, super_admin: 1, health: 1 };
const _DF_ROLE = { citizen: 'Normal User', health: 'Doctor', school: 'School', admin: 'Admin', super_admin: 'Super Admin', analyst: 'Analyst', user: 'User' };

/* ---------- helpers ---------- */
async function _dfCsrf() { try { const r = await GT.api.get('auth.php', { action: 'csrf' }); return (r && (r.csrf || r.token)) || ''; } catch (e) { return ''; } }
function _dfToast(m, t) { if (window.GT && GT.toast) { GT.toast(m, t); return; } if (t === 'error') alert(m); }
function _dfEsc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function _dfLinkify(s) {
  let h = _dfEsc(s);
  h = h.replace(/(^|\s)(#[\p{L}0-9_]+)/gu, '$1<span class="df-tag">$2</span>');
  h = h.replace(/(^|\s)@([\p{L}0-9_.]+)/gu, '$1<span class="df-mention">@$2</span>');
  h = h.replace(/(https?:\/\/[^\s<]+)/g, '<a class="df-link" href="$1" target="_blank" rel="noopener">$1</a>');
  return h;
}
function _dfInitial(n) { return (String(n || '?').trim().charAt(0) || '?').toUpperCase(); }
function _dfHumanSize(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(1) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }
function _dfKind(name) { const e = (String(name).split('.').pop() || '').toLowerCase(); if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(e)) return 'image'; if (['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'].includes(e)) return 'video'; return 'file'; }
function _dfFmtDate(s) { if (!s) return '\u2014'; const d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return _dfEsc(s); return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
function _dfTimeAgo(s) { if (!s) return ''; const d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return _dfEsc(s); const sec = Math.floor((Date.now() - d.getTime()) / 1000); if (sec < 60) return 'just now'; if (sec < 3600) return Math.floor(sec / 60) + 'm'; if (sec < 86400) return Math.floor(sec / 3600) + 'h'; if (sec < 604800) return Math.floor(sec / 86400) + 'd'; return _dfFmtDate(s); }

/* ---------- entry (chained after initDashboard) ---------- */
function initDashboardFeed() {
  const root = document.getElementById('df-root');
  if (!root) return; // dashboard variant without the feed section

  // reset per-mount state
  _dfPostFile = null; _dfFeedOldest = 0; _dfNewest = 0; _dfLoading = false; _dfHasMore = true; _dfSeen = {};
  if (_dfObserver) { try { _dfObserver.disconnect(); } catch (e) {} _dfObserver = null; }
  if (_dfPoll) { clearInterval(_dfPoll); _dfPoll = null; }

  _dfApplyTheme();
  _dfRenderComposerAvatar();
  _dfWire();
  _dfSetupInfiniteScroll();
  _dfLoadFeed(true);

  // Light real-time: poll for new posts every 25s (only when tab visible).
  _dfPoll = setInterval(() => {
    if (document.hidden) return;
    if (!document.getElementById('df-root')) { clearInterval(_dfPoll); _dfPoll = null; return; }
    _dfCheckNew();
  }, 25000);
}

/* ---------- theme ---------- */
function _dfApplyTheme() {
  // Light/dark toggle removed from the dashboard — always keep the clean light theme.
  const root = document.getElementById('df-root');
  if (!root) return;
  root.classList.remove('df-dark');
}
function _dfToggleTheme() {
  const dark = localStorage.getItem('pf_theme') !== 'dark';
  localStorage.setItem('pf_theme', dark ? 'dark' : 'light');
  _dfApplyTheme();
}

function _dfMe() { return (window.GT_USER || {}); }
function _dfRenderComposerAvatar() {
  const box = document.getElementById('df-composer-avatar');
  if (!box) return;
  const me = _dfMe();
  if (me.avatar) box.innerHTML = '<img src="' + _dfEsc(GT.api.asset(me.avatar)) + '" alt="me">';
  else box.innerHTML = '<span>' + _dfEsc(_dfInitial(me.full_name || me.name)) + '</span>';
}

/* ---------- wiring ---------- */
function _dfWire() {
  document.getElementById('df-theme-btn')?.addEventListener('click', _dfToggleTheme);
  document.getElementById('df-refresh')?.addEventListener('click', () => _dfLoadFeed(true));

  document.getElementById('df-post-media-btn')?.addEventListener('click', () => document.getElementById('df-post-media')?.click());
  document.getElementById('df-post-doc-btn')?.addEventListener('click', () => document.getElementById('df-post-doc')?.click());
  document.getElementById('df-post-media')?.addEventListener('change', (e) => { _dfPostFile = (e.target.files && e.target.files[0]) || null; _dfRenderPreview(); });
  document.getElementById('df-post-doc')?.addEventListener('change', (e) => { _dfPostFile = (e.target.files && e.target.files[0]) || null; _dfRenderPreview(); });
  document.getElementById('df-post-btn')?.addEventListener('click', _dfCreatePost);

  const comp = document.getElementById('df-composer');
  if (comp) {
    ['dragenter', 'dragover'].forEach(ev => comp.addEventListener(ev, (e) => { e.preventDefault(); comp.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(ev => comp.addEventListener(ev, (e) => { e.preventDefault(); if (ev === 'drop' || e.target === comp) comp.classList.remove('dragging'); }));
    comp.addEventListener('drop', (e) => { const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if (f) { _dfPostFile = f; _dfRenderPreview(); } });
  }

  const feed = document.getElementById('df-feed');
  feed?.addEventListener('click', _dfFeedClick);
  feed?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter' && !ev.shiftKey && ev.target.classList && ev.target.classList.contains('df-comment-input')) {
      ev.preventDefault();
      _dfComment(parseInt(ev.target.dataset.post, 10), ev.target, ev.target.dataset.parent ? parseInt(ev.target.dataset.parent, 10) : 0);
    }
  });
  document.getElementById('df-search')?.addEventListener('input', _dfSearchFilter);
  document.getElementById('df-fab-compose')?.addEventListener('click', () => {
    document.getElementById('df-composer')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => document.getElementById('df-post-body')?.focus(), 350);
  });
  document.getElementById('df-fab-top')?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  document.querySelector('.df-side')?.addEventListener('click', _dfSideClick);
  document.querySelector('.df-nav')?.addEventListener('click', _dfNavClick);
  document.addEventListener('click', _dfDocClick);
}
/* click a hashtag chip -> put it in the search box and filter */
function _dfSideClick(e) {
  const chip = e.target.closest('[data-hashtag]');
  if (!chip) return;
  e.preventDefault();
  const s = document.getElementById('df-search');
  if (s) { s.value = chip.getAttribute('data-hashtag'); _dfSearchFilter(); s.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
}
/* left-nav items not yet routed show a gentle notice */
function _dfNavClick(e) {
  const item = e.target.closest('.df-navitem[data-soon]');
  if (!item) return;
  e.preventDefault();
  _dfToast((item.textContent || 'This section').trim() + ' \u2014 coming soon.', 'info');
}
function _dfDocClick(e) {
  if (!e.target.closest('.df-menu-wrap')) document.querySelectorAll('.df-menu').forEach(m => m.remove());
  if (!e.target.closest('.df-act.like-wrap')) document.querySelectorAll('.df-rx-pop').forEach(p => p.remove());
}

/* ---------- composer ---------- */
function _dfRenderPreview() {
  const box = document.getElementById('df-post-preview');
  if (!box) return;
  if (!_dfPostFile) { box.hidden = true; box.innerHTML = ''; return; }
  box.hidden = false;
  const k = _dfKind(_dfPostFile.name);
  const url = URL.createObjectURL(_dfPostFile);
  let inner;
  if (k === 'image') inner = '<img src="' + url + '" alt="preview">';
  else if (k === 'video') inner = '<video src="' + url + '" controls></video>';
  else inner = '<div class="df-doc-chip">\u{1F4CE} ' + _dfEsc(_dfPostFile.name) + ' \u00b7 ' + _dfHumanSize(_dfPostFile.size) + '</div>';
  box.innerHTML = inner + '<button class="df-x" id="df-preview-x" type="button">\u2715</button>';
  document.getElementById('df-preview-x')?.addEventListener('click', () => {
    _dfPostFile = null;
    const a = document.getElementById('df-post-media'); if (a) a.value = '';
    const b = document.getElementById('df-post-doc'); if (b) b.value = '';
    _dfRenderPreview();
  });
}

async function _dfCreatePost() {
  const bodyEl = document.getElementById('df-post-body');
  const err = document.getElementById('df-post-err');
  const btn = document.getElementById('df-post-btn');
  const body = (bodyEl && bodyEl.value || '').trim();
  if (err) err.textContent = '';
  if (!body && !_dfPostFile) { if (err) err.textContent = 'Write something or attach a file.'; return; }
  if (btn) btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('body', body);
    if (_dfPostFile) fd.append('file', _dfPostFile);
    fd.append('csrf', await _dfCsrf());
    const r = await GT.api.postForm('feed.php?action=create', fd);
    if (!r || !r.ok) { if (err) err.textContent = (r && r.error) || 'Could not post.'; return; }
    if (bodyEl) bodyEl.value = '';
    _dfPostFile = null;
    const a = document.getElementById('df-post-media'); if (a) a.value = '';
    const b = document.getElementById('df-post-doc'); if (b) b.value = '';
    _dfRenderPreview();
    _dfToast('Posted to the community!', 'success');
    await _dfLoadFeed(true);
  } catch (e) { if (err) err.textContent = 'Request failed.'; }
  finally { if (btn) btn.disabled = false; }
}

/* ---------- infinite scroll ---------- */
function _dfSetupInfiniteScroll() {
  const sentinel = document.getElementById('df-feed-sentinel');
  if (!sentinel) return;
  _dfObserver = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && _dfHasMore && !_dfLoading) _dfLoadFeed(false);
  }, { rootMargin: '400px' });
  _dfObserver.observe(sentinel);
}

/* ---------- load / render ---------- */
async function _dfLoadFeed(reset) {
  const wrap = document.getElementById('df-feed');
  const spin = document.getElementById('df-feed-spinner');
  if (!wrap || _dfLoading) return;
  _dfLoading = true;
  if (reset) { _dfFeedOldest = 0; _dfHasMore = true; _dfSeen = {}; }
  if (spin) spin.hidden = false;
  try {
    const params = { action: 'list' };
    if (!reset && _dfFeedOldest) params.before = _dfFeedOldest;
    const r = await GT.api.get('feed.php', params);
    if (!r || r.ok === false) { if (reset) wrap.innerHTML = '<div class="df-empty" style="color:#b91c1c">' + _dfEsc((r && r.error) || 'Failed to load the feed.') + '</div>'; _dfHasMore = false; return; }
    const posts = (r.posts || []).filter(p => { if (_dfSeen[p.id]) return false; _dfSeen[p.id] = 1; return true; });
    posts.forEach(p => { _dfLoadedCache[p.id] = p; });
    if (reset && !(r.posts || []).length) { wrap.innerHTML = '<div class="df-empty">No posts yet \u2014 be the first to share something with the community!</div>'; }
    else {
      const html = posts.map(_dfPostCard).join('');
      if (reset) wrap.innerHTML = html; else wrap.insertAdjacentHTML('beforeend', html);
    }
    if ((r.posts || []).length) {
      _dfFeedOldest = r.posts[r.posts.length - 1].id;
      if (reset) _dfNewest = r.posts[0].id;
    }
    _dfHasMore = !!r.has_more && (r.posts || []).length > 0;
    if (reset) _dfRenderSidebar(r.posts || []);
  } catch (e) {
    if (reset) wrap.innerHTML = '<div class="df-empty" style="color:#b91c1c">Request failed.</div>';
    _dfHasMore = false;
  } finally {
    _dfLoading = false;
    if (spin) spin.hidden = true;
  }
}

/* ---------- real-time: prepend brand new posts ---------- */
async function _dfCheckNew() {
  try {
    const r = await GT.api.get('feed.php', { action: 'list' });
    if (!r || !r.ok || !r.posts) return;
    const fresh = r.posts.filter(p => p.id > _dfNewest && !_dfSeen[p.id]);
    const wrap = document.getElementById('df-feed');
    if (!wrap) return;
    if (fresh.length) {
      fresh.reverse().forEach(p => { _dfSeen[p.id] = 1; wrap.insertAdjacentHTML('afterbegin', _dfPostCard(p)); });
      _dfNewest = r.posts[0].id;
      const empty = wrap.querySelector('.df-empty'); if (empty) empty.remove();
    }
    _dfRenderSidebar(r.posts);
  } catch (e) { /* silent */ }
}

function _dfAvatarHtml(avatar, name, cls, userId) {
  const inner = avatar
    ? '<div class="df-avatar ' + cls + '"><img src="' + _dfEsc(GT.api.asset(avatar)) + '" alt=""></div>'
    : '<div class="df-avatar ' + cls + '"><span>' + _dfEsc(_dfInitial(name)) + '</span></div>';
  return userId ? '<a class="df-ulink" href="#/profile?u=' + userId + '">' + inner + '</a>' : inner;
}
function _dfMediaHtml(att) {
  if (!att || !att.path) return '';
  const url = _dfEsc(GT.api.asset(att.path));
  if (att.kind === 'image') return '<div class="df-media"><a href="' + url + '" target="_blank" rel="noopener"><img loading="lazy" src="' + url + '" alt=""></a></div>';
  if (att.kind === 'video') return '<div class="df-media"><video src="' + url + '" controls preload="none"></video></div>';
  return '<a class="df-doc" href="' + url + '" target="_blank" rel="noopener" download>\u{1F4C4} ' + _dfEsc(att.name || 'file') + ' <span>' + _dfHumanSize(att.size) + '</span></a>';
}
function _dfCommentHtml(c, postId, depth) {
  const del = c.can_delete ? '<button data-del-comment="' + c.id + '" type="button">Delete</button>' : '';
  const edit = c.can_edit ? '<button data-edit-comment="' + c.id + '" type="button">Edit</button>' : '';
  const reply = depth === 0 ? '<button data-reply="' + c.id + '" data-post="' + postId + '" type="button">Reply</button>' : '';
  const edited = c.edited ? ' <span class="df-muted">(edited)</span>' : '';
  const replies = (c.replies && c.replies.length) ? '<div class="df-replies">' + c.replies.map(rc => _dfCommentHtml(rc, postId, 1)).join('') + '</div>' : '';
  return '<div class="df-comment" data-comment="' + c.id + '">'
    + _dfAvatarHtml(c.avatar, c.author, 'xs', c.user_id)
    + '<div class="df-c-main">'
    + '<div class="df-c-bubble"><b><a class="df-uname" href="#/profile?u=' + c.user_id + '">' + _dfEsc(c.author) + '</a></b><span data-body="' + c.id + '">' + _dfLinkify(c.body) + '</span></div>'
    + '<div class="df-c-actions"><span>' + _dfTimeAgo(c.created_at) + edited + '</span>' + reply + edit + del + '</div>'
    + replies + '</div></div>';
}
/* role + verified badges shown next to the author name */
function _dfRoleBadge(role) {
  const lbl = _DF_ROLE[role] || role || '';
  if (!lbl) return '';
  return '<span class="df-badge df-badge-' + _dfEsc(role || 'user') + '">' + _dfEsc(lbl) + '</span>';
}
function _dfBadges(p) {
  let h = _dfRoleBadge(p.role);
  if (_DF_VERIFIED_ROLES[p.role]) h += '<span class="df-verified" title="Verified">\u2713</span>';
  return h;
}

/* global search: filter loaded posts AND find people (Facebook-style) */
let _dfSearchTimer = null;
function _dfSearchFilter() {
  const raw = (document.getElementById('df-search')?.value || '').trim();
  const q = raw.toLowerCase();
  // 1) filter the posts already loaded in the feed
  const cards = document.querySelectorAll('#df-feed .df-post');
  let shown = 0;
  cards.forEach(c => {
    const hit = !q || (c.textContent || '').toLowerCase().indexOf(q) !== -1;
    c.style.display = hit ? '' : 'none';
    if (hit) shown++;
  });
  const info = document.getElementById('df-search-info');
  if (info) info.textContent = q ? (shown + ' result' + (shown === 1 ? '' : 's') + ' in loaded posts') : '';
  // 2) live people search (debounced). Skip pure #hashtag queries.
  clearTimeout(_dfSearchTimer);
  if (!raw || raw[0] === '#') { _dfHidePeople(); return; }
  _dfSearchTimer = setTimeout(() => _dfPeopleSearch(raw), 250);
}

async function _dfPeopleSearch(term) {
  const box = document.getElementById('df-people');
  if (!box) return;
  _dfWirePeople();
  box.hidden = false;
  box.innerHTML = '<div class="df-people-empty">Searching\u2026</div>';
  try {
    const r = await GT.api.get('follow.php', { action: 'search', q: term });
    if (!r || r.ok === false) { box.innerHTML = '<div class="df-people-empty">Search failed.</div>'; return; }
    _dfRenderPeople(r.people || []);
  } catch (e) { box.innerHTML = '<div class="df-people-empty">Search failed.</div>'; }
}

function _dfRenderPeople(people) {
  const box = document.getElementById('df-people');
  if (!box) return;
  box.hidden = false;
  if (!people.length) { box.innerHTML = '<div class="df-people-empty">No people found</div>'; return; }
  box.innerHTML = '<div class="df-people-head">People</div>' + people.map(p => {
    const role = _DF_ROLE[p.role] || p.role || '';
    const btn = p.is_me ? ''
      : '<button class="df-follow-btn' + (p.is_following ? ' on' : '') + '" data-follow="' + p.id + '" type="button">' + (p.is_following ? 'Following' : '+ Follow') + '</button>';
    return '<a class="df-person" href="#/profile?u=' + p.id + '" data-person="' + p.id + '">'
      + _dfAvatarHtml(p.avatar_path, p.full_name, 'sm')
      + '<div class="df-person-info"><b>' + _dfEsc(p.full_name || p.username || 'User') + '</b>'
      + '<span>@' + _dfEsc(p.username || 'user') + (role ? ' \u00b7 ' + _dfEsc(role) : '') + '</span></div>'
      + btn + '</a>';
  }).join('');
}

function _dfHidePeople() { const box = document.getElementById('df-people'); if (box) { box.hidden = true; box.innerHTML = ''; } }

function _dfPeopleClick(e) {
  const fb = e.target.closest('[data-follow]');
  if (fb) { e.preventDefault(); e.stopPropagation(); _dfToggleFollow(parseInt(fb.dataset.follow, 10)); return; }
  const person = e.target.closest('[data-person]');
  if (person) { _dfHidePeople(); const s = document.getElementById('df-search'); if (s) s.value = ''; }
}

function _dfWirePeople() {
  const box = document.getElementById('df-people');
  if (box && !box.__wired) { box.__wired = true; box.addEventListener('click', _dfPeopleClick); }
  if (!window.__dfPeopleDocWired) {
    window.__dfPeopleDocWired = true;
    document.addEventListener('click', (e) => { if (!e.target.closest('.df-search-wrap')) _dfHidePeople(); });
  }
}

function _dfPostCard(p) {
  const roleLbl = _DF_ROLE[p.role] || p.role || '';
  const followBtn = (!p.is_mine)
    ? '<button class="df-follow-btn' + (p.is_following_author ? ' on' : '') + '" data-follow="' + p.user_id + '" type="button">' + (p.is_following_author ? 'Following' : '+ Follow') + '</button>' : '';
  const menu = '<div class="df-menu-wrap"><button class="df-menu-btn" data-menu="' + p.id + '" type="button">\u22EF</button></div>';
  const rx = p.reactions || {};
  const rxIcons = Object.keys(rx).slice(0, 3).map(e => '<span>' + e + '</span>').join('');
  const total = p.reaction_total || 0;
  const commentCount = (p.comments || []).reduce((n, c) => n + 1 + ((c.replies && c.replies.length) || 0), 0);
  const countsLeft = total ? '<button class="df-rx-summary" data-reactors="' + p.id + '" type="button"><span class="rx-icons">' + rxIcons + '</span> ' + total + '</button>' : '';
  const countsRight = (commentCount ? commentCount + ' comments' : '') + (p.save_count ? '  \u00b7  ' + p.save_count + ' saved' : '');
  const myR = p.my_reaction;
  const myReaction = myR ? (_DF_REACTIONS.find(x => x.e === myR) || { e: myR, label: 'Like' }) : null;
  const likeLabel = myReaction ? (myReaction.e + ' ' + myReaction.label) : '\u{1F44D} Like';
  const comments = (p.comments || []).map(c => _dfCommentHtml(c, p.id, 0)).join('');
  const me = _dfMe();
  return '<article class="df-post" data-post="' + p.id + '">'
    + '<header class="df-post-head">' + _dfAvatarHtml(p.avatar, p.author, 'sm', p.user_id)
    + '<div class="df-post-who"><div class="df-name-row"><b><a class="df-uname" href="#/profile?u=' + p.user_id + '">' + _dfEsc(p.author) + '</a></b>' + _dfBadges(p) + (p.pinned ? '<span class="df-pin" title="Pinned">\u{1F4CC}</span>' : '') + '</div>'
    + '<span class="sub">@' + _dfEsc(p.username || 'user') + ' \u00b7 ' + _dfTimeAgo(p.created_at) + (p.edited ? ' \u00b7 edited' : '') + ' \u00b7 <span class="df-privacy" title="Public">\u{1F310} Public</span></span></div>'
    + followBtn + menu + '</header>'
    + (p.body ? '<div class="df-post-body">' + _dfLinkify(p.body) + '</div>' : '')
    + _dfSharedHtml(p.shared, p.shared_from)
    + _dfMediaHtml(p.attach)
    + '<div class="df-counts"><span>' + countsLeft + '</span><span>' + countsRight + '</span></div>'
    + '<div class="df-actionsbar">'
    +   '<button class="df-act like-wrap' + (myR ? ' on' : '') + '" data-like="' + p.id + '" type="button">' + likeLabel + '</button>'
    +   '<button class="df-act" data-focus-comment="' + p.id + '" type="button"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Comment</button>'
    +   '<button class="df-act" data-share="' + p.id + '" type="button"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>Share</button>'
    +   '<button class="df-act' + (p.saved ? ' on' : '') + '" data-save="' + p.id + '" type="button">' + (p.saved ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>Saved' : '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>Save') + '</button>'
    + '</div>'
    + '<div class="df-comments">' + comments + '</div>'
    + '<div class="df-comment-new">' + _dfAvatarHtml(me.avatar, me.full_name || me.name, 'xs')
    +   '<input class="df-comment-input" data-post="' + p.id + '" placeholder="Write a comment\u2026" maxlength="2000">'
    +   '<button class="df-mini" data-comment-btn="' + p.id + '" type="button">Send</button></div>'
    + '</article>';
}

/* ---------- right sidebar (derived, no extra API calls) ---------- */
function _dfRenderSidebar(posts) {
  posts = posts || [];
  const trend = document.getElementById('df-trending');
  if (trend) {
    const ranked = posts.slice().map(p => ({ p, score: (p.reaction_total || 0) + (p.comments || []).length * 2 }))
      .sort((a, b) => b.score - a.score).filter(x => x.score > 0).slice(0, 5);
    trend.innerHTML = ranked.length
      ? ranked.map(x => {
          const txt = (x.p.body || (x.p.attach ? '[' + (x.p.attach.kind || 'media') + ']' : 'Post')).slice(0, 60);
          return '<a class="df-trend-item" href="#/profile?u=' + x.p.user_id + '"><b>' + _dfEsc(x.p.author) + '</b>'
            + '<span>' + _dfEsc(txt) + '</span>'
            + '<em>\u{1F525} ' + x.score + '</em></a>';
        }).join('')
      : '<div class="df-muted">No trending posts yet.</div>';
  }
  const active = document.getElementById('df-active');
  if (active) {
    const seen = {}; const users = [];
    posts.forEach(p => { if (!seen[p.user_id]) { seen[p.user_id] = 1; users.push(p); } });
    const list = users.slice(0, 8);
    active.innerHTML = list.length
      ? list.map(p => _dfAvatarHtml(p.avatar, p.author, 'xs', p.user_id) + '<a class="df-au-name df-uname" href="#/profile?u=' + p.user_id + '">' + _dfEsc(p.author) + '</a>')
          .map(h => '<div class="df-active-item">' + h + '</div>').join('')
      : '<div class="df-muted">No active members yet.</div>';
  }
  // most-used hashtags (derived from loaded posts)
  const tagsBox = document.getElementById('df-hashtags');
  if (tagsBox) {
    const counts = {};
    posts.forEach(p => { (String(p.body || '').match(/#[\p{L}0-9_]+/gu) || []).forEach(t => { const k = t.toLowerCase(); counts[k] = (counts[k] || 0) + 1; }); });
    const top = Object.keys(counts).sort((a, b) => counts[b] - counts[a]).slice(0, 8);
    tagsBox.innerHTML = top.length
      ? top.map(t => '<button class="df-hashchip" type="button" data-hashtag="' + _dfEsc(t) + '">' + _dfEsc(t) + ' <em>' + counts[t] + '</em></button>').join('')
      : '<div class="df-muted">No hashtags yet.</div>';
  }
  // community statistics
  const statsBox = document.getElementById('df-stats');
  if (statsBox) {
    const members = Object.keys(posts.reduce((m, p) => { m[p.user_id] = 1; return m; }, {})).length;
    const totalRx = posts.reduce((n, p) => n + (p.reaction_total || 0), 0);
    const totalCm = posts.reduce((n, p) => n + (p.comments || []).reduce((k, c) => k + 1 + ((c.replies && c.replies.length) || 0), 0), 0);
    const cell = (v, l) => '<div class="df-statcell"><b>' + v + '</b><span>' + l + '</span></div>';
    statsBox.innerHTML = cell(posts.length, 'Posts') + cell(members, 'Members') + cell(totalRx, 'Reactions') + cell(totalCm, 'Comments');
  }
}

/* ---------- interactions ---------- */
function _dfFeedClick(ev) {
  const t = ev.target; const feed = document.getElementById('df-feed'); let el;
  if ((el = t.closest('[data-react-pick]'))) { _dfReact(parseInt(el.dataset.post, 10), el.dataset.reactPick); document.querySelectorAll('.df-rx-pop').forEach(p => p.remove()); return; }
  if ((el = t.closest('[data-reactors]'))) { _dfShowReactors(parseInt(el.dataset.reactors, 10)); return; }
  if ((el = t.closest('[data-like]'))) { _dfShowReactions(el, parseInt(el.dataset.like, 10)); return; }
  if ((el = t.closest('[data-save]'))) { _dfToggleSave(parseInt(el.dataset.save, 10), el); return; }
  if ((el = t.closest('[data-share]'))) { _dfSharePost(parseInt(el.dataset.share, 10)); return; }
  if ((el = t.closest('[data-menu]'))) { _dfOpenMenu(el, parseInt(el.dataset.menu, 10)); return; }
  if ((el = t.closest('[data-edit-post]'))) { _dfEditPost(parseInt(el.dataset.editPost, 10)); return; }
  if ((el = t.closest('[data-del-post]'))) { _dfDeletePost(parseInt(el.dataset.delPost, 10)); return; }
  if ((el = t.closest('[data-report]'))) { _dfReport(parseInt(el.dataset.report, 10)); return; }
  if ((el = t.closest('[data-copy]'))) { _dfCopyLink(parseInt(el.dataset.copy, 10)); return; }
  if ((el = t.closest('[data-follow]'))) { _dfToggleFollow(parseInt(el.dataset.follow, 10)); return; }
  if ((el = t.closest('[data-comment-btn]'))) { const inp = feed.querySelector('.df-comment-input[data-post="' + el.dataset.commentBtn + '"]'); _dfComment(parseInt(el.dataset.commentBtn, 10), inp, 0); return; }
  if ((el = t.closest('[data-focus-comment]'))) { const inp = feed.querySelector('.df-comment-input[data-post="' + el.dataset.focusComment + '"]'); inp && inp.focus(); return; }
  if ((el = t.closest('[data-reply]'))) { _dfShowReplyBox(el, parseInt(el.dataset.post, 10), parseInt(el.dataset.reply, 10)); return; }
  if ((el = t.closest('[data-edit-comment]'))) { _dfEditComment(parseInt(el.dataset.editComment, 10), el); return; }
  if ((el = t.closest('[data-del-comment]'))) { _dfDeleteComment(parseInt(el.dataset.delComment, 10)); return; }
}
function _dfShowReactions(anchor, postId) {
  document.querySelectorAll('.df-rx-pop').forEach(p => p.remove());
  const pop = document.createElement('div');
  pop.className = 'df-rx-pop';
  pop.innerHTML = _DF_REACTIONS.map(r => '<button data-react-pick="' + r.e + '" data-post="' + postId + '" title="' + r.label + '" type="button">' + r.e + '</button>').join('');
  anchor.appendChild(pop);
}
async function _dfReact(postId, emoji) {
  try {
    const r = await GT.api.post('feed.php?action=react', { post_id: postId, emoji: emoji, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Reaction failed.', 'error'); return; }
    _dfReloadPost(postId);
  } catch (e) { _dfToast('Reaction failed.', 'error'); }
}
async function _dfToggleSave(postId, btn) {
  const saving = !(btn.classList.contains('on'));
  try {
    const r = await GT.api.post('feed.php?action=' + (saving ? 'save' : 'unsave'), { post_id: postId, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Failed.', 'error'); return; }
    _dfToast(saving ? 'Saved.' : 'Removed from saved.', 'success');
    _dfReloadPost(postId);
  } catch (e) { _dfToast('Failed.', 'error'); }
}
async function _dfSharePost(postId) {
  if (!confirm('Share this post to your profile?')) return;
  try {
    const r = await GT.api.post('feed.php?action=share', { post_id: postId, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Share failed.', 'error'); return; }
    _dfToast('Shared to your profile.', 'success');
    document.querySelectorAll('.df-menu').forEach(m => m.remove());
    _dfLoadFeed(true);
  } catch (e) { _dfToast('Share failed.', 'error'); }
}
function _dfCopyLink(postId) { _dfCopy(location.origin + location.pathname + '#/profile?post=' + postId, 'Post link copied!'); }
function _dfSharedHtml(shared, sharedFrom) {
  if (!sharedFrom) return '';
  if (!shared) return '<div class="df-shared df-shared-gone">\u{1F517} The original post is no longer available.</div>';
  return '<div class="df-shared">'
    + '<div class="df-shared-head">' + _dfAvatarHtml(shared.avatar, shared.author, 'xs', shared.user_id)
    +   '<div class="df-shared-who"><b><a class="df-uname" href="#/profile?u=' + shared.user_id + '">' + _dfEsc(shared.author) + '</a></b>'
    +   '<span class="sub">@' + _dfEsc(shared.username || 'user') + ' \u00b7 ' + _dfTimeAgo(shared.created_at) + '</span></div></div>'
    + (shared.body ? '<div class="df-shared-body">' + _dfLinkify(shared.body) + '</div>' : '')
    + _dfMediaHtml(shared.attach)
    + '</div>';
}
async function _dfShowReactors(postId) {
  let data = null;
  try { data = await GT.api.get('feed.php', { action: 'reactors', post_id: postId }); } catch (e) { /* ignore */ }
  const list = (data && data.reactors) || [];
  document.querySelectorAll('.df-modal-back').forEach(m => m.remove());
  const groups = {};
  list.forEach(r => { (groups[r.emoji] = groups[r.emoji] || []).push(r); });
  const tabs = Object.keys(groups).map(e => '<span class="df-rx-tab">' + e + ' ' + groups[e].length + '</span>').join('');
  const rows = list.length
    ? list.map(r => '<div class="df-rx-row"><div class="df-rx-ava">' + _dfAvatarHtml(r.avatar, r.name, 'xs', r.user_id) + '<span class="df-rx-badge">' + r.emoji + '</span></div><a class="df-uname df-rx-nm" href="#/profile?u=' + r.user_id + '">' + _dfEsc(r.name) + '</a></div>').join('')
    : '<div class="df-muted" style="padding:18px;text-align:center">No reactions yet.</div>';
  const back = document.createElement('div');
  back.className = 'df-modal-back';
  back.innerHTML = '<div class="df-modal" role="dialog"><div class="df-modal-h"><b>People who reacted</b><button class="df-modal-x" type="button">\u2715</button></div>'
    + (tabs ? '<div class="df-rx-tabs">' + tabs + '</div>' : '')
    + '<div class="df-rx-people">' + rows + '</div></div>';
  document.body.appendChild(back);
  back.addEventListener('click', (e) => { if (e.target === back || e.target.closest('.df-modal-x') || e.target.closest('.df-uname')) back.remove(); });
}
function _dfCopy(text, ok) {
  if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(() => _dfToast(ok, 'success')).catch(() => prompt('Copy this link:', text));
  else prompt('Copy this link:', text);
}
async function _dfReport(postId) {
  if (!confirm('Report this post to the administrators?')) return;
  try { const r = await GT.api.post('feed.php?action=report', { post_id: postId, csrf: await _dfCsrf() }); _dfToast((r && r.ok) ? 'Thanks \u2014 reported.' : 'Failed.', (r && r.ok) ? 'success' : 'error'); }
  catch (e) { _dfToast('Failed.', 'error'); }
}
function _dfOpenMenu(anchor, postId) {
  const existing = anchor.parentElement.querySelector('.df-menu');
  document.querySelectorAll('.df-menu').forEach(m => m.remove());
  if (existing) return;
  const node = document.querySelector('.df-post[data-post="' + postId + '"]');
  const canDelete = node && node.querySelector('[data-like]'); // presence check only
  const post = _dfFindLoaded(postId);
  const showDelete = post && post.can_delete;
  const showEdit = post && post.can_edit;
  const menu = document.createElement('div');
  menu.className = 'df-menu';
  menu.innerHTML =
    '<button data-copy="' + postId + '" type="button">\u{1F517} Copy link</button>'
    + '<button data-share="' + postId + '" type="button"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>Share</button>'
    + '<button data-report="' + postId + '" type="button">\u{1F6A9} Report</button>'
    + (showEdit ? '<button data-edit-post="' + postId + '" type="button">\u270F\uFE0F Edit post</button>' : '')
    + (showDelete ? '<button class="danger" data-del-post="' + postId + '" type="button">\u{1F5D1}\uFE0F Delete post</button>' : '');
  anchor.parentElement.appendChild(menu);
}
let _dfLoadedCache = {};
function _dfFindLoaded(id) { return _dfLoadedCache[id] || null; }
async function _dfToggleFollow(userId) {
  try {
    const r = await GT.api.post('follow.php?action=toggle', { user_id: userId, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Failed.', 'error'); return; }
    document.querySelectorAll('[data-follow="' + userId + '"]').forEach(b => { b.classList.toggle('on', r.following); b.textContent = r.following ? 'Following' : '+ Follow'; });
  } catch (e) { _dfToast('Failed.', 'error'); }
}
async function _dfComment(postId, inputEl, parentId) {
  const text = (inputEl && inputEl.value || '').trim();
  if (!text) return;
  try {
    const body = { post_id: postId, body: text, csrf: await _dfCsrf() };
    if (parentId) body.parent_id = parentId;
    const r = await GT.api.post('feed.php?action=comment', body);
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Comment failed.', 'error'); return; }
    if (inputEl) inputEl.value = '';
    _dfReloadPost(postId);
  } catch (e) { _dfToast('Comment failed.', 'error'); }
}
function _dfShowReplyBox(anchor, postId, parentId) {
  const cmt = anchor.closest('.df-comment');
  if (!cmt) return;
  const main = cmt.querySelector(':scope > .df-c-main');
  if (main.querySelector(':scope > .df-reply-box')) { main.querySelector('.df-reply-box').remove(); return; }
  const box = document.createElement('div');
  box.className = 'df-reply-box df-comment-new';
  box.innerHTML = '<input class="df-comment-input" data-post="' + postId + '" data-parent="' + parentId + '" placeholder="Write a reply\u2026" maxlength="2000">'
    + '<button class="df-mini" data-reply-send="' + parentId + '" type="button">Reply</button>';
  main.appendChild(box);
  const inp = box.querySelector('input'); inp && inp.focus();
  box.querySelector('[data-reply-send]').addEventListener('click', () => _dfComment(postId, inp, parentId));
}
async function _dfEditComment(commentId, anchor) {
  const span = document.querySelector('span[data-body="' + commentId + '"]');
  if (!span) return;
  const next = prompt('Edit your comment:', span.textContent);
  if (next == null) return;
  const text = next.trim(); if (!text) return;
  try {
    const r = await GT.api.post('feed.php?action=edit-comment', { comment_id: commentId, body: text, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Edit failed.', 'error'); return; }
    const post = anchor.closest('.df-post'); if (post) _dfReloadPost(parseInt(post.dataset.post, 10));
  } catch (e) { _dfToast('Edit failed.', 'error'); }
}
async function _dfDeleteComment(commentId) {
  if (!confirm('Delete this comment?')) return;
  const post = document.querySelector('.df-comment[data-comment="' + commentId + '"]')?.closest('.df-post');
  try {
    const r = await GT.api.post('feed.php?action=delete-comment', { comment_id: commentId, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Delete failed.', 'error'); return; }
    if (post) _dfReloadPost(parseInt(post.dataset.post, 10));
  } catch (e) { _dfToast('Delete failed.', 'error'); }
}
async function _dfDeletePost(postId) {
  if (!confirm('Delete this post?')) return;
  try {
    const r = await GT.api.post('feed.php?action=delete', { post_id: postId, csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Delete failed.', 'error'); return; }
    _dfToast('Post deleted.', 'success');
    delete _dfLoadedCache[postId];
    document.querySelector('.df-post[data-post="' + postId + '"]')?.remove();
    _dfLoadFeed(true); // full refresh so reposts of this post disappear too (no trace)
  } catch (e) { _dfToast('Delete failed.', 'error'); }
}
async function _dfEditPost(postId) {
  document.querySelectorAll('.df-menu').forEach(m => m.remove());
  const post = _dfFindLoaded(postId);
  const cur = (post && post.body) || '';
  const next = prompt('Edit your post:', cur);
  if (next == null) return;
  try {
    const r = await GT.api.post('feed.php?action=edit', { post_id: postId, body: next.trim(), csrf: await _dfCsrf() });
    if (!r || !r.ok) { _dfToast((r && r.error) || 'Edit failed.', 'error'); return; }
    _dfToast('Post updated.', 'success');
    _dfReloadPost(postId);
  } catch (e) { _dfToast('Edit failed.', 'error'); }
}
async function _dfReloadPost(postId) {
  try {
    const r = await GT.api.get('feed.php', { action: 'list', before: postId + 1 });
    if (!r || !r.ok || !r.posts) return;
    const fresh = r.posts.find(p => p.id === postId);
    const node = document.querySelector('.df-post[data-post="' + postId + '"]');
    if (fresh) _dfLoadedCache[postId] = fresh;
    if (fresh && node) { const tmp = document.createElement('div'); tmp.innerHTML = _dfPostCard(fresh); node.replaceWith(tmp.firstElementChild); }
    else if (!fresh && node) node.remove();
  } catch (e) { /* ignore */ }
}

/* ---------- chain onto the existing initDashboard (do not modify it) ---------- */
(function () {
  const _orig = window.initDashboard;
  window.initDashboard = function () {
    let ret;
    if (typeof _orig === 'function') { try { ret = _orig.apply(this, arguments); } catch (e) { console.error('[dashboard]', e); } }
    try { initDashboardFeed(); } catch (e) { console.error('[dashboard-feed]', e); }
    return ret;
  };
})();
