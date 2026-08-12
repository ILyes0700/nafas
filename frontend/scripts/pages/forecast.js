/**
 * Forecast page — admin/health UI for the hybrid ML/DL pollution forecaster.
 *
 * Pulls metrics from /backend/api/forecast-metrics.php and predictions from
 * /backend/api/forecast.php. The "Retrain" button triggers an on-demand
 * recomputation across every zone via ?train=1.
 */
window.initForecast = async function () {
  const tableBody = document.querySelector('#fc-table tbody');
  const predBox   = document.getElementById('fc-predictions');
  const btnTrain  = document.getElementById('fc-train');
  const btnRefresh= document.getElementById('fc-refresh');

  const API = '../backend/api';
  const j = async (url) => {
    const r = await fetch(url, { credentials: 'same-origin' });
    if (!r.ok) throw new Error(`${url} → ${r.status}`);
    return r.json();
  };

  async function loadMetrics () {
    try {
      const r = await j(`${API}/forecast-metrics.php`);
      const sum = (r && r.summary) || [];
      if (!sum.length) {
        tableBody.innerHTML = `<tr><td colspan="8" class="muted">
          No training metrics yet — click "Retrain on all zones" first.
        </td></tr>`;
        return;
      }
      const order = ['xgboost','lstm','ensemble','ar7','mewma','ewma'];
      sum.sort((a,b) => order.indexOf(a.model) - order.indexOf(b.model));
      tableBody.innerHTML = sum.map(m => `
        <tr ${m.model.startsWith('ensemble') ? 'style="background:rgba(40,167,69,0.06);font-weight:600"' : ''}>
          <td>${m.model.toUpperCase()}</td>
          <td>${m.mae}</td><td>${m.rmse}</td><td>${m.mape}</td>
          <td>${m.r2}</td><td>${m.smape}</td>
          <td class="muted small">${m.n_runs}</td>
          <td class="muted small">${m.latest || '—'}</td>
        </tr>`).join('');
    } catch (e) {
      tableBody.innerHTML = `<tr><td colspan="8" class="muted">Error: ${e.message}</td></tr>`;
    }
  }

  async function loadPredictions () {
    try {
      const zones = await j(`${API}/zones.php`);
      const list = (zones && (zones.zones || zones.items)) || [];
      predBox.innerHTML = '';
      for (const z of list) {
        let f = {};
        try {
          const r = await j(`${API}/forecast.php?zone_id=${z.id}`);
          // forecast.php returns { ok, zone, forecast:{ horizons, method, confidence } }
          f = (r && r.forecast) || r || {};
        } catch (e) {
          f = { horizons: [], method: 'error', confidence: 0 };
        }
        const hs = (f && f.horizons) || [];
        const html = `
          <div class="forecast-card">
            <h4>${z.name}</h4>
            <div class="muted small">method: ${f.method || '—'} · confidence: ${Math.round((f.confidence||0)*100)}%</div>
            <div class="forecast-horizons">
              ${hs.map(h => `
                <div class="fh fh-${h.level}">
                  <div class="fh-h">+${h.h}h</div>
                  <div class="fh-v">${h.predicted}</div>
                  <div class="fh-l muted small">${h.level}</div>
                </div>`).join('')}
            </div>
          </div>`;
        predBox.insertAdjacentHTML('beforeend', html);
      }
    } catch (e) {
      predBox.innerHTML = `<div class="muted">Error: ${e.message}</div>`;
    }
  }

  btnTrain.addEventListener('click', async () => {
    btnTrain.disabled = true;
    btnTrain.textContent = 'Training (≈10 s)…';
    try {
      await j(`${API}/forecast-metrics.php?train=1`);
      await loadMetrics();
      await loadPredictions();
    } catch (e) {
      alert('Training failed: ' + e.message);
    } finally {
      btnTrain.disabled = false;
      btnTrain.textContent = 'Retrain on all zones';
    }
  });
  btnRefresh.addEventListener('click', async () => {
    btnRefresh.disabled = true;
    await loadMetrics();
    await loadPredictions();
    btnRefresh.disabled = false;
  });

  await loadMetrics();
  await loadPredictions();
};
