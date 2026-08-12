/**
 * Real-time WebSocket client (PART 17.5).
 * Connects to the Flask-SocketIO server (models/websocket_server.py, port 5001)
 * and updates the live badge, AQI values and map markers in place.
 *
 * Degrades gracefully: if socket.io is missing or the server is offline, the
 * badge simply shows OFFLINE and the rest of the app keeps working.
 */
(function () {
  const badge = () => document.getElementById('live-badge');
  function setBadge(txt, cls) {
    const el = badge();
    if (!el) return;
    el.textContent = txt;
    el.className = 'live-badge ' + cls;
  }

  function categoryColor(cat) {
    const c = (cat || '').toLowerCase();
    if (c.includes('bon') || c.includes('safe')) return '#00E400';
    if (c.includes('mod')) return '#FFFF00';
    if (c.includes('sgs')) return '#FF7E00';
    if (c.includes('mauvais')) return '#FF0000';
    if (c.includes('très') || c.includes('very')) return '#99004C';
    return '#7E0023';
  }

  function updateCity(data) {
    const el = document.getElementById('aqi-' + data.city_id);
    if (el && typeof data.aqi === 'number') {
      el.textContent = data.aqi.toFixed(0);
      el.className = 'aqi-badge aqi-' + String(data.category || '').toLowerCase();
    }
    if (window.mapMarkers && window.mapMarkers[data.city_id]) {
      const col = categoryColor(data.category);
      try { window.mapMarkers[data.city_id].setStyle({ color: col, fillColor: col }); } catch (e) {}
    }
    if (data.anomaly && typeof window.showToast === 'function') {
      window.showToast('🚨 Anomalie détectée — ' + (data.city_name || data.city_id) + ' (AQI ' + Math.round(data.aqi) + ')', 'danger');
    }
    const stamp = document.getElementById('live-updated');
    if (stamp) stamp.textContent = 'Dernière mise à jour : à l\'instant';
  }

  function connect() {
    if (typeof io === 'undefined') { setBadge('🔴 OFFLINE', 'off'); return; }
    let socket;
    try { socket = io('http://localhost:5001', { reconnectionAttempts: 3, timeout: 4000 }); }
    catch (e) { setBadge('🔴 OFFLINE', 'off'); return; }

    socket.on('connect', () => setBadge('🟢 LIVE', 'on'));
    socket.on('disconnect', () => setBadge('🔴 OFFLINE', 'off'));
    socket.on('connect_error', () => setBadge('🔴 OFFLINE', 'off'));
    socket.on('aqi_update', updateCity);
    window.GT_SOCKET = socket;
  }

  document.addEventListener('DOMContentLoaded', () => { setBadge('… connexion', 'off'); connect(); });
})();
