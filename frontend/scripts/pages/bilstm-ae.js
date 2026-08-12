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

  async function fetchHorizon(h) {
    const r = await fetch(`${API}/deep-learning.php?horizon=${h}`, {
      credentials: 'same-origin'
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }

  async function load() {
    const tbody = $('#bae-table').querySelector('tbody');
    let payloads;
    try {
      payloads = await Promise.all(HORIZONS.map(fetchHorizon));
    } catch (e) {
      tbody.innerHTML =
        `<tr><td colspan="7" class="muted">Erreur : ${e.message}</td></tr>`;
      return;
    }

    const demo = payloads.some((p) => p && p.demo);
    $('#bae-demo-badge').style.display = demo ? '' : 'none';
    if (window.GT && GT.notTrainedGuard && GT.notTrainedGuard(demo)) return;

    // Aplatit {horizon -> models[]} en une seule liste.
    const rows = [];
    payloads.forEach((p, i) => {
      const h = HORIZONS[i];
      (p && p.models ? p.models : []).forEach((m) => {
        rows.push({ horizon: h, ...m });
      });
    });

    if (!rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="muted">Aucun modele recurrent en base. '
        + 'Lance python -m models.train_all pour les entrainer.</td></tr>';
      return;
    }

    // Notre modele d'abord, puis les autres, chacun trie par horizon.
    const order = { '1h': 0, '6h': 1, '24h': 2 };
    rows.sort((a, b) => {
      const pa = a.model_name === TARGET ? 0 : 1;
      const pb = b.model_name === TARGET ? 0 : 1;
      if (pa !== pb) return pa - pb;
      if (a.model_name !== b.model_name) {
        return a.model_name.localeCompare(b.model_name);
      }
      return order[a.horizon] - order[b.horizon];
    });

    tbody.innerHTML = rows.map((m) => {
      const best = m.model_name === TARGET;
      return `<tr class="${best ? 'sci-row-best' : ''}">`
        + `<td><b>${m.model_name}</b></td>`
        + `<td>${m.horizon}</td>`
        + `<td class="${best ? 'cell-best' : ''}">${nz(m.mae)}</td>`
        + `<td class="${best ? 'cell-best' : ''}">${nz(m.rmse)}</td>`
        + `<td>${nz(m.mape)}</td>`
        + `<td>${r2(m.r2)}</td>`
        + `<td class="muted small">${nz(m.latency)} ms</td>`
        + '</tr>';
    }).join('');

    // Graphe : RMSE a 1 h, un barreau par modele recurrent.
    const h1 = rows.filter((m) => m.horizon === '1h');
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