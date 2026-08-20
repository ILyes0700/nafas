/** BiLSTM + Autoencoder (v4.0). Donnees : /backend/api/deep-learning.php
 *
 *  Aucun endpoint dedie n'est necessaire : deep-learning.php renvoie les lignes
 *  réelles de train, validation et test. On filtre côté client sur BiLSTM+AE.
 */
window.initBilstmAe = async function () {
  const API = '../backend/api';
  let chart = null;
  const $ = (s) => document.querySelector(s);
  const nz = (v) => (v === null || v === undefined || v === '') ? '—' : v;
  const r2 = (v) => (v === null || v === undefined) ? '—' : Number(v).toFixed(3);

  const HORIZONS = ['1h', '6h', '24h'];
  const TARGET = 'BiLSTM+AE';
  const chartEmpty = (title, message) => {
    const canvas = $('#bae-chart');
    if (!canvas || !canvas.parentElement) return;
    const wrap = canvas.parentElement;
    let note = wrap.querySelector('[data-empty-for="bae-chart"]');
    if (!note) {
      note = document.createElement('div');
      note.className = 'chart-empty';
      note.dataset.emptyFor = 'bae-chart';
      wrap.appendChild(note);
    }
    note.innerHTML = `<strong>${title}</strong>${message}`;
    note.hidden = false;
    canvas.style.display = 'none';
  };
  const chartShow = () => {
    const canvas = $('#bae-chart');
    if (!canvas || !canvas.parentElement) return false;
    canvas.style.display = '';
    const note = canvas.parentElement.querySelector('[data-empty-for="bae-chart"]');
    if (note) note.hidden = true;
    return true;
  };

  async function load() {
    const tbody = $('#bae-table').querySelector('tbody');
    let payload;
    try {
      const r = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      payload = await r.json();
    } catch (e) {
      tbody.innerHTML =
        `<tr><td colspan="8" class="muted">Erreur réelle du backend : ${e.message}</td></tr>`;
      return;
    }

    // Le backend renvoie uniquement des lignes issues de la base :
    // validation/test, modèle, zone et horizon. Toutes les lignes sont réelles.
    const rows = (payload && Array.isArray(payload.models) ? payload.models : [])
      .filter((m) => m.model_name === TARGET && HORIZONS.includes(m.horizon));

    if (!rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="8"><div class="cmp-empty-card"><div><strong>Aucun résultat réel BiLSTM+AE</strong>'
        + 'Lancez python -m models.train_all pour entraîner le modèle.</div></div></td></tr>';
      return;
    }

    const order = { '1h': 0, '6h': 1, '24h': 2 };
    rows.sort((a, b) => order[a.horizon] - order[b.horizon] || String(a.split).localeCompare(String(b.split)));

    tbody.innerHTML = rows.map((m) => {
      return `<tr>`
        + `<td><b>${m.model_name}</b></td>`
        + `<td>${m.horizon}</td>`
        + `<td>${nz(m.split)}</td>`
        + `<td>${nz(m.mae)}</td>`
        + `<td>${nz(m.rmse)}</td>`
        + `<td>${nz(m.mape)}</td>`
        + `<td>${r2(m.r2)}</td>`
        + `<td class="muted small">${nz(m.latency)} ms</td>`
        + '</tr>';
    }).join('');

    // Graphe : une barre par horizon, uniquement sur le TEST réel du modèle ciblé.
    const testRows = rows.filter((m) => m.split === 'test');
    if (chart) { try { chart.destroy(); } catch (e) {} }
    if (typeof Chart !== 'undefined' && testRows.length && chartShow()) {
      chart = new Chart($('#bae-chart').getContext('2d'), {
        type: 'bar',
        data: {
          labels: testRows.map((m) => `+${m.horizon.replace('h', ' h')}`),
          datasets: [{
            label: 'RMSE TEST réel',
            data: testRows.map((m) => Number(m.rmse)),
            backgroundColor: ['#0d3b66', '#2f6fb3', '#16a34a'],
            borderRadius: 8,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, title: { display: true, text: 'BiLSTM+AE — RMSE TEST réel par horizon' } },
          scales: { y: { beginAtZero: true, title: { display: true, text: 'RMSE' } } }
        }
      });
    } else {
      chartEmpty('Graphique indisponible', 'Aucune métrique TEST réelle BiLSTM+AE n’est disponible.');
    }
  }

  const b = document.getElementById('bae-refresh');
  if (b) b.addEventListener('click', load);
  await load();
};