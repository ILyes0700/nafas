/* Nafass — BiLSTM+AE : métriques réelles issues de deep-learning.php. */
window.initBilstmAe = async function () {
  const API = '../backend/api';
  const TARGET = 'BiLSTM+AE';
  const HORIZONS = ['1h', '6h', '24h'];
  const SPLITS = ['train', 'validation', 'test'];
  const ZONES = { '1': 'Gabes_ville', '2': 'Ghannouche', '3': 'Chott_Salem', '4': 'Teboulbou' };
  let chart = null;
  const $ = (s) => document.querySelector(s);
  const esc = (v) => String(v == null ? '—' : v)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const metric = (v, digits = 4) => {
    if (v === null || v === undefined || v === '' || !Number.isFinite(Number(v))) return '—';
    return Number(v).toFixed(digits).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
  };
  const zoneLabel = (row) => row.city || row.zone || ZONES[String(row.city_id)] || `Zone ${row.city_id ?? '—'}`;
  const splitLabel = (split) => ({ train: 'Train (70 %)', validation: 'Validation (10 %)', test: 'Test (20 %)' }[split] || split || '—');
  const splitOrder = (split) => SPLITS.indexOf(split) >= 0 ? SPLITS.indexOf(split) : 99;
  const horizonOrder = (horizon) => HORIZONS.indexOf(horizon) >= 0 ? HORIZONS.indexOf(horizon) : 99;

  const chartEmpty = (title, message) => {
    const canvas = $('#bae-chart');
    if (!canvas || !canvas.parentElement) return;
    const wrap = canvas.parentElement;
    let note = wrap.querySelector('[data-empty-for="bae-chart"]');
    if (!note) { note = document.createElement('div'); note.className = 'chart-empty'; note.dataset.emptyFor = 'bae-chart'; wrap.appendChild(note); }
    note.innerHTML = `<strong>${esc(title)}</strong>${esc(message)}`;
    note.hidden = false; canvas.style.display = 'none';
  };
  const chartShow = () => {
    const canvas = $('#bae-chart');
    if (!canvas || !canvas.parentElement) return false;
    canvas.style.display = '';
    const note = canvas.parentElement.querySelector('[data-empty-for="bae-chart"]');
    if (note) note.hidden = true;
    return true;
  };
  const setSelection = (rows, payload) => {
    const badge = $('#bae-selection-badge');
    if (badge) badge.textContent = rows.length ? 'selection_rule=validation_only · Validation sert au choix ; Test reste un rapport final.' : 'selection_rule=validation_only · aucun résultat réel disponible.';
    const feedback = $('#bae-feedback-status');
    if (feedback) feedback.textContent = payload && payload.attention ? 'Artifact de feedback/attention réel détecté dans la réponse.' : 'Aucun artifact de feedback numérique n’est fourni par deep-learning.php pour cette page ; aucune valeur n’est fabriquée.';
  };

  async function load() {
    const tbody = $('#bae-table')?.querySelector('tbody');
    if (!tbody) return;
    let payload;
    try {
      const response = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      payload = await response.json();
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="cmp-empty-card"><div><strong>Erreur réelle du backend</strong>${esc(e.message)}. Aucune métrique n’a été inventée.</div></div></td></tr>`;
      setSelection([], null);
      chartEmpty('Graphique indisponible', 'Le endpoint réel deep-learning.php n’a pas répondu.');
      return;
    }

    const rows = (Array.isArray(payload.models) ? payload.models : [])
      .filter((m) => m.model_name === TARGET && HORIZONS.includes(m.horizon))
      .sort((a, b) => splitOrder(a.split) - splitOrder(b.split) || horizonOrder(a.horizon) - horizonOrder(b.horizon) || String(zoneLabel(a)).localeCompare(String(zoneLabel(b))));
    setSelection(rows, payload);

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9"><div class="cmp-empty-card"><div><strong>Aucun résultat réel BiLSTM+AE</strong>La base ne contient aucune métrique persistée pour le modèle, les quatre zones et les trois horizons demandés. Lancez réellement l’entraînement.</div></div></td></tr>';
      chartEmpty('Graphique indisponible', 'Aucune métrique TEST réelle BiLSTM+AE n’est disponible.');
      return;
    }

    tbody.innerHTML = rows.map((m) => `<tr>
      <td><b>${esc(zoneLabel(m))}</b><div class="muted small">city_id=${esc(m.city_id)}</div></td>
      <td><b>${esc(m.model_name)}</b></td>
      <td>${esc(m.horizon)}</td>
      <td><span class="split-pill split-${esc(m.split)}">${esc(splitLabel(m.split))}</span></td>
      <td>${metric(m.mae)}</td>
      <td>${metric(m.rmse)}</td>
      <td>${metric(m.mape)}</td>
      <td>${metric(m.r2, 3)}</td>
      <td class="muted small">${metric(m.latency, 2)} ms</td>
    </tr>`).join('');

    const testRows = rows.filter((m) => m.split === 'test');
    if (chart) { try { chart.destroy(); } catch (e) {} chart = null; }
    if (typeof Chart !== 'undefined' && testRows.length && chartShow()) {
      const grouped = HORIZONS.map((horizon) => {
        const group = testRows.filter((row) => row.horizon === horizon);
        if (!group.length) return null;
        const rmse = group.reduce((sum, row) => sum + Number(row.rmse || 0), 0) / group.length;
        return { horizon, rmse, count: group.length };
      }).filter(Boolean);
      if (!grouped.length) { chartEmpty('Graphique indisponible', 'Les lignes TEST réelles ne contiennent aucune RMSE exploitable.'); return; }
      chart = new Chart($('#bae-chart').getContext('2d'), {
        type: 'bar',
        data: { labels: grouped.map((g) => `+${g.horizon.replace('h', ' h')} · n=${g.count}`), datasets: [{ label: 'RMSE TEST réel moyen sur zones observées', data: grouped.map((g) => g.rmse), backgroundColor: ['#0d3b66', '#2f6fb3', '#16a34a'], borderRadius: 8, maxBarThickness: 64 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: 'BiLSTM+AE — RMSE TEST réel moyen par horizon' } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'RMSE AQI' } } } }
      });
    } else {
      chartEmpty('Graphique indisponible', 'Aucune métrique TEST réelle BiLSTM+AE n’est disponible.');
    }
  }

  const refresh = document.getElementById('bae-refresh');
  if (refresh) refresh.addEventListener('click', load);
  await load();
};
