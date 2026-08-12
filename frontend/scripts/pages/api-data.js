/* PART 0 — Page "Données de pollution temps réel" (fusion multi-API). */
 
let _apidCharts = {};
let _apidTimer = null;
 
function apidColorFor(aqi) {
  if (aqi <= 50)  return '#00E400';
  if (aqi <= 100) return '#FFFF00';
  if (aqi <= 150) return '#FF7E00';
  if (aqi <= 200) return '#FF0000';
  if (aqi <= 300) return '#99004C';
  return '#7E0023';
}
const apidNum = (v, u = '') => (v === null || v === undefined || v === '') ? '—' : `${(+v).toFixed ? (+v % 1 ? (+v).toFixed(1) : +v) : v}${u}`;
 
async function initApiData() {
  if (_apidTimer) { clearInterval(_apidTimer); _apidTimer = null; }
 
  const sel = document.getElementById('apid-city');
  const btn = document.getElementById('apid-refresh');
 
  // Charge la liste des villes pour le dropdown.
  let all;
  try { all = await GT.api.get('api-data.php'); }
  catch (e) { document.getElementById('apid-fused-wrap').innerHTML = '<div class="card">Erreur de chargement des données API.</div>'; return; }
 
  sel.innerHTML = all.cities.map(c => `<option value="${c.city_id}">${c.name_fr} — ${c.name_ar}</option>`).join('');
 
  async function loadCity(id) {
    let d;
    try { d = await GT.api.get('api-data.php', { city_id: id }); }
    catch (e) { return; }
    renderCity(d);
  }
 
  function renderCity(d) {
    const c = d.city;
    const who = d.who_limits;
    const color = c.category_color || apidColorFor(c.final_aqi);
    document.getElementById('apid-meta').textContent =
      `Méthode: ${c.fusion_method} · Qualité données: ${Math.round((c.data_quality_score || 0) * 100)}% · Mis à jour: ${GT.fmt.date(c.timestamp)}`;
 
    // ---- Bloc fusionné ----
    const sa = c.sources_available;
    document.getElementById('apid-fused-wrap').innerHTML = `
      <div class="apid-fused" style="background:linear-gradient(135deg,${color},${color}cc)">
        <div>
          <div class="apid-aqi-big">${Math.round(c.final_aqi)}</div>
          <div style="font-size:12px;opacity:.9">AQI fusionné</div>
        </div>
        <div>
          <div class="apid-badge">${c.final_category}</div>
          <div class="apid-src-ind">
            <span class="chip">AccuWeather: ${apidNum(c.sources.accuweather.aqi)} (75%) <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;color:#d97706" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01z"/></svg></span>
            <span class="chip">WAQI: ${apidNum(c.sources.waqi.aqi)} (15%)</span>
            <span class="chip">IQAir: ${apidNum(c.sources.iqair.aqi)} (10%)</span>
          </div>
        </div>
        <div style="margin-left:auto;text-align:right">
          <div style="font-size:12px;opacity:.9">Qualité des données</div>
          <div style="font-size:26px;font-weight:800">${Math.round((c.data_quality_score || 0) * 100)}%</div>
        </div>
      </div>`;
 
    // ---- 3 colonnes ----
    const a = c.sources.accuweather, iq = c.sources.iqair, wq = c.sources.waqi;
    const row = (k, v) => `<div class="apid-row"><span>${k}</span><b>${v}</b></div>`;
    const dot = ok => ok ? '<span class="apid-ok">●</span>' : '<span class="apid-bad">○</span>';
 
    document.getElementById('apid-cols').innerHTML = `
      <div class="apid-col primary">
        <h3><span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>AccuWeather <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;color:#d97706" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01z"/></svg></span><span>75%</span></h3>
        <div class="body">
          ${row('AQI', apidNum(a.aqi))}
          ${row('Catégorie', a.category || '—')}
          <div class="apid-sec">Polluants (μg/m³)</div>
          ${row('PM2.5', apidNum(a.pm25))}${row('PM10', apidNum(a.pm10))}
          ${row('NO₂', apidNum(a.no2))}${row('SO₂', apidNum(a.so2))}
          ${row('O₃', apidNum(a.o3))}${row('CO', apidNum(a.co))}
          <div class="apid-sec">Météo</div>
          ${row('Température', apidNum(a.temp, '°C'))}${row('Ressenti', apidNum(a.feels_like, '°C'))}
          ${row('Humidité', apidNum(a.humidity, '%'))}${row('Vent', apidNum(a.wind_speed, ' km/h'))}
          ${row('Pression', apidNum(a.pressure, ' mb'))}${row('Visibilité', apidNum(a.visibility, ' km'))}
          ${row('Index UV', apidNum(a.uv_index))}${row('Couverture', apidNum(a.cloud_cover, '%'))}
          ${row('Point de rosée', apidNum(a.dew_point, '°C'))}${row('Météo', a.weather_text || '—')}
          <div class="apid-sec">Prévisions 12h (AQI)</div>
          ${row('+1h', apidNum(c.forecast['1h']))}${row('+3h', apidNum(c.forecast['3h']))}
          ${row('+6h', apidNum(c.forecast['6h']))}${row('+12h', apidNum(c.forecast['12h']))}
          <div class="apid-row"><span>Disponible</span><b>${dot(sa.accuweather)}</b></div>
        </div>
      </div>
      <div class="apid-col iqair">
        <h3><span>IQAir</span><span>10%</span></h3>
        <div class="body">
          ${row('AQI (US)', apidNum(iq.aqi))}${row('AQI (CN)', apidNum(iq.aqi_cn))}
          ${row('Polluant principal', iq.main_pollutant || '—')}
          <div class="apid-sec">Mesures</div>
          ${row('PM2.5', apidNum(iq.pm25))}${row('PM10', apidNum(iq.pm10))}
          ${row('Température', apidNum(iq.temp, '°C'))}${row('Humidité', apidNum(iq.humidity, '%'))}
          ${row('Vent', apidNum(iq.wind_speed, ' km/h'))}${row('Pression', apidNum(iq.pressure, ' hPa'))}
          <div class="apid-row"><span>Disponible</span><b>${dot(sa.iqair)}</b></div>
        </div>
      </div>
      <div class="apid-col waqi">
        <h3><span>WAQI</span><span>15%</span></h3>
        <div class="body">
          ${row('AQI', apidNum(wq.aqi))}
          <div class="apid-sec">Polluants</div>
          ${row('PM2.5', apidNum(wq.pm25))}${row('PM10', apidNum(wq.pm10))}
          ${row('NO₂', apidNum(wq.no2))}${row('SO₂', apidNum(wq.so2))}
          ${row('O₃', apidNum(wq.o3))}${row('CO', apidNum(wq.co))}
          <div class="apid-sec">Météo</div>
          ${row('Température', apidNum(wq.temp, '°C'))}${row('Humidité', apidNum(wq.humidity, '%'))}
          ${row('Vent', apidNum(wq.wind_speed, ' km/h'))}
          <div class="apid-row"><span>Disponible</span><b>${dot(sa.waqi)}</b></div>
        </div>
      </div>`;
 
    renderCharts(c, who, d.history);
  }
 
  function destroyChart(k) { if (_apidCharts[k]) { _apidCharts[k].destroy(); delete _apidCharts[k]; } }
 
  function renderCharts(c, who, history) {
    if (typeof Chart === 'undefined') return;
    const a = c.sources.accuweather;
 
    // Chart 1 — AQI 3 API + ligne OMS 100.
    destroyChart('aqi');
    _apidCharts.aqi = new Chart(document.getElementById('apid-chart-aqi'), {
      type: 'bar',
      data: {
        labels: ['AccuWeather', 'IQAir', 'WAQI', 'Fusionné'],
        datasets: [{
          label: 'AQI',
          data: [a.aqi, c.sources.iqair.aqi, c.sources.waqi.aqi, c.final_aqi],
          backgroundColor: ['#0d3b66', '#0e7490', '#6d28d9', apidColorFor(c.final_aqi)],
        }],
      },
      options: {
        plugins: {
          legend: { display: false },
          annotation: undefined,
        },
        scales: { y: { beginAtZero: true, suggestedMax: 200, grid: { color: '#eee' } } },
      },
    });
    drawWhoLine('apid-chart-aqi', 'aqi', 100);
 
    // Chart 2 — polluants AccuWeather vs OMS.
    destroyChart('poll');
    const polls = ['pm25', 'pm10', 'no2', 'so2', 'o3', 'co'];
    _apidCharts.poll = new Chart(document.getElementById('apid-chart-poll'), {
      type: 'bar',
      data: {
        labels: ['PM2.5', 'PM10', 'NO₂', 'SO₂', 'O₃', 'CO'],
        datasets: [
          { label: 'Mesuré', data: polls.map(p => a[p] || 0), backgroundColor: '#0d3b66' },
          { label: 'Limite OMS', data: polls.map(p => who[p] || 0), backgroundColor: '#f59e0b', type: 'line', borderColor: '#dc2626', borderDash: [6, 4], pointRadius: 0, fill: false },
        ],
      },
      options: { scales: { y: { beginAtZero: true, grid: { color: '#eee' } } } },
    });
 
    // Chart 3 — prévision 12h (ligne + bande de confiance simulée).
    destroyChart('fc');
    const fcVals = [c.final_aqi, c.forecast['1h'], c.forecast['3h'], c.forecast['6h'], c.forecast['12h']].map(x => x == null ? null : +x);
    _apidCharts.fc = new Chart(document.getElementById('apid-chart-fc'), {
      type: 'line',
      data: {
        labels: ['now', '+1h', '+3h', '+6h', '+12h'],
        datasets: [
          { label: 'Borne haute', data: fcVals.map(v => v == null ? null : Math.round(v * 1.12)), borderColor: 'transparent', backgroundColor: 'rgba(13,59,102,.12)', fill: '+1', pointRadius: 0 },
          { label: 'Borne basse', data: fcVals.map(v => v == null ? null : Math.round(v * 0.88)), borderColor: 'transparent', backgroundColor: 'rgba(13,59,102,.12)', fill: false, pointRadius: 0 },
          { label: 'AQI prévu', data: fcVals, borderColor: '#0d3b66', backgroundColor: '#0d3b66', tension: .3, fill: false },
        ],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#eee' } } } },
    });
 
    // Chart 4 — historique fusionné.
    destroyChart('hist');
    _apidCharts.hist = new Chart(document.getElementById('apid-chart-hist'), {
      type: 'line',
      data: {
        labels: (history || []).map(h => GT.fmt.date(h.t)),
        datasets: [{ label: 'AQI', data: (history || []).map(h => h.aqi), borderColor: apidColorFor(c.final_aqi), backgroundColor: apidColorFor(c.final_aqi) + '33', tension: .3, fill: true }],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#eee' } } } },
    });
  }
 
  // Trace une ligne horizontale OMS sur un bar chart sans plugin externe.
  function drawWhoLine(canvasId, key, value) {
    const chart = _apidCharts[key];
    if (!chart) return;
    const origDraw = chart.draw;
    chart.draw = function () {
      origDraw.apply(this, arguments);
      const yScale = this.scales.y, area = this.chartArea, ctx = this.ctx;
      if (!yScale || !area) return;
      const y = yScale.getPixelForValue(value);
      ctx.save();
      ctx.strokeStyle = '#dc2626'; ctx.lineWidth = 1.5; ctx.setLineDash([6, 4]);
      ctx.beginPath(); ctx.moveTo(area.left, y); ctx.lineTo(area.right, y); ctx.stroke();
      ctx.fillStyle = '#dc2626'; ctx.font = '10px Inter, sans-serif';
      ctx.fillText('OMS 100', area.left + 4, y - 4);
      ctx.restore();
    };
    chart.update();
  }
 
  // Événements.
  sel.addEventListener('change', () => loadCity(sel.value));
  btn.addEventListener('click', async () => {
    btn.disabled = true; btn.textContent = 'Actualisation…';
    try { await GT.api.post('api-data.php', { force: true, city_id: +sel.value }); } catch (e) {}
    await loadCity(sel.value);
    btn.disabled = false; btn.textContent = 'Actualiser';
  });
 
  // Auto-refresh toutes les 30 minutes.
  _apidTimer = setInterval(() => loadCity(sel.value), 30 * 60 * 1000);
 
  await loadCity(sel.value || all.cities[0].city_id);
}