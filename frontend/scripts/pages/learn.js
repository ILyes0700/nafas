/* Learn & Prevent — awareness hub for the Nafass / Gabès Tatenafas platform.
 *
 * Loads resources from /backend/api/learn.php, renders a filterable grid
 * (articles, videos, quizzes, infographics), and opens a modal with the
 * full content (Markdown-light → HTML, YouTube embeds, interactive quiz).
 */
(function () {
  'use strict';

  const API = '../backend/api/learn.php';

  let allItems = [];
  let activeKind = '';
  let activeCat  = '';
  let searchQ    = '';

  /* ---------- helpers ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  // Tiny markdown-ish renderer: paragraphs, ## headings, **bold**, lists, line breaks.
  // Output is then passed through esc-aware logic to avoid XSS for user-supplied data;
  // resources here are seeded by admins, but we still escape source text.
  function mdToHtml(src) {
    if (!src) return '';
    const lines = String(src).split(/\r?\n/);
    const out = [];
    let inList = false, inOl = false;
    const flush = () => {
      if (inList) { out.push('</ul>'); inList = false; }
      if (inOl)   { out.push('</ol>'); inOl   = false; }
    };
    const inline = (s) => esc(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');

    for (const raw of lines) {
      const t = raw.replace(/\s+$/, '');
      if (!t.trim()) { flush(); out.push(''); continue; }
      if (t.startsWith('## '))   { flush(); out.push(`<h3>${inline(t.slice(3))}</h3>`); continue; }
      if (t.startsWith('# '))    { flush(); out.push(`<h2>${inline(t.slice(2))}</h2>`); continue; }
      if (/^\s*[-*]\s+/.test(t)) {
        if (inOl) { out.push('</ol>'); inOl = false; }
        if (!inList) { out.push('<ul>'); inList = true; }
        out.push(`<li>${inline(t.replace(/^\s*[-*]\s+/, ''))}</li>`);
        continue;
      }
      if (/^\s*\d+\.\s+/.test(t)) {
        if (inList) { out.push('</ul>'); inList = false; }
        if (!inOl) { out.push('<ol>'); inOl = true; }
        out.push(`<li>${inline(t.replace(/^\s*\d+\.\s+/, ''))}</li>`);
        continue;
      }
      flush();
      out.push(`<p>${inline(t)}</p>`);
    }
    flush();
    return out.join('\n');
  }

  // Lucide-style line icons (stroke 1.85, currentColor) — match the sidebar.
  function kindIcon(kind) {
    const o = '<svg class="lc-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    const e = '</svg>';
    switch (kind) {
      case 'article':
        // Open book
        return o + '<path d="M2 4h6a3 3 0 013 3v13a2 2 0 00-2-2H2z"/><path d="M22 4h-6a3 3 0 00-3 3v13a2 2 0 012-2h7z"/>' + e;
      case 'video':
        // Film with play
        return o + '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="3" y1="16" x2="21" y2="16"/><line x1="8" y1="4" x2="8" y2="20"/><line x1="16" y1="4" x2="16" y2="20"/>' + e;
      case 'quiz':
        // Help-circle (matches help icon style)
        return o + '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.2a3 3 0 015.7 1c0 2-3 2.5-3 4"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/>' + e;
      case 'infographic':
        // Bar chart 4 bars
        return o + '<path d="M3 3v18h18"/><rect x="6"  y="13" width="3" height="6"/><rect x="11" y="9"  width="3" height="10"/><rect x="16" y="5"  width="3" height="14"/>' + e;
      default:
        // Generic bookmark
        return o + '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>' + e;
    }
  }

  function kindLabel(kind) {
    switch (kind) {
      case 'video':       return 'Video';
      case 'article':     return 'Article';
      case 'quiz':        return 'Quiz';
      case 'infographic': return 'Infographic';
      default:            return kind || '';
    }
  }

  /* ---------- rendering ---------- */
  function renderStats(items) {
    const counts = { article: 0, video: 0, quiz: 0, infographic: 0 };
    items.forEach(i => { if (counts[i.kind] != null) counts[i.kind]++; });
    const a = document.getElementById('learn-stat-articles');
    const v = document.getElementById('learn-stat-videos');
    const q = document.getElementById('learn-stat-quizzes');
    if (a) a.textContent = counts.article;
    if (v) v.textContent = counts.video;
    if (q) q.textContent = counts.quiz;
  }

  function renderCategories(cats) {
    const wrap = document.getElementById('learn-cats');
    if (!wrap) return;
    const html = ['<button class="lf-cat active" data-cat="">Every category</button>']
      .concat((cats || []).map(c =>
        `<button class="lf-cat" data-cat="${esc(c.category)}">${esc(c.category)} <em>(${c.c})</em></button>`
      )).join('');
    wrap.innerHTML = html;
    wrap.querySelectorAll('.lf-cat').forEach(b => {
      b.addEventListener('click', () => {
        wrap.querySelectorAll('.lf-cat').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        activeCat = b.dataset.cat || '';
        applyFilters();
      });
    });
  }

  function renderGrid(items) {
    const grid = document.getElementById('learn-grid');
    if (!grid) return;
    if (!items.length) {
      grid.innerHTML = '<div class="empty">No resources match your filters.</div>';
      return;
    }
    grid.innerHTML = items.map(it => {
      const meta = [];
      if (it.duration_min) meta.push(`${it.duration_min} min`);
      if (it.reading_min)  meta.push(`${it.reading_min} min read`);
      if (it.level)        meta.push(esc(it.level));

      return `
        <article class="learn-card lc-${esc(it.kind)}" data-id="${it.id}" tabindex="0" role="button" aria-label="Open ${esc(it.title)}">
          <div class="lc-thumb">
            <span class="lc-kind">${kindIcon(it.kind)} ${kindLabel(it.kind)}</span>
            ${it.thumbnail
              ? `<img loading="lazy" src="${esc(it.thumbnail)}" alt="">`
              : `<div class="lc-thumb-fallback"><span class="lc-fallback-icon">${kindIcon(it.kind)}</span></div>`}
          </div>
          <div class="lc-body">
            <span class="lc-cat">${esc(it.category)}</span>
            <h3 class="lc-title">${esc(it.title)}</h3>
            <p class="lc-summary">${esc(it.summary || '')}</p>
            <div class="lc-meta">
              ${meta.map(m => `<span>${m}</span>`).join('')}
              <span class="lc-views" title="Views">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                ${it.views || 0}
              </span>
            </div>
          </div>
        </article>
      `;
    }).join('');

    grid.querySelectorAll('.learn-card').forEach(el => {
      const open = () => openResource(Number(el.dataset.id));
      el.addEventListener('click', open);
      el.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); });
    });
  }

  function applyFilters() {
    const q = (searchQ || '').toLowerCase();
    const items = allItems.filter(it => {
      if (activeKind && it.kind !== activeKind) return false;
      if (activeCat  && it.category !== activeCat) return false;
      if (q) {
        const blob = (it.title + ' ' + (it.summary || '') + ' ' + it.category).toLowerCase();
        if (!blob.includes(q)) return false;
      }
      return true;
    });
    renderGrid(items);
  }

  /* ---------- detail modal ---------- */
  async function openResource(id) {
    const overlay = document.getElementById('learn-modal-overlay');
    const titleEl = document.getElementById('learn-modal-title');
    const bodyEl  = document.getElementById('learn-modal-body');
    if (!overlay || !titleEl || !bodyEl) return;

    titleEl.textContent = 'Loading…';
    bodyEl.innerHTML = '<div class="empty">Loading…</div>';
    overlay.hidden = false;

    try {
      const r = await fetch(`${API}?id=${id}`, { credentials: 'same-origin' });
      const d = await r.json();
      if (!d.ok || !d.resource) {
        bodyEl.innerHTML = '<div class="empty">Resource not available.</div>';
        return;
      }
      const it = d.resource;
      titleEl.textContent = it.title;

      // Best-effort view counter (non-blocking)
      fetch(API, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'view', id }),
      }).catch(() => {});

      if (it.kind === 'video' && it.media_url) {
        bodyEl.innerHTML = `
          <div class="learn-video">
            <iframe src="${esc(it.media_url)}" allow="autoplay; encrypted-media; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
          </div>
          <p class="muted small">${esc(it.summary || '')}</p>`;
        return;
      }
      if (it.kind === 'quiz') {
        renderQuiz(bodyEl, it);
        return;
      }
      if (it.kind === 'infographic' && it.media_url) {
        bodyEl.innerHTML = `
          <img class="learn-infographic" src="${esc(it.media_url)}" alt="${esc(it.title)}" loading="lazy">
          <p class="muted small">${esc(it.summary || '')}</p>`;
        return;
      }
      // Default: article
      bodyEl.innerHTML = `<div class="learn-article">${mdToHtml(it.body || it.summary || '')}</div>`;
    } catch (_) {
      bodyEl.innerHTML = '<div class="empty">Network error.</div>';
    }
  }

  function renderQuiz(host, resource) {
    let questions = [];
    try { questions = JSON.parse(resource.body || '[]'); } catch (_) {}
    if (!Array.isArray(questions) || !questions.length) {
      host.innerHTML = '<div class="empty">Quiz unavailable.</div>';
      return;
    }
    host.innerHTML = `
      <p class="muted small">${esc(resource.summary || '')}</p>
      <form class="learn-quiz" id="learn-quiz-form" novalidate>
        ${questions.map((q, i) => `
          <fieldset class="lq-q">
            <legend>${i + 1}. ${esc(q.q)}</legend>
            ${(q.options || []).map((o, j) => `
              <label class="lq-option">
                <input type="radio" name="q${i}" value="${j}">
                <span>${esc(o)}</span>
              </label>
            `).join('')}
          </fieldset>
        `).join('')}
        <div class="lq-actions">
          <button type="submit" class="btn primary">Check answers</button>
        </div>
        <div id="lq-result" class="lq-result" hidden></div>
      </form>`;

    host.querySelector('#learn-quiz-form').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const answers = questions.map((_, i) => {
        const v = host.querySelector(`input[name="q${i}"]:checked`);
        return v ? Number(v.value) : -1;
      });
      try {
        const r = await fetch(API, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'quiz', id: resource.id, answers }),
        });
        const d = await r.json();
        if (!d.ok) return;

        // Inline correctness highlighting
        d.details.forEach((det, i) => {
          const fs = host.querySelectorAll('.lq-q')[i];
          if (!fs) return;
          fs.classList.add(det.correct ? 'lq-good' : 'lq-bad');
          fs.querySelectorAll('.lq-option').forEach((opt, j) => {
            if (j === det.expected) opt.classList.add('lq-correct');
            if (j === det.got && !det.correct) opt.classList.add('lq-wrong');
          });
        });

        const res = host.querySelector('#lq-result');
        res.hidden = false;
        res.innerHTML = `
          <div class="lq-score">
            <strong>${d.score}/${d.total}</strong>
            <span class="muted">(${d.percent}%)</span>
          </div>
          <p class="muted small">${
            d.percent === 100 ? 'Perfect — knowledge unlocked.' :
            d.percent >= 60   ? 'Good. Review the items you missed.' :
                               'Worth re-reading the related article.'
          }</p>`;
      } catch (_) {}
    });
  }

  function bindFilters() {
    document.querySelectorAll('#learn-kind .lf-pill').forEach(b => {
      b.addEventListener('click', () => {
        document.querySelectorAll('#learn-kind .lf-pill').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        activeKind = b.dataset.kind || '';
        applyFilters();
      });
    });
    const q = document.getElementById('learn-q');
    if (q) q.addEventListener('input', () => { searchQ = q.value.trim(); applyFilters(); });

    const overlay = document.getElementById('learn-modal-overlay');
    if (overlay) {
      const close = () => {
        overlay.hidden = true;
        const body = document.getElementById('learn-modal-body');
        if (body) body.innerHTML = '';
      };
      overlay.querySelector('.learn-modal-close').addEventListener('click', close);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
      document.addEventListener('keydown', (e) => {
        if (!overlay.hidden && e.key === 'Escape') close();
      }, { once: false });
    }
  }

  /* ---------- admin: add new resource ---------- */
  async function setupAdminTools() {
    // Show admin tools only to role=admin (server-side enforcement still in learn.php).
    let me = null;
    try {
      const r = await fetch('../backend/api/auth.php?action=me', { credentials: 'same-origin' });
      const d = await r.json();
      me = d && d.user;
    } catch (_) {}
    if (!me || me.role !== 'admin') return;

    const tools   = document.getElementById('learn-admin-tools');
    const overlay = document.getElementById('learn-admin-overlay');
    const openBtn = document.getElementById('learn-admin-add');
    const closeBtn= overlay && overlay.querySelector('.learn-admin-close');
    const cancel  = document.getElementById('learn-admin-cancel');
    const form    = document.getElementById('learn-admin-form');
    const status  = document.getElementById('learn-admin-status');
    if (!tools || !overlay || !openBtn || !form) return;

    tools.hidden = false;
    const open  = () => { overlay.hidden = false; status.textContent = ''; };
    const close = () => { overlay.hidden = true;  };

    openBtn.addEventListener('click', open);
    closeBtn && closeBtn.addEventListener('click', close);
    cancel   && cancel.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const submit = document.getElementById('learn-admin-submit');
      const fd = new FormData(form);
      const payload = { action: 'create' };
      for (const [k, v] of fd.entries()) payload[k] = v;
      if (!payload.title || !payload.category) {
        status.textContent = 'Title and category are required.';
        status.className = 'laf-status err';
        return;
      }
      submit.disabled = true;
      status.className = 'laf-status';
      status.textContent = 'Publishing…';
      try {
        const r = await fetch(API, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const d = await r.json();
        if (!d.ok) {
          status.className = 'laf-status err';
          status.textContent = d.error || 'Publish failed.';
          submit.disabled = false;
          return;
        }
        status.className = 'laf-status ok';
        status.textContent = 'Published. Reloading list…';
        // Refresh the grid with the new resource.
        const lr = await fetch(`${API}?list=1`, { credentials: 'same-origin' });
        const ld = await lr.json();
        if (ld.ok) {
          allItems = ld.items || [];
          renderStats(allItems);
          renderCategories(ld.categories || []);
          applyFilters();
        }
        setTimeout(() => {
          form.reset();
          submit.disabled = false;
          close();
        }, 600);
      } catch (e) {
        status.className = 'laf-status err';
        status.textContent = 'Network error.';
        submit.disabled = false;
      }
    });
  }

  /* ---------- entry point ---------- */
  window.initLearn = async function () {
    bindFilters();
    setupAdminTools();
    try {
      const r = await fetch(`${API}?list=1`, { credentials: 'same-origin' });
      const d = await r.json();
      if (!d.ok) {
        document.getElementById('learn-grid').innerHTML =
          '<div class="empty">Could not load resources.</div>';
        return;
      }
      allItems = d.items || [];
      renderStats(allItems);
      renderCategories(d.categories || []);
      renderGrid(allItems);
    } catch (_) {
      document.getElementById('learn-grid').innerHTML =
        '<div class="empty">Network error — check your connection.</div>';
    }
  };
})();
