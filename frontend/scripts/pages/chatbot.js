async function initChatbot() {
  const log = document.getElementById('chat-log');
  const input = document.getElementById('chat-input');
  const send = document.getElementById('chat-send');

  push('bot', 'مرحبا 👋 I am نفاس (Nafass). Tell me about your symptoms, or ask me about air quality in a specific zone.');

  async function ask(text) {
    if (!text.trim()) return;
    push('user', text);
    input.value = '';
    push('bot', '…', true);
    try {
      const r = await GT.api.post('chatbot.php', { message: text, user_label: GT.role });
      const fz = r && r.fuzzy;
      const tag = (fz && typeof fz.risk_score !== 'undefined')
        ? `\n\n[Fuzzy Type-2 + IA · score ${Number(fz.risk_score).toFixed(1)}/100 · ${fz.urgency_level}]`
        : '';
      replaceLast(r.response + (r.global_status === 'critical' ? '  (critical situation)' : '') + tag);
    } catch (e) {
      replaceLast('Sorry, I cannot reply right now.');
    }
  }

  send.addEventListener('click', () => ask(input.value));
  input.addEventListener('keydown', e => { if (e.key === 'Enter') ask(input.value); });
  document.querySelectorAll('#chat-suggest button').forEach(b => {
    b.addEventListener('click', () => ask(b.textContent));
  });

  function push(who, text, temp=false) {
    const el = document.createElement('div');
    el.className = 'bubble ' + who;
    el.textContent = text;
    if (temp) el.dataset.temp = '1';
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }
  function replaceLast(text) {
    const last = log.querySelector('[data-temp="1"]');
    if (last) { last.textContent = text; last.removeAttribute('data-temp'); }
    else push('bot', text);
  }
}
