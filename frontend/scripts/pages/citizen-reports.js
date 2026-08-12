async function initCitizenReports() {
  // remplir liste de zones
  const zones = (await GT.api.get('zones.php')).zones;
  const sel = document.getElementById('report-zone');
  sel.innerHTML = zones.map(z => `<option value="${z.id}">${z.name} — ${z.name_ar}</option>`).join('');

  // helpers locaux
  const $ = (id) => document.getElementById(id);
  const escapeHtml = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  // SVG placeholder pour les rapports sans image
  const NO_IMG_SVG = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
      <circle cx="8.5" cy="8.5" r="1.5"/>
      <polyline points="21 15 16 10 5 21"/>
    </svg>`;

  // Category → short label
  const CAT = {
    odor:'odor', smoke:'smoke', breathing:'breathing',
    dust:'dust', noise:'noise', other:'other'
  };

  await refresh();

  // ----- Gestion preview photo -----
  let pickedFile = null;
  const preview     = $('cr-preview');
  const previewImg  = $('cr-preview-img');
  const removeBtn   = $('cr-preview-remove');
  const cameraInput = $('cr-camera');
  const fileInput   = $('cr-file');

  function applyFile(file) {
    if (!file) return;
    if (!file.type || !file.type.startsWith('image/')) {
      $('report-status').innerHTML = '<span class="pill danger">Format not supported</span>';
      return;
    }
    if (file.size > 4 * 1024 * 1024) {
      $('report-status').innerHTML = '<span class="pill danger">Image too large (max 4 MB)</span>';
      return;
    }
    pickedFile = file;
    const url = URL.createObjectURL(file);
    previewImg.src = url;
    preview.classList.add('has-image');
    $('report-status').textContent = '';
  }

  cameraInput.addEventListener('change', (e) => applyFile(e.target.files[0]));
  fileInput.addEventListener('change',   (e) => applyFile(e.target.files[0]));
  removeBtn.addEventListener('click', () => {
    pickedFile = null;
    previewImg.src = '';
    preview.classList.remove('has-image');
    cameraInput.value = '';
    fileInput.value = '';
  });

  // ----- Submit -----
  document.getElementById('form-report').addEventListener('submit', async (e) => {
    e.preventDefault();
    const status = $('report-status');
    status.textContent = 'Sending…';

    try {
      let resp;
      // Si on a une image, on passe en multipart ; sinon on garde le JSON simple.
      if (pickedFile) {
        const fd = new FormData();
        fd.append('citizen_name', e.target.citizen_name.value || 'Anonymous');
        fd.append('zone_id',      e.target.zone_id.value);
        fd.append('category',     e.target.category.value);
        fd.append('description',  e.target.description.value);
        fd.append('image',        pickedFile);
        status.innerHTML = '<span class="pill warning">Sending + AI analysis…</span>';
        resp = await GT.api.postForm('reports.php', fd);
      } else {
        const body = {
          citizen_name: e.target.citizen_name.value || 'Anonymous',
          zone_id:      e.target.zone_id.value,
          category:     e.target.category.value,
          description:  e.target.description.value,
        };
        resp = await GT.api.post('reports.php', body);
      }

      // Affichage post-envoi : si une analyse IA a été produite, on la montre.
      if (resp && resp.ai_analysis) {
        const s   = resp.ai_struct || {};
        const fake = Number(s.fake_score || 0);
        const sev  = Number(s.severity   || 0);
        const intLabel = s.intensity || '';
        const dup  = resp.duplicate_of;

        let chips = '';
        if (s.category)  chips += `<span class="cr-ai-chip">cat: ${escapeHtml(s.category)}</span>`;
        if (intLabel)    chips += `<span class="cr-ai-chip cr-ai-chip-${escapeHtml(intLabel)}">${escapeHtml(intLabel)}</span>`;
        if (sev)         chips += `<span class="cr-ai-chip cr-ai-chip-sev">severity ${sev}/10</span>`;
        if (fake >= 50)  chips += `<span class="cr-ai-chip cr-ai-chip-fake">⚠ suspicious image (${fake}%)</span>`;

        const dupBanner = dup
          ? `<div class="cr-ai-dup">Image already seen (report #${dup.id} · ${escapeHtml(dup.reported_at)}). Possible duplicate.</div>`
          : '';

        status.innerHTML =
          '<span class="pill safe">✓ Report submitted</span>' +
          '<div class="cr-ai cr-ai-flash">' +
            '<div class="cr-ai-head">' +
              '<span class="cr-ai-badge">AI Analysis</span>' +
              '<span class="cr-ai-model">Nafass-Vision · llama-4-scout</span>' +
            '</div>' +
            (chips ? `<div class="cr-ai-chips">${chips}</div>` : '') +
            dupBanner +
            '<div class="cr-ai-body">' + escapeHtml(resp.ai_analysis) + '</div>' +
          '</div>';
      } else if (pickedFile) {
        const why = (resp && resp.ai_error) ? ' — ' + resp.ai_error : '';
        status.innerHTML = '<span class="pill safe">✓ Report submitted</span> ' +
          '<span class="muted">(AI analysis unavailable' + escapeHtml(why) + ')</span>';
      } else {
        status.innerHTML = '<span class="pill safe">✓ Report submitted</span>';
      }
      e.target.reset();
      removeBtn.click();
      await refresh();
    } catch (err) {
      status.innerHTML = '<span class="pill danger">Error</span> ' + err.message;
    }
  });

  document.getElementById('emergency-btn').addEventListener('click', async () => {
    const zone_id = document.getElementById('report-zone').value;
    await GT.api.post('alerts.php', {
      zone_id, title: 'Citizen alert — emergency', message: 'Assistance request triggered by a citizen.',
      severity: 'danger', type: 'emergency'
    });
    document.getElementById('report-status').innerHTML = '<span class="pill danger">Emergency alert sent</span>';
  });

  // ----- C4 — dictée vocale Whisper -----
  const micBtn    = $('cr-mic-btn');
  const micLabel  = $('cr-mic-label');
  const micStatus = $('cr-mic-status');
  const descTa    = $('cr-description');
  let _recState = null;

  if (micBtn && navigator.mediaDevices && window.MediaRecorder) {
    micBtn.addEventListener('click', async () => {
      if (_recState && _recState.recorder) {
        // STOP
        try { _recState.recorder.stop(); } catch (e) { /* ignore */ }
        return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        // Sélection robuste du mime AUDIO (jamais video/webm — non supporté côté serveur)
        const candidates = [
          'audio/webm;codecs=opus',
          'audio/webm',
          'audio/ogg;codecs=opus',
          'audio/ogg',
          'audio/mp4;codecs=mp4a.40.2',
          'audio/mp4',
          'audio/mpeg',
        ];
        const mime = candidates.find(c => MediaRecorder.isTypeSupported(c)) || '';
        const recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
        // Si le navigateur a choisi quand même un container vidéo, on force le type audio à l'envoi
        const fileExt = (mime.includes('mp4') || mime.includes('mpeg')) ? 'm4a'
                      : (mime.includes('ogg')) ? 'ogg' : 'webm';
        const audioMimeForBlob = mime && !mime.startsWith('video/')
          ? mime.split(';')[0]              // strip codecs= partial
          : 'audio/webm';
        const chunks = [];
        recorder.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
        recorder.onstop = async () => {
          stream.getTracks().forEach(t => t.stop());
          micBtn.classList.remove('recording');
          if (micLabel) micLabel.textContent = 'Dictate (EN/FR/AR)';
          if (micStatus) micStatus.textContent = 'Transcribing…';
          // Force toujours un mime AUDIO (corrige bug "video/webm non supporté")
          const blob = new Blob(chunks, { type: audioMimeForBlob });
          const fd = new FormData();
          fd.append('audio', blob, 'speech.' + fileExt);
          fd.append('language', 'auto');
          try {
            const r = await fetch('../backend/api/voice.php', {
              method: 'POST', credentials: 'same-origin', body: fd,
            });
            const data = await r.json();
            if (data.ok && data.text) {
              const sep = (descTa.value && !descTa.value.endsWith(' ')) ? ' ' : '';
              descTa.value = (descTa.value || '') + sep + data.text;
              if (micStatus) micStatus.textContent = `Transcribed (${data.language || 'auto'}).`;
            } else {
              if (micStatus) micStatus.textContent = data.error || 'Transcription failed';
            }
          } catch (err) {
            if (micStatus) micStatus.textContent = 'Network error: ' + err.message;
          }
          setTimeout(() => micStatus && (micStatus.textContent = ''), 3500);
          _recState = null;
        };
        recorder.start();
        _recState = { recorder, chunks };
        micBtn.classList.add('recording');
        if (micLabel) micLabel.textContent = 'Stop';
        if (micStatus) micStatus.textContent = 'Recording… (speak then click Stop)';
      } catch (err) {
        if (micStatus) micStatus.textContent = 'Microphone denied: ' + err.message;
      }
    });
  } else if (micBtn) {
    micBtn.disabled = true;
    micBtn.title = 'Dictation not supported by this browser';
  }

  // ----- Lightbox photos -----
  const lightbox    = $('cr-lightbox');
  const lightboxImg = $('cr-lightbox-img');
  lightbox.addEventListener('click', () => lightbox.classList.remove('open'));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') lightbox.classList.remove('open');
  });

  async function refresh() {
    const data = await GT.api.get('reports.php');
    const list = data.reports.slice(0, 12);
    if (!list.length) {
      document.getElementById('reports-feed').innerHTML =
        '<div class="empty">No report</div>';
      return;
    }

    document.getElementById('reports-feed').innerHTML = `
      <div class="cr-feed">
        ${list.map(r => `
          <div class="cr-card">
            ${r.image_path
              ? `<div class="cr-thumb">
                   <img src="${GT.api.asset(r.image_path)}" alt="Report photo"
                        data-full="${GT.api.asset(r.image_path)}">
                 </div>`
              : `<div class="cr-thumb empty">${NO_IMG_SVG}</div>`}
            <div class="cr-body">
              <div class="cr-row1">
                <div class="cr-author">${escapeHtml(r.citizen_name || 'Anonymous')}</div>
                <span class="pill ${r.status==='validated'?'safe':r.status==='rejected'?'danger':'warning'}">${r.status}</span>
              </div>
              <div class="cr-meta">
                <span>${escapeHtml(CAT[r.category] || r.category)}</span>
                <span>·</span>
                <span>${escapeHtml(r.zone_name || '—')}</span>
                <span>·</span>
                <span>${GT.fmt.date(r.reported_at)}</span>
              </div>
              <div class="cr-desc">${escapeHtml(r.description || '')}</div>
              ${r.ai_analysis ? `
                <details class="cr-ai cr-ai-compact">
                  <summary class="cr-ai-head">
                    <span class="cr-ai-badge">AI Analysis</span>
                    <span class="cr-ai-model">Nafass-Vision</span>
                    <span class="cr-ai-toggle" aria-hidden="true"></span>
                  </summary>
                  <div class="cr-ai-body">${escapeHtml(r.ai_analysis)}</div>
                </details>` : ''}
            </div>
          </div>
        `).join('')}
      </div>`;

    // attache lightbox aux miniatures
    document.querySelectorAll('#reports-feed .cr-thumb img').forEach(img => {
      img.addEventListener('click', () => {
        lightboxImg.src = img.dataset.full;
        lightbox.classList.add('open');
      });
    });
  }
}
