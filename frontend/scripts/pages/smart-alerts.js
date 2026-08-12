/** Smart Alert Engine (PART 17). Data: /backend/api/smart-alerts.php */
window.initSmartAlerts = async function () {
  const API = '../backend/api';
  const $ = (s) => document.querySelector(s);
  const cls = { CRITICAL: 'critical', WARNING: 'warning', INFO: 'info' };
  const color = { CRITICAL: 'var(--danger)', WARNING: 'var(--warning)', INFO: 'var(--primary)' };

  async function load() {
    let d; try { const r = await fetch(`${API}/smart-alerts.php`, { credentials: 'same-origin' }); if (!r.ok) throw new Error('HTTP ' + r.status); d = await r.json(); }
    catch (e) { $('#sa-list').innerHTML = `<div class="card"><div class="muted">Erreur : ${e.message}</div></div>`; return; }
    $('#sa-demo-badge').style.display = d.demo ? '' : 'none';
    $('#sa-c').textContent = d.counts.CRITICAL; $('#sa-w').textContent = d.counts.WARNING; $('#sa-i').textContent = d.counts.INFO;
    if (!d.alerts.length) { $('#sa-list').innerHTML = '<div class="card"><div class="muted">Aucune alerte active.</div></div>'; return; }
    $('#sa-list').innerHTML = d.alerts.map(a => `
      <div class="card" style="margin-bottom:14px;border-left:4px solid ${color[a.level]}">
        <div class="row between">
          <div><span class="pill ${cls[a.level]}">${a.level}</span> <b style="margin-left:8px">${a.city}</b> ${a.anomaly ? '<span class="pill danger">🚨 anomalie</span>' : ''}</div>
          <div class="muted small">${(GT.fmt ? GT.fmt.date(a.triggered_at) : a.triggered_at)}</div>
        </div>
        <div style="margin-top:8px;font-size:24px;font-weight:700;color:${color[a.level]}">AQI ${a.predicted_aqi} <span style="font-size:13px;color:var(--muted)">${a.horizon} · confiance ${Math.round(a.confidence * 100)}%</span></div>
        <div class="muted small" style="margin-top:6px">${a.explanation}</div>
        <div style="margin-top:8px">${a.shap_top.map(f => `<span class="sci-chip" style="background:var(--primary-3);color:var(--primary);border:none">SHAP: ${f}</span>`).join(' ')} ${a.lime_top.map(f => `<span class="sci-chip" style="background:#dcfce7;color:#14532d;border:none">LIME: ${f}</span>`).join(' ')}</div>
        <div style="margin-top:10px;padding:8px 12px;background:var(--bg-soft);border-radius:8px"><b>Recommandation :</b> ${a.recommendation}</div>
      </div>`).join('');
  }
  const b = document.getElementById('sa-refresh'); if (b) b.addEventListener('click', load);
  await load();
};
