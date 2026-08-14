/** BiLSTM + Autoencoder (v4.0). Donnees : /backend/api/deep-learning.php
 *
 *  Aucun endpoint dedie n'est necessaire : deep-learning.php agrege deja
 *  model_performance avec WHERE model_name LIKE '%LSTM%', ce qui inclut
 *  BiLSTM+AE. On filtre cote client pour mettre notre modele en avant.
 */
window.initBilstmAe = async function () {
  const API = '../backend/api';
  let chart = null;
  const $ = (s) => document.querySelector(s);
  const nz = (v) => (v === null || v === undefined || v === '') ? '—' : v;
  const r2 = (v) => (v === null || v === undefined) ? '—' : Number(v).toFixed(3);

  const HORIZONS = ['1h', '6h', '24h'];
  const TARGET = 'BiLSTM+AE';

  async function load() {
    const tbody = $('#bae-table').querySelector('tbody');
    let payload;
    try {
      const r = await fetch(`${API}/deep-learning.php`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      payload = await r.json();
    } catch (e) {
      tbody.innerHTML =
        `<tr><td colspan="7" class="muted">Erreur réelle du backend : ${e.message}</td></tr>`;
      return;
    }

    // Le backend renvoie uniquement des lignes issues de la base :
    // validation/test, modèle, zone et horizon. Aucun flag Demo n'est utilisé.
    const rows = (payload && Array.isArray(payload.models) ? payload.models : [])
      .filter((m) => m.model_name === TARGET && HORIZONS.includes(m.horizon));

    if (!rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="muted">Aucun résultat réel BiLSTM+AE. '
        + 'Lancez python -m models.train_all pour entraîner le modèle.</td></tr>';
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

    // Graphe : RMSE a 1 h, un barreau par modele recurrent.
    const h1 = rows.filter((m) => m.horizon === '1h' && m.split === 'test');
    if (chart) { try { chart.destroy(); } catch (e) {} }
    if (typeof Chart !== 'undefined' && h1.length) {
      chart = new Chart($('#bae-chart').getContext('2d'), {
        type: 'bar',
        data: {
          labels: h1.map((m) => m.model_name),
          datasets: [{
            label: 'RMSE (horizon 1 h)',
            data: h1.map((m) => m.rmse),
            backgroundColor: h1.map(
              (m) => m.model_name === TARGET ? '#16a34a' : '#0d3b66'
            )
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          plugins: { legend: { display: false } }
        }
      });
    }
  }

  const b = document.getElementById('bae-refresh');
  if (b) b.addEventListener('click', load);
  await load();
};