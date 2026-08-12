async function initMap() {
  // Attendre que Leaflet soit chargé (script defer)
  if (typeof L === 'undefined') {
    await new Promise(res => {
      const id = setInterval(() => { if (typeof L !== 'undefined') { clearInterval(id); res(); } }, 50);
      setTimeout(() => { clearInterval(id); res(); }, 5000);
    });
  }
  if (typeof L === 'undefined') {
    document.getElementById('leaflet-map').innerHTML =
      '<div class="empty">Unable to load Leaflet (check the internet connection for OSM tiles).</div>';
    return;
  }
 
  const data = await GT.api.get('zones.php');
 
  // PART 0 — récupère l'AQI fusionné multi-API par ville (chaque ville = nombre différent).
  let fusedById = {};
  try {
    const fd = await GT.api.get('api-data.php');
    (fd.cities || []).forEach(c => { fusedById[c.city_id] = c; });
  } catch (e) { /* fallback silencieux sur pollution_level */ }
 
  // Centrer sur Gabès, Tunisie
  const map = L.map('leaflet-map', { zoomControl: true }).setView([33.881, 10.098], 12);
 
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18,
  }).addTo(map);
 
  // Couleur catégorie US AQI (cf. moteur de fusion).
  const aqiColor = aqi => aqi <= 50 ? '#00E400' : aqi <= 100 ? '#FFFF00' : aqi <= 150 ? '#FF7E00'
    : aqi <= 200 ? '#FF0000' : aqi <= 300 ? '#99004C' : '#7E0023';
  const aqiTextColor = aqi => aqi <= 100 ? '#1f2937' : '#fff';
  const colorFor = s => s === 'critical' ? '#dc2626' : s === 'warning' ? '#d97706' : '#16a34a';
  const radiusFor = pop => Math.max(400, Math.min(2200, Math.sqrt(+pop || 0) * 8));

  // --- Icônes pro (SVG ligne, cohérentes avec les icônes de la sidebar) ---
  const GT_ICON_PATHS = {
    factory: '<path d="M3 21h18"/><path d="M4 21V10l5 3V10l5 3V6l5 3v12"/><path d="M8 21v-4M13 21v-4"/>',
    school: '<path d="M3 21h18"/><path d="M5 21V9l7-4 7 4v12"/><path d="M9 21v-5h6v5"/><path d="M12 3v2"/>',
    mosque: '<path d="M4 21h16"/><path d="M6 21v-6a6 6 0 0 1 12 0v6"/><path d="M12 3c-1.6 1.8 1.6 3 0 5"/><path d="M4 21v-5M20 21v-5"/>',
    hospital: '<path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M12 10v6M9 13h6"/>',
    shield: '<path d="M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/>',
    signal: '<path d="M4 20V10M9 20V6M14 20v-8M19 20V4"/>',
    flame: '<path d="M12 3c2 3 5 5 5 9a5 5 0 0 1-10 0c0-2 1-3 2-4 .5 2 2 2.5 3 1-1-2 0-4 0-6z"/>',
    wind: '<path d="M3 8h11a3 3 0 1 0-3-3"/><path d="M3 12h15a3 3 0 1 1-3 3"/><path d="M3 16h7"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  };
  const svgIco = (name, color, size, sw) =>
    '<svg viewBox="0 0 24 24" width="' + (size || 22) + '" height="' + (size || 22) + '" fill="none" stroke="' + (color || '#0d3b66') +
    '" stroke-width="' + (sw || 1.8) + '" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;filter:drop-shadow(0 1px 2px rgba(0,0,0,.35))">' +
    (GT_ICON_PATHS[name] || '') + '</svg>';
 
  // Time Machine state — must be declared BEFORE first renderDetail() call
  let selectedZone = null;
  const currentScores = {};
  data.zones.forEach(z => { currentScores[z.id] = Number(z.pollution_level) || 0; });
 
  const markers = [];
  data.zones.forEach(z => {
    if (!z.lat || !z.lng) return;
    
    const fz = fusedById[z.id];                       // données fusionnées multi-API
    const hasAqi = fz && fz.final_aqi != null;
    const aqi = hasAqi ? Math.round(fz.final_aqi) : null;
    // Couleur : catégorie AQI si disponible, sinon statut interne.
    const c = hasAqi ? aqiColor(aqi) : colorFor(z.status);
    const stroke = hasAqi ? aqiColor(aqi) : c;
    const label = hasAqi ? aqi : z.pollution_level;
    const txt = hasAqi ? aqiTextColor(aqi) : '#fff';
    // Rayon marqueur : plus l'AQI est élevé, plus gros (15 + aqi/10 px).
    const mkSize = hasAqi ? Math.round(2 * (15 + aqi / 10)) : 34;
 
    // Cercle de zone (proportionnel à la population)
    const circle = L.circle([+z.lat, +z.lng], {
     
      color: stroke,
      weight: 2,
      fillColor: c,
     
      fillOpacity: 0.22,
      radius: radiusFor(z.population),
    }).addTo(map);
 

    // Marqueur central avec icône colorée (affiche l'AQI fusionné de la ville)
    const icon = L.divIcon({
      className: 'gt-marker',
      html: `<div style="
        background:${c};
        
        color:${txt};
        border-radius:50%;
       
        width:${mkSize}px;height:${mkSize}px;
        display:flex;align-items:center;justify-content:center;
        
        font-weight:800;font-size:${hasAqi ? 13 : 11}px;
        box-shadow:0 2px 8px rgba(0,0,0,.25);
        border:2px solid #fff;
      
      ">${label}</div>`,
      iconSize: [mkSize, mkSize],
      iconAnchor: [mkSize / 2, mkSize / 2],
    });
    const m = L.marker([+z.lat, +z.lng], { icon }).addTo(map);
 
    const fuseBlock = hasAqi ? `
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid #eee">
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Polluants</div>
          <div style="font-size:11px;color:#374151">PM2.5 ${fz.final_pm25 ?? '—'} · PM10 ${fz.final_pm10 ?? '—'} · NO₂ ${fz.final_no2 ?? '—'}</div>
          <div style="font-size:11px;color:#374151">SO₂ ${fz.final_so2 ?? '—'} · O₃ ${fz.final_o3 ?? '—'} · CO ${fz.final_co ?? '—'}</div>
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-top:4px">Météo</div>
          <div style="font-size:11px;color:#374151">${fz.final_temperature ?? '—'}°C · ${fz.final_humidity ?? '—'}% · ${fz.final_wind_speed ?? '—'} km/h</div>
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-top:4px">Sources</div>
          <div style="font-size:11px;color:#374151">AccuWeather ${fz.sources_available.accuweather ? '✅' : '❌'} · IQAir ${fz.sources_available.iqair ? '✅' : '❌'} · WAQI ${fz.sources_available.waqi ? '✅' : '❌'}</div>
          <div style="font-size:10px;color:#9ca3af;margin-top:3px">Mis à jour : ${GT.fmt.date(fz.timestamp)}</div>
        </div>` : '';
    const popupHtml = `
      <div style="font-family:Inter,Segoe UI;line-height:1.4">
        <div style="font-weight:700;color:#0d3b66;font-size:14px">${z.name}</div>
        <div style="color:#6b7280;font-size:11px;margin-bottom:6px">${z.name_ar} · ${z.category}</div>
        
        ${hasAqi ? `<div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:26px;font-weight:800;color:${c === '#FFFF00' ? '#b45309' : c}">${aqi}</span>
            <span style="background:${c};color:${txt};padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700">${fz.final_category}</span>
          </div>`
          : `<div><span style="background:${c};color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600">${z.status}</span>
             <strong style="margin-left:6px">${z.pollution_level}%</strong></div>`}
        <div style="margin-top:4px;font-size:12px">Risk: <strong>${z.risk_score ?? '—'}/100</strong></div>
        <div style="font-size:11px;color:#6b7280">${z.reports_total} reports · ${z.symptoms_total} symptoms</div>
        ${z.population ? `<div style="font-size:11px;color:#6b7280">Population: ${(+z.population).toLocaleString('en-US')}</div>` : ''}
        
        ${fuseBlock}
      </div>`;
    m.bindPopup(popupHtml);
    circle.bindPopup(popupHtml);
    m.on('click', () => renderDetail(z));
    circle.on('click', () => renderDetail(z));
    markers.push({ z, m, circle });
  });
 
  // Auto-fit sur les zones existantes
  if (markers.length) {
    const group = L.featureGroup(markers.map(x => x.m));
    map.fitBounds(group.getBounds().pad(0.15));
  }

  // ===================== PART 0.4 — couches enrichies (additif) =====================
  (function addPart04() {
    if (typeof L === 'undefined' || !map) return;
    const zoneAqi = (z) => {
      const fz = fusedById[z.id];
      return (fz && fz.final_aqi != null) ? Math.round(fz.final_aqi) : (Number(z.pollution_level) || 0);
    };

    // --- Heatmap : cercles colorés semi-transparents (toggle) ---
    const heatLayer = L.layerGroup();
    data.zones.forEach(z => {
      if (!z.lat || !z.lng) return;
      const col = aqiColor(zoneAqi(z));
      L.circle([+z.lat, +z.lng], { radius: 2800, stroke: false, fillColor: col, fillOpacity: 0.25 }).addTo(heatLayer);
      L.circle([+z.lat, +z.lng], { radius: 1600, stroke: false, fillColor: col, fillOpacity: 0.28 }).addTo(heatLayer);
    });

    // --- Marqueurs usines ---
    const factories = [
      { name: 'SIAPE (Ghannouche)', lat: 33.9281, lng: 10.0711 },
      { name: 'GCT Gabès',          lat: 33.8900, lng: 10.0900 },
      { name: 'CPG Zone',            lat: 33.9100, lng: 10.0800 },
    ];
    const factoryLayer = L.layerGroup();
    factories.forEach(f => {
      L.marker([f.lat, f.lng], { icon: L.divIcon({ className: 'gt-factory', html: svgIco('factory', '#374151', 26, 1.9), iconSize: [26, 26], iconAnchor: [13, 26] }) })
        .bindTooltip(f.name, { direction: 'top' })
        .bindPopup('<b>' + f.name + '</b><br>Source industrielle de pollution')
        .addTo(factoryLayer);
    });

    // --- Flèches de vent (direction + vitesse) ---
    const windLayer = L.layerGroup();
    data.zones.forEach(z => {
      if (!z.lat || !z.lng) return;
      const fz = fusedById[z.id];
      const dir = fz && fz.final_wind_direction != null ? +fz.final_wind_direction : 135;
      const spd = fz && fz.final_wind_speed != null ? +fz.final_wind_speed : 12;
      const size = Math.round(18 + Math.min(22, spd));
      const html = '<div title="Vent ' + Math.round(spd) + ' km/h" style="transform:rotate(' + dir + 'deg);font-size:' + size + 'px;line-height:1;color:#0d3b66;font-weight:800">↑</div>';
      L.marker([+z.lat + 0.007, +z.lng + 0.007], { icon: L.divIcon({ className: 'gt-wind', html, iconSize: [size, size], iconAnchor: [size / 2, size / 2] }) }).addTo(windLayer);
    });

    // --- Lignes de propagation (zones industrielles → centre) ---
    const propLayer = L.layerGroup();
    const center = data.zones.find(z => (+z.id) === 1) || data.zones[0];
    if (center && center.lat) {
      data.zones.forEach(z => {
        if (!z.lat || !z.lng || z === center) return;
        const isInd = ((z.category || '').toLowerCase().indexOf('indus') >= 0) || zoneAqi(z) > 150;
        if (!isInd) return;
        L.polyline([[+z.lat, +z.lng], [+center.lat, +center.lng]], { color: '#dc2626', weight: 2, opacity: 0.6, dashArray: '6 6' })
          .bindTooltip('Propagation ' + z.name + ' → ' + center.name, { sticky: true })
          .addTo(propLayer);
      });
    }

    // Contrôle de couches (toggle) — usines visibles par défaut
    const layerControl = L.control.layers(null, {
      [svgIco('flame', '#C4622D', 14, 2) + ' Heatmap']: heatLayer,
      [svgIco('factory', '#374151', 14, 2) + ' Usines']: factoryLayer,
      [svgIco('wind', '#0d3b66', 14, 2) + ' Vent']: windLayer,
      [svgIco('arrow', '#dc2626', 14, 2) + ' Propagation']: propLayer,
    }, { position: 'topright', collapsed: false }).addTo(map);
    factoryLayer.addTo(map);

    // UPGRADE v9 — Part 55 : nouvelles couches (Écoles, Zones sûres, Signalements, Confiance).
    (async function addV9Layers() {
      let lay = null;
      try {
        const base = (window.GT && GT.api && GT.api.base) ? GT.api.base : '../backend/api';
        const r = await fetch(base + '/map-layers.php', { credentials: 'same-origin' });
        lay = await r.json();
      } catch (e) { return; }
      if (!lay || !lay.ok) return;

      // 1) Écoles — visibles par défaut.
      const schoolLayer = L.layerGroup();
      (lay.schools || []).forEach(sc => {
        if (sc.lat == null || sc.lng == null) return;
        const col = sc.current_status === 'suspension' ? '#dc2626' : sc.current_status === 'vigilance' ? '#d97706' : '#0d3b66';
        L.marker([+sc.lat, +sc.lng], { icon: L.divIcon({ className: 'gt-school', html: svgIco('school', col, 24, 1.9), iconSize: [24,24], iconAnchor: [12,12] }) })
          .bindTooltip((sc.name || 'École') + ' · ' + (sc.current_status || 'normal'), { direction: 'top' })
          .addTo(schoolLayer);
      });
      layerControl.addOverlay(schoolLayer, svgIco('school', '#0d3b66', 14, 2) + ' Écoles');
      schoolLayer.addTo(map);

      // 2) Zones sûres — affichées seulement si une zone est en critical.
      const safeLayer = L.layerGroup();
      (lay.safe_points || []).forEach(pt => {
        if (pt.lat == null || pt.lng == null) return;
        const ico = pt.type === 'mosque' ? 'mosque' : pt.type === 'health_center' ? 'hospital' : 'shield';
        L.marker([+pt.lat, +pt.lng], { icon: L.divIcon({ className: 'gt-safe', html: svgIco(ico, '#0f766e', 24, 1.9), iconSize: [24,24], iconAnchor: [12,12] }) })
          .bindTooltip((pt.name || 'Point sûr') + (pt.has_filtration ? ' · filtration' : ''), { direction: 'top' })
          .addTo(safeLayer);
      });
      layerControl.addOverlay(safeLayer, svgIco('shield', '#0f766e', 14, 2) + ' Zones sûres');
      const zsrc = (typeof data !== 'undefined' && data && data.zones) ? data.zones : [];
      if (zsrc.some(z => z.status === 'critical')) safeLayer.addTo(map);

      // 3) Densité des signalements citoyens (24h) — cercles proportionnels.
      const reportsLayer = L.layerGroup();
      (lay.reports || []).forEach(rp => {
        if (rp.lat == null || rp.lng == null || !rp.count) return;
        L.circle([+rp.lat, +rp.lng], { radius: 300 + rp.count * 120, stroke: false, fillColor: '#C4622D', fillOpacity: 0.35 })
          .bindTooltip(rp.count + ' signalement(s) · ' + (rp.name || ''), { direction: 'top' })
          .addTo(reportsLayer);
      });
      layerControl.addOverlay(reportsLayer, svgIco('users', '#C4622D', 14, 2) + ' Signalements');

      // 4) Confiance des données — halo plein (haute) ou pointillé (basse).
      const trustLayer = L.layerGroup();
      (lay.trust || []).forEach(t => {
        if (t.lat == null || t.lng == null) return;
        const high = (t.confidence || 0) >= 0.6;
        L.circle([+t.lat, +t.lng], {
          radius: 1400, color: '#6E8FA3', weight: 2,
          dashArray: high ? null : '4 6',
          fillColor: '#6E8FA3', fillOpacity: high ? 0.18 : 0.05
        }).bindTooltip('Confiance ' + Math.round((t.confidence || 0) * 100) + '% · ' + (t.sources || 0) + ' source(s)', { direction: 'top' })
          .addTo(trustLayer);
      });
      layerControl.addOverlay(trustLayer, svgIco('signal', '#6E8FA3', 14, 2) + ' Confiance données');
    })();

    // --- Légende (bas-droite) ---
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
      const div = L.DomUtil.create('div', 'gt-legend');
      div.style.cssText = 'background:#fff;padding:8px 10px;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.2);font:11px Inter,Segoe UI,sans-serif;line-height:1.6';
      const rows = [['0-50', '#00E400', 'Bon'], ['51-100', '#FFFF00', 'Modéré'], ['101-150', '#FF7E00', 'Mauvais SGS'], ['151-200', '#FF0000', 'Mauvais'], ['201-300', '#99004C', 'Très mauvais'], ['300+', '#7E0023', 'Dangereux']];
      div.innerHTML = '<div style="font-weight:700;margin-bottom:4px">AQI</div>' + rows.map(r => '<div style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;border-radius:2px;background:' + r[1] + ';display:inline-block"></span>' + r[0] + ' · ' + r[2] + '</div>').join('');
      return div;
    };
    legend.addTo(map);

    // --- Tooltip au survol + enrichissement popup (anomalie, santé, boutons) ---
    markers.forEach(({ z, m, circle }) => {
      const fz = fusedById[z.id];
      const aqi = zoneAqi(z);
      m.bindTooltip(z.name + ' · AQI ' + aqi, { direction: 'top', offset: [0, -6] });
      const pm25 = fz ? (+fz.final_pm25 || 0) : 0, so2 = fz ? (+fz.final_so2 || 0) : 0, pm10 = fz ? (+fz.final_pm10 || 0) : 0;
      let hi = ((aqi / 500) * 0.25 + (pm25 / 75) * 0.25 + (so2 / 100) * 0.20 + 0.25 * 0.15 + 0.5 * 0.15) * 100;
      hi = Math.max(0, Math.min(100, Math.round(hi)));
      const hiLvl = hi <= 25 ? 'Négligeable' : hi <= 50 ? 'Faible' : hi <= 75 ? 'Modéré' : hi <= 90 ? 'Élevé' : 'Critique';
      const anomaly = (aqi > 180 || so2 > 200 || pm10 > 400);
      const extra = '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #eee">'
        + '<div style="font-size:11px"><b>Impact sanitaire :</b> ' + hi + '/100 (' + hiLvl + ')</div>'
        + '<div style="font-size:11px"><b>Anomalie :</b> ' + (anomaly ? '🚨 Détectée' : '✅ Normal') + '</div>'
        + '<div style="margin-top:6px;display:flex;gap:6px">'
        + '<a href="#/api-data" style="background:#0d3b66;color:#fff;padding:3px 8px;border-radius:6px;font-size:11px;text-decoration:none">Données API</a>'
        + '<a href="#/forecast" style="background:#eef2f7;color:#0d3b66;padding:3px 8px;border-radius:6px;font-size:11px;text-decoration:none">Prédictions</a>'
        + '</div></div>';
      try {
        const p = m.getPopup(); if (p) m.setPopupContent(p.getContent() + extra);
        const pc = circle.getPopup(); if (pc) circle.setPopupContent(pc.getContent() + extra);
      } catch (e) { /* ignore */ }
    });
  })();
 
  // Sélection initiale
  const first = data.zones.find(z => z.status === 'critical') || data.zones[0];
  if (first) renderDetail(first);
 
  function renderDetail(z) {
    selectedZone = z;
    // Mode fusion (onglet "Now") : panneau détaillé AQI multi-API.
    const fz = fusedById[z.id];
    const inNowMode = (document.querySelector('#map-time-card .mtc-tab.is-active')?.dataset.mode || 'now') === 'now';
    if (fz && fz.final_aqi != null && inNowMode) {
      const aqi = Math.round(fz.final_aqi);
      const col = aqiColor(aqi), txt = aqiTextColor(aqi);
      const numCol = col === '#FFFF00' ? '#b45309' : col;
      const reco = aqi <= 50 ? 'Qualité de l\'air satisfaisante.'
        : aqi <= 100 ? 'Acceptable ; personnes très sensibles : limiter les efforts prolongés.'
        : aqi <= 150 ? 'Groupes sensibles : réduire les efforts extérieurs prolongés.'
        : aqi <= 200 ? 'Tout le monde : limiter les activités extérieures.'
        : aqi <= 300 ? 'Alerte santé : éviter les activités extérieures.'
        : 'Urgence sanitaire : rester à l\'intérieur.';
      document.getElementById('zone-detail').innerHTML = `
        <div style="font-size:18px;font-weight:700;color:var(--primary)">${z.name}</div>
        <div class="muted" style="margin-bottom:12px">${z.name_ar} · ${z.category}</div>
        <div class="row between"><span class="muted">AQI fusionné</span>
          <span style="font-size:26px;font-weight:800;color:${numCol}">${aqi}</span></div>
        <div class="row between" style="margin-top:4px"><span class="muted">Catégorie</span>
          <span style="background:${col};color:${txt};padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700">${fz.final_category}</span></div>
        <div class="row between" style="margin-top:8px"><span class="muted">Qualité données</span><strong>${Math.round((fz.data_quality_score ?? 1) * 100)}%</strong></div>
        <hr>
        <div class="muted" style="margin-bottom:4px;font-weight:600">Polluants</div>
        <div class="row between" style="font-size:12px"><span class="muted">PM2.5 / PM10</span><strong>${fz.final_pm25 ?? '—'} / ${fz.final_pm10 ?? '—'}</strong></div>
        <div class="row between" style="font-size:12px"><span class="muted">NO₂ / SO₂</span><strong>${fz.final_no2 ?? '—'} / ${fz.final_so2 ?? '—'}</strong></div>
        <div class="row between" style="font-size:12px"><span class="muted">O₃ / CO</span><strong>${fz.final_o3 ?? '—'} / ${fz.final_co ?? '—'}</strong></div>
        <div class="muted" style="margin:6px 0 4px;font-weight:600">Météo</div>
        <div class="row between" style="font-size:12px"><span class="muted">Temp / Humidité</span><strong>${fz.final_temperature ?? '—'}°C / ${fz.final_humidity ?? '—'}%</strong></div>
        <div class="row between" style="font-size:12px"><span class="muted">Vent</span><strong>${fz.final_wind_speed ?? '—'} km/h</strong></div>
        <div class="muted" style="margin:6px 0 4px;font-weight:600">Sources</div>
        <div style="font-size:12px">AccuWeather ${fz.sources_available.accuweather ? '✅' : '❌'} · IQAir ${fz.sources_available.iqair ? '✅' : '❌'} · WAQI ${fz.sources_available.waqi ? '✅' : '❌'}</div>
        <hr>
        <div class="muted" style="margin-bottom:6px">Recommandation santé</div>
        <div>${reco}</div>
        <hr>
        <div class="muted" style="font-size:12px">Population : ${(+z.population).toLocaleString('fr-FR')}</div>
        <div class="muted" style="font-size:12px">Coordonnées : ${(+z.lat).toFixed(4)}, ${(+z.lng).toFixed(4)}</div>
        <div class="muted" style="font-size:12px;margin-top:6px">Mis à jour : ${GT.fmt.date(fz.timestamp)}</div>`;
      const found = markers.find(x => x.z.id === z.id);
      if (found) { map.setView([+z.lat, +z.lng], 13, { animate: true }); found.m.openPopup(); }
      return;
    }
    // Use live score (changes with Time Machine) when available, fall back to z.pollution_level
    const live = (typeof currentScores !== 'undefined' && currentScores[z.id] != null)
      ? currentScores[z.id]
      : Number(z.pollution_level) || 0;
    const liveStatus = live >= 70 ? 'critical' : live >= 40 ? 'warning' : 'safe';
    const recoMap = {
      safe:     'Normal activities are possible.',
      warning:  'Limit intense outdoor effort. Stay hydrated.',
      critical: 'Stay indoors. Sensitive people: consult a doctor.',
    };
    const statusLabel = { safe: 'Safe', warning: 'Watch', critical: 'Critical' }[liveStatus] || liveStatus;
    document.getElementById('zone-detail').innerHTML = `
      <div style="font-size:18px;font-weight:700;color:var(--primary)">${z.name}</div>
      <div class="muted" style="margin-bottom:12px">${z.name_ar} · ${z.category}</div>
      <div class="row between"><span class="muted">Status</span> <span class="pill ${liveStatus}">${statusLabel}</span></div>
      <div class="row between" style="margin-top:8px"><span class="muted">Pollution</span> <strong>${live}%</strong></div>
      <div class="bar ${liveStatus}" style="margin-top:6px"><span style="width:${live}%"></span></div>
      <div class="row between" style="margin-top:10px"><span class="muted">Risk Score</span><strong>${z.risk_score ?? '—'}/100</strong></div>
      <div class="row between" style="margin-top:6px"><span class="muted">Reports</span><strong>${z.reports_total}</strong></div>
      <div class="row between" style="margin-top:6px"><span class="muted">Symptoms</span><strong>${z.symptoms_total}</strong></div>
      <hr>
      <div class="muted" style="margin-bottom:6px">Health recommendation</div>
      <div>${recoMap[liveStatus]}</div>
      <hr>
      <div class="muted" style="font-size:12px">Population: ${(+z.population).toLocaleString('en-US')}</div>
      <div class="muted" style="font-size:12px">Coordinates: ${(+z.lat).toFixed(4)}, ${(+z.lng).toFixed(4)}</div>
      <div class="muted" style="font-size:12px;margin-top:6px">${z.description || ''}</div>
    `;
 
    const found = markers.find(x => x.z.id === z.id);
    if (found) {
      map.setView([+z.lat, +z.lng], 13, { animate: true });
      found.m.openPopup();
    }
  }
 
  /* ---------- Time Machine: Now / History / AI Forecast (redesigned) ---------- */
  let timelapseData = null;
  let forecastData  = null;
  let playTimer     = null;
  // selectedZone + currentScores already declared at top of initMap (needed before first renderDetail)
 
 
  function buildMarkerHtml(score, color, size, txt) {
    size = size || 34; txt = txt || '#fff';
    return `<div style="
        background:${color};
        
        color:${txt};
        border-radius:50%;
        
        width:${size}px;height:${size}px;
        display:flex;align-items:center;justify-content:center;
        
        font-weight:800;font-size:${size >= 40 ? 13 : 11}px;
        box-shadow:0 2px 8px rgba(0,0,0,.25);
        border:2px solid #fff;
      ">${score}</div>`;
  }
 
  function buildPopupHtml(z, score, color, statusLabel) {
    return `
      <div style="font-family:Inter,Segoe UI;line-height:1.4">
        <div style="font-weight:700;color:#0d3b66;font-size:14px">${z.name}</div>
        <div style="color:#6b7280;font-size:11px;margin-bottom:6px">${z.name_ar} · ${z.category}</div>
        <div><span style="background:${color};color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600">${statusLabel}</span>
             <strong style="margin-left:6px">${score}%</strong></div>
        <div style="margin-top:4px;font-size:12px">Risk: <strong>${z.risk_score ?? '—'}/100</strong></div>
        <div style="font-size:11px;color:#6b7280">${z.reports_total} reports · ${z.symptoms_total} symptoms</div>
        ${z.population ? `<div style="font-size:11px;color:#6b7280">Population: ${(+z.population).toLocaleString('en-US')}</div>` : ''}
      </div>`;
  }
 
  
  // Popup riche multi-API (mode "Now" avec fusion) — détail polluants/météo/sources.
  function buildFusedPopup(z, fz) {
    const aqi = Math.round(fz.final_aqi);
    const col = aqiColor(aqi), txt = aqiTextColor(aqi);
    const numCol = col === '#FFFF00' ? '#b45309' : col;
    return `
      <div style="font-family:Inter,Segoe UI;line-height:1.4">
        <div style="font-weight:700;color:#0d3b66;font-size:14px">${z.name}</div>
        <div style="color:#6b7280;font-size:11px;margin-bottom:6px">${z.name_ar} · ${z.category}</div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:26px;font-weight:800;color:${numCol}">${aqi}</span>
          <span style="background:${col};color:${txt};padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700">${fz.final_category}</span>
        </div>
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid #eee">
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Polluants</div>
          <div style="font-size:11px;color:#374151">PM2.5 ${fz.final_pm25 ?? '—'} · PM10 ${fz.final_pm10 ?? '—'} · NO₂ ${fz.final_no2 ?? '—'}</div>
          <div style="font-size:11px;color:#374151">SO₂ ${fz.final_so2 ?? '—'} · O₃ ${fz.final_o3 ?? '—'} · CO ${fz.final_co ?? '—'}</div>
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-top:4px">Météo</div>
          <div style="font-size:11px;color:#374151">${fz.final_temperature ?? '—'}°C · ${fz.final_humidity ?? '—'}% · ${fz.final_wind_speed ?? '—'} km/h</div>
          <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-top:4px">Sources</div>
          <div style="font-size:11px;color:#374151">AccuWeather ${fz.sources_available.accuweather ? '✅' : '❌'} · IQAir ${fz.sources_available.iqair ? '✅' : '❌'} · WAQI ${fz.sources_available.waqi ? '✅' : '❌'}</div>
          <div style="font-size:10px;color:#9ca3af;margin-top:3px">Mis à jour : ${GT.fmt.date(fz.timestamp)}</div>
        </div>
      </div>`;
  }
 
  // aqiMode=true → couleur/catégorie US AQI + popup fusion riche ; sinon mode pollution %.
  function applyScores(scoresByZoneId, aqiMode) {
    let total = 0, count = 0;
    markers.forEach(({ z, m, circle }) => {
      const s = scoresByZoneId[z.id];
      if (s == null) return;
      const score = Math.round(Number(s));
      const fz = aqiMode ? fusedById[z.id] : null;
      let color, stroke, size, txt, popup;
      if (aqiMode) {
        color = aqiColor(score); stroke = color; txt = aqiTextColor(score);
        size = Math.round(2 * (15 + score / 10));
        popup = fz ? buildFusedPopup(z, fz) : buildPopupHtml(z, score, color, fz ? fz.final_category : '');
      } else {
        const status = score >= 70 ? 'critical' : score >= 40 ? 'warning' : 'safe';
        color = colorFor(status); stroke = color; txt = '#fff'; size = 34;
        const stLabel = status === 'critical' ? 'Critical' : status === 'warning' ? 'Watch' : 'Safe';
        popup = buildPopupHtml(z, score, color, stLabel);
      }
      // 1) Update circle style (color + fill)
      
      circle.setStyle({ color: stroke, fillColor: color });
      // 2) Force-rebuild marker icon (DOM changes alone don't always stick)
      m.setIcon(L.divIcon({
        className: 'gt-marker',
        html: buildMarkerHtml(score, color, size, txt),
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      }));
      // 3) Update popup so opening it shows fresh data
      
      m.setPopupContent(popup);
      circle.setPopupContent(popup);
      // 4) Track for detail panel + summary
      currentScores[z.id] = score;
      total += score; count += 1;
    });
    // Live detail panel refresh if a zone is currently displayed
    if (selectedZone) renderDetail(selectedZone);
    // Summary chip
    const avgEl = document.getElementById('mtc-avg');
    
    if (avgEl) avgEl.textContent = count ? (aqiMode ? `AQI moy ${Math.round(total / count)}` : `avg ${Math.round(total / count)}%`) : 'avg —';
  }
 
  async function loadTimelapse() {
    try {
      const r = await fetch('../backend/api/timelapse.php?days=7', { credentials: 'same-origin' });
      timelapseData = await r.json();
      const sl = document.getElementById('map-timelapse');
      if (sl && timelapseData && timelapseData.timeline) {
        sl.max = String(timelapseData.timeline.length - 1);
        sl.value = String(timelapseData.timeline.length - 1);
      }
    } catch (_) {}
  }
 
  async function loadForecast() {
    try {
      const r = await fetch('../backend/api/forecast.php', { credentials: 'same-origin' });
      forecastData = await r.json();
    } catch (_) {}
  }
 
  function applyNow() {
    const hasFused = Object.keys(fusedById).length > 0;
    const map = {};
    
    data.zones.forEach(z => {
      const fz = fusedById[z.id];
      map[z.id] = (fz && fz.final_aqi != null) ? Math.round(fz.final_aqi) : (Number(z.pollution_level) || 0);
    });
    applyScores(map, hasFused);
    setSummary('Now', hasFused ? 'AQI fusionné multi-API · temps réel' : 'Real-time city snapshot');
  }
 
  function applyTimelapseDay(idx) {
    if (!timelapseData || !timelapseData.zones) return;
    const map = {};
    timelapseData.zones.forEach(z => { map[z.id] = z.scores[idx]; });
    applyScores(map);
    const d = (timelapseData.timeline || [])[idx] || '';
    const lastIdx = (timelapseData.timeline || []).length - 1;
    const daysAgo = lastIdx - idx;
    const label = daysAgo === 0 ? 'today'
                : daysAgo === 1 ? 'yesterday'
                : `${daysAgo} days ago`;
    setSummary('History', `${label} · ${d}`);
    const dayLbl = document.getElementById('map-timelapse-day');
    if (dayLbl) dayLbl.textContent = label;
    if (typeof window.__mapCompareHook === 'function') { var _sl = document.getElementById('map-timelapse'); window.__mapCompareHook(_sl ? Number(_sl.value) : 0); }
  }
 
  function applyForecast(h) {
    if (!forecastData || !forecastData.zones) return;
    const map = {};
    forecastData.zones.forEach(zo => {
      const horizon = (zo.horizons || []).find(x => x.h === Number(h));
      if (horizon) map[zo.zone.id] = horizon.predicted;
    });
    applyScores(map);
    setSummary('Forecast', `AI prediction in ${h} hour${h > 1 ? 's' : ''}`);
  }
 
  function setSummary(modeName, text) {
    const pill = document.getElementById('mtc-mode-pill');
    const txt  = document.getElementById('mtc-summary-text');
    if (pill) {
      pill.textContent = modeName;
      pill.dataset.mode = modeName.toLowerCase();
    }
    if (txt) txt.textContent = text;
    // Pulse animation hint that the map just changed
    const card = document.getElementById('map-time-card');
    if (card) {
      card.classList.remove('mtc-pulse');
      // Force reflow to restart animation
      void card.offsetWidth;
      card.classList.add('mtc-pulse');
    }
  }
 
  await Promise.all([loadTimelapse(), loadForecast()]);
 
  // Initial summary (Now)
  applyNow();
 
  /* --- Tabs (Now / History / AI Forecast) --- */
  const tabs   = document.querySelectorAll('#map-time-card .mtc-tab');
  const panels = document.querySelectorAll('#map-time-card .mtc-panel');
  function setMode(mode) {
    tabs.forEach(t => {
      const active = t.dataset.mode === mode;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', String(active));
    });
    panels.forEach(p => {
      const active = p.dataset.panel === mode;
      p.classList.toggle('is-active', active);
      p.hidden = !active;
    });
    if (mode === 'now') {
      stopPlay();
      applyNow();
    } else if (mode === 'history') {
      const sl = document.getElementById('map-timelapse');
      if (sl) applyTimelapseDay(Number(sl.value));
    } else if (mode === 'forecast') {
      stopPlay();
      // Auto-pick first horizon if none selected
      const chips = document.querySelectorAll('#map-time-card .mtc-chip');
      const active = Array.from(chips).find(c => c.classList.contains('is-active'));
      const target = active || chips[0];
      if (target) {
        chips.forEach(c => c.classList.remove('is-active'));
        target.classList.add('is-active');
        applyForecast(Number(target.dataset.h));
      }
    }
  }
  tabs.forEach(t => t.addEventListener('click', () => setMode(t.dataset.mode)));
 
  /* --- History slider --- */
  const slider = document.getElementById('map-timelapse');
  if (slider) {
    slider.addEventListener('input', () => applyTimelapseDay(Number(slider.value)));
  }
 
  /* --- Forecast horizon chips --- */
  document.querySelectorAll('#map-time-card .mtc-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('#map-time-card .mtc-chip').forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      applyForecast(Number(chip.dataset.h));
    });
  });
 
  /* --- Refresh button (Now) --- */
  const refreshBtn = document.getElementById('mtc-refresh');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', async () => {
      refreshBtn.classList.add('is-spinning');
      try {
        const fresh = await GT.api.get('zones.php');
        if (fresh && fresh.zones) {
          fresh.zones.forEach(z => {
            const m = markers.find(x => x.z.id === z.id);
            if (m) Object.assign(m.z, z);
          });
          await Promise.all([loadTimelapse(), loadForecast()]);
          applyNow();
        }
      } catch (_) {}
      setTimeout(() => refreshBtn.classList.remove('is-spinning'), 500);
    });
  }
 
  /* --- Play / Stop animation --- */
  const playBtn = document.getElementById('map-timelapse-play');
  const playLbl = playBtn ? playBtn.querySelector('span') : null;
  let playSpeed = 1;               // UPGRADE v9 — Part 54.2 : multiplicateur de vitesse
  const BASE_INTERVAL = 900;
  function stopPlay() {
    if (!playTimer) return;
    clearInterval(playTimer); playTimer = null;
    if (playBtn)  playBtn.classList.remove('is-playing');
    if (playLbl) playLbl.textContent = 'Play';
  }
  function startPlay() {
    if (playTimer || !slider) return;
    playBtn.classList.add('is-playing');
    if (playLbl) playLbl.textContent = 'Stop';
    let i = Number(slider.value) || 0;
    playTimer = setInterval(() => {
      slider.value = String(i);
      applyTimelapseDay(i);
      i = (i + 1) % (Number(slider.max) + 1);
    }, Math.max(120, BASE_INTERVAL / playSpeed));
  }
  if (playBtn && slider) {
    playBtn.addEventListener('click', () => {
      if (playTimer) { stopPlay(); return; }
      startPlay();
    });

    // UPGRADE v9 — Part 54.2 : sélecteur de vitesse (0.5x / 1x / 2x / 4x).
    if (!document.getElementById('mtc-speed')) {
      const sp = document.createElement('span');
      sp.id = 'mtc-speed';
      sp.style.cssText = 'display:inline-flex;gap:4px;margin-left:8px;vertical-align:middle';
      [0.5, 1, 2, 4].forEach(v => {
        const b = document.createElement('button');
        b.textContent = v + 'x';
        b.style.cssText = 'font-size:11px;padding:2px 6px;border-radius:6px;border:1px solid #cbd5e1;cursor:pointer;background:' + (v === 1 ? '#0d3b66' : '#fff') + ';color:' + (v === 1 ? '#fff' : '#0d3b66');
        b.addEventListener('click', () => {
          playSpeed = v;
          sp.querySelectorAll('button').forEach(x => { x.style.background = '#fff'; x.style.color = '#0d3b66'; });
          b.style.background = '#0d3b66'; b.style.color = '#fff';
          if (playTimer) { stopPlay(); startPlay(); }
        });
        sp.appendChild(b);
      });
      if (playBtn.parentNode) playBtn.parentNode.insertBefore(sp, playBtn.nextSibling);
    }

    // UPGRADE v9 — Part 54.3 : bouton d'export GIF (dégradation gracieuse via timelapse_export.js).
    if (!document.getElementById('mtc-export') && typeof window.exportTimelapseGif === 'function') {
      const eb = document.createElement('button');
      eb.id = 'mtc-export';
      eb.textContent = '⤓ GIF';
      eb.style.cssText = 'font-size:11px;padding:2px 8px;margin-left:8px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#0d3b66;cursor:pointer';
      eb.addEventListener('click', () => window.exportTimelapseGif({
        sliderId: 'map-timelapse', mapId: 'leaflet-map',
        applyFrame: (idx) => applyTimelapseDay(idx),
        frameCount: Number(slider.max) + 1, fps: 2
      }));
      if (playBtn.parentNode) playBtn.parentNode.insertBefore(eb, playBtn.nextSibling);
    }

    // UPGRADE v9 — Part 54.4 : comparaison de 2 zones (split view synchronisé sur le slider).
    (function setupCompare() {
      const card = document.getElementById('map-time-card');
      if (!card || document.getElementById('mtc-compare')) return;
      const box = document.createElement('div');
      box.id = 'mtc-compare';
      box.style.cssText = 'margin-top:10px;padding-top:8px;border-top:1px dashed #cbd5e1;font-size:12px';
      box.innerHTML = '<div style="font-weight:600;margin-bottom:4px">⇄ Comparer 2 zones</div>'
        + '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">'
        + '<select id="mtc-cmp-a"></select><select id="mtc-cmp-b"></select></div>'
        + '<div id="mtc-cmp-out" style="display:flex;gap:12px;margin-top:8px"></div>';
      card.appendChild(box);
      const selA = box.querySelector('#mtc-cmp-a');
      const selB = box.querySelector('#mtc-cmp-b');
      function ensureOpts() {
        if (selA.options.length || !window.timelapseData || !timelapseData.zones) return;
        const opts = timelapseData.zones.map(z => '<option value="' + z.id + '">' + (z.name || ('Zone ' + z.id)) + '</option>').join('');
        selA.innerHTML = opts; selB.innerHTML = opts;
        if (selB.options.length > 1) selB.selectedIndex = 1;
      }
      function bar(zid, idx) {
        if (!window.timelapseData || !timelapseData.zones) return '';
        const z = timelapseData.zones.find(x => String(x.id) === String(zid));
        if (!z || !z.scores) return '';
        const v = Math.round(z.scores[idx] != null ? z.scores[idx] : 0);
        const col = v >= 70 ? '#dc2626' : v >= 40 ? '#d97706' : '#16a34a';
        return '<div style="flex:1"><div style="font-weight:600">' + (z.name || zid) + '</div>'
          + '<div style="background:#eef2f7;border-radius:6px;height:14px;overflow:hidden"><span style="display:block;height:100%;width:' + v + '%;background:' + col + '"></span></div>'
          + '<div style="font-size:11px">' + v + '%</div></div>';
      }
      window.__mapCompareHook = function (idx) {
        ensureOpts();
        const out = document.getElementById('mtc-cmp-out');
        if (out) out.innerHTML = bar(selA.value, idx) + bar(selB.value, idx);
      };
      const refresh = () => { const sl = document.getElementById('map-timelapse'); window.__mapCompareHook(sl ? Number(sl.value) : 0); };
      selA.addEventListener('change', refresh);
      selB.addEventListener('change', refresh);
      refresh();
    })();
  }
}