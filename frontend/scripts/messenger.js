/* =========================================================
   Nafass Messenger — private 1:1 chat (Facebook-style popup)
   Global widget: launcher button (bottom-left) + slide-in panel with a
   conversations list and a chat view. Messaging is allowed by the backend
   only between users with a follow relationship (friend / follower / following).

   Public API:
     window.Messenger.open(userId, name, avatarPath)  -> open a chat directly
     window.Messenger.toggle()                         -> show/hide the panel
   ========================================================= */
(function () {
  'use strict';
  if (window.__msgrBooted) return;
  window.__msgrBooted = true;

  var api = (window.GT && window.GT.api) ? window.GT.api : null;
  if (!api) return;

  var state = {
    open: false,
    view: 'list',           // 'list' | 'chat'
    peer: null,             // { id, full_name, username, avatar_path, role }
    lastId: 0,
    canSend: true,
    pollThread: null,
    pollBadge: null,
    csrf: '',
  };

  var ROLE = { citizen:'Normal User', health:'Doctor', school:'School', admin:'Admin', super_admin:'Super Admin', analyst:'Analyst', user:'User' };

  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function initial(n){ n=(n||'').trim(); return n ? n.charAt(0).toUpperCase() : '?'; }
  function fmt(s){
    if(!s) return '';
    var d=new Date(String(s).replace(' ','T')); if(isNaN(d)) return '';
    var diff=(Date.now()-d.getTime())/1000;
    if(diff<60) return 'now';
    if(diff<3600) return Math.floor(diff/60)+'m';
    if(diff<86400) return Math.floor(diff/3600)+'h';
    return d.toLocaleDateString();
  }
  function avatarHtml(u, cls){
    var a=u&&u.avatar_path;
    if(a) return '<span class="msgr-av '+(cls||'')+'"><img src="'+esc(api.asset(a))+'" alt=""></span>';
    return '<span class="msgr-av '+(cls||'')+'">'+esc(initial((u&&(u.full_name||u.username))||'?'))+'</span>';
  }
  function nameOf(u){ return (u&&((u.full_name||'').trim()||u.username))||'User'; }

  async function csrf(){
    if(state.csrf) return state.csrf;
    try{ var r=await api.get('auth.php',{action:'csrf'}); state.csrf=(r&&(r.csrf||r.token))||''; }catch(e){}
    return state.csrf;
  }

  /* ---------- DOM ---------- */
  function ensureDom(){
    if(document.getElementById('msgr-root')) return;
    var wrap=document.createElement('div');
    wrap.id='msgr-root';
    wrap.innerHTML =
      '<button id="msgr-launch" class="msgr-launch" title="Messages" aria-label="Messages">'+
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 20l1.4-4.2A8.5 8.5 0 1 1 21 11.5z"/></svg>'+
        '<span id="msgr-badge" class="msgr-badge hidden">0</span>'+
      '</button>'+
      '<section id="msgr-panel" class="msgr-panel" aria-hidden="true">'+
        '<header class="msgr-head">'+
          '<button id="msgr-back" class="msgr-icon hidden" title="Back" aria-label="Back">&#8592;</button>'+
          '<div id="msgr-title" class="msgr-title">Messages</div>'+
          '<button id="msgr-close" class="msgr-icon" title="Close" aria-label="Close">&#10005;</button>'+
        '</header>'+
        '<div id="msgr-list" class="msgr-list"><div class="msgr-empty">Loading\u2026</div></div>'+
        '<div id="msgr-chat" class="msgr-chat hidden">'+
          '<div id="msgr-thread" class="msgr-thread"></div>'+
          '<form id="msgr-form" class="msgr-form">'+
            '<input id="msgr-input" class="msgr-input" type="text" placeholder="Write a message\u2026" autocomplete="off" maxlength="4000">'+
            '<button type="submit" class="msgr-send" title="Send" aria-label="Send">'+
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>'+
            '</button>'+
          '</form>'+
        '</div>'+
      '</section>';
    document.body.appendChild(wrap);
    wire();
  }

  function wire(){
    document.getElementById('msgr-launch').addEventListener('click', toggle);
    document.getElementById('msgr-close').addEventListener('click', function(){ setOpen(false); });
    document.getElementById('msgr-back').addEventListener('click', showList);
    document.getElementById('msgr-form').addEventListener('submit', onSend);
    document.getElementById('msgr-list').addEventListener('click', function(e){
      var row=e.target.closest('[data-uid]'); if(!row) return;
      openChat({ id:parseInt(row.dataset.uid,10), full_name:row.dataset.name, username:row.dataset.username, avatar_path:row.dataset.avatar||null, role:row.dataset.role||'' });
    });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && state.open) setOpen(false); });
  }

  /* ---------- open / close ---------- */
  function toggle(){ setOpen(!state.open); }
  function setOpen(v){
    state.open=v;
    var p=document.getElementById('msgr-panel');
    var l=document.getElementById('msgr-launch');
    if(p){ p.classList.toggle('open', v); p.setAttribute('aria-hidden', v?'false':'true'); }
    if(l){ l.classList.toggle('active', v); }
    if(v){ if(state.view==='chat' && state.peer){ startThreadPoll(); } else { showList(); } }
    else { stopThreadPoll(); }
  }

  /* ---------- conversations list ---------- */
  async function showList(){
    state.view='list'; state.peer=null; stopThreadPoll();
    document.getElementById('msgr-back').classList.add('hidden');
    document.getElementById('msgr-title').textContent='Messages';
    document.getElementById('msgr-chat').classList.add('hidden');
    var list=document.getElementById('msgr-list'); list.classList.remove('hidden');
    try{
      var r=await api.get('messages.php',{action:'conversations'});
      if(!r||r.ok===false){ list.innerHTML='<div class="msgr-empty">Could not load.</div>'; return; }
      var cs=r.conversations||[];
      if(!cs.length){ list.innerHTML='<div class="msgr-empty">No conversations yet.<br><span class="msgr-hint">Open a friend\u2019s profile and tap \u201cMessage\u201d.</span></div>'; return; }
      list.innerHTML=cs.map(function(c){
        var u=c.user||{}; var pre=c.last_from_me?'You: ':'';
        return '<div class="msgr-row'+(c.unread?' unread':'')+'" data-uid="'+u.id+'" data-name="'+esc(nameOf(u))+'" data-username="'+esc(u.username||'')+'" data-avatar="'+esc(u.avatar_path||'')+'" data-role="'+esc(u.role||'')+'">'+
          avatarHtml(u)+
          '<div class="msgr-row-main"><div class="msgr-row-top"><span class="msgr-row-name">'+esc(nameOf(u))+'</span><span class="msgr-row-time">'+fmt(c.last_at)+'</span></div>'+
          '<div class="msgr-row-last">'+esc(pre+(c.last_body||''))+'</div></div>'+
          (c.unread?'<span class="msgr-row-dot">'+c.unread+'</span>':'')+'</div>';
      }).join('');
    }catch(e){ list.innerHTML='<div class="msgr-empty">Could not load.</div>'; }
  }

  /* ---------- chat view ---------- */
  function openChat(peer){
    if(!peer||!peer.id) return;
    state.view='chat'; state.peer=peer; state.lastId=0;
    document.getElementById('msgr-list').classList.add('hidden');
    document.getElementById('msgr-chat').classList.remove('hidden');
    document.getElementById('msgr-back').classList.remove('hidden');
    document.getElementById('msgr-title').textContent=nameOf(peer);
    document.getElementById('msgr-thread').innerHTML='<div class="msgr-empty">Loading\u2026</div>';
    loadThread(true).then(function(){ startThreadPoll(); });
    setTimeout(function(){ var i=document.getElementById('msgr-input'); if(i) i.focus(); }, 120);
  }

  async function loadThread(reset){
    if(!state.peer) return;
    try{
      var q={ action:'thread', user_id:state.peer.id };
      if(!reset && state.lastId) q.after=state.lastId;
      var r=await api.get('messages.php', q);
      if(!r||r.ok===false) return;
      state.canSend = (r.can_send!==false);
      var thread=document.getElementById('msgr-thread');
      var msgs=r.messages||[];
      if(reset){
        if(!msgs.length){ thread.innerHTML='<div class="msgr-empty">Say hi \u{1F44B}</div>'; }
        else { thread.innerHTML=msgs.map(bubble).join(''); }
      } else if(msgs.length){
        if(thread.querySelector('.msgr-empty')) thread.innerHTML='';
        thread.insertAdjacentHTML('beforeend', msgs.map(bubble).join(''));
      }
      if(msgs.length){ state.lastId=msgs[msgs.length-1].id; scrollBottom(); }
      var form=document.getElementById('msgr-form');
      var input=document.getElementById('msgr-input');
      if(!state.canSend){ input.disabled=true; input.placeholder='You must follow each other to chat.'; form.classList.add('disabled'); }
      else { input.disabled=false; input.placeholder='Write a message\u2026'; form.classList.remove('disabled'); }
    }catch(e){}
  }

  function bubble(m){
    return '<div class="msgr-b '+(m.mine?'me':'them')+'"><div class="msgr-bubble">'+esc(m.body)+'</div><div class="msgr-b-time">'+fmt(m.created_at)+'</div></div>';
  }
  function scrollBottom(){ var t=document.getElementById('msgr-thread'); if(t) t.scrollTop=t.scrollHeight; }

  async function onSend(e){
    e.preventDefault();
    if(!state.peer || !state.canSend) return;
    var input=document.getElementById('msgr-input');
    var body=(input.value||'').trim();
    if(!body) return;
    input.value='';
    try{
      var r=await api.post('messages.php?action=send', { user_id:state.peer.id, body:body, csrf:await csrf() });
      if(!r||!r.ok){ input.value=body; return; }
      await loadThread(false);
    }catch(err){ input.value=body; }
  }

  /* ---------- polling ---------- */
  function startThreadPoll(){ stopThreadPoll(); state.pollThread=setInterval(function(){ if(state.open && state.view==='chat') loadThread(false); }, 4000); }
  function stopThreadPoll(){ if(state.pollThread){ clearInterval(state.pollThread); state.pollThread=null; } }

  async function refreshBadge(){
    try{
      var r=await api.get('messages.php',{action:'unread'});
      var n=(r&&r.ok!==false)?(r.count||0):0;
      var b=document.getElementById('msgr-badge');
      if(b){ if(n>0){ b.textContent=n>99?'99+':n; b.classList.remove('hidden'); } else { b.classList.add('hidden'); } }
      if(state.open && state.view==='list' && n>0) showList();
    }catch(e){}
  }

  /* ---------- public API ---------- */
  window.Messenger = {
    open: function(userId, name, avatar){
      ensureDom(); setOpen(true);
      openChat({ id:parseInt(userId,10), full_name:name||'User', username:'', avatar_path:avatar||null, role:'' });
    },
    toggle: function(){ ensureDom(); toggle(); },
  };

  /* ---------- boot ---------- */
  function boot(){
    ensureDom();
    refreshBadge();
    state.pollBadge=setInterval(refreshBadge, 20000);
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
