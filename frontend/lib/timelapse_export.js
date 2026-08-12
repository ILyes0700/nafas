/* UPGRADE v9 — Part 54.3 : export du time-lapse en GIF (ou séquence d'images).
 * Capture le conteneur Leaflet frame par frame en avançant le slider.
 * Dégradation gracieuse :
 *  - si une lib GIF globale (window.GIF de gif.js) est chargée => vrai GIF animé ;
 *  - sinon => télécharge la dernière frame en PNG et prévient l'utilisateur.
 * Aucune dépendance obligatoire : fonctionne hors-ligne (WAMP) sans CDN.
 */
(function () {
  'use strict';

  // Petit util : attend un tick de rendu.
  const raf = () => new Promise(r => requestAnimationFrame(() => r()));
  const sleep = ms => new Promise(r => setTimeout(r, ms));

  async function captureCanvas(mapEl) {
    // Leaflet n'expose pas un canvas unique ; on tente html2canvas si dispo,
    // sinon on capture le premier <canvas> du conteneur (couche heatmap/canvas).
    if (window.html2canvas) {
      return await window.html2canvas(mapEl, { useCORS: true, logging: false });
    }
    const cv = mapEl.querySelector('canvas');
    return cv || null;
  }

  function downloadDataUrl(dataUrl, filename) {
    const a = document.createElement('a');
    a.href = dataUrl; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
  }

  /**
   * @param {Object} opts { sliderId, mapId, applyFrame(idx), frameCount, fps }
   */
  async function exportTimelapseGif(opts) {
    opts = opts || {};
    const slider = document.getElementById(opts.sliderId || 'map-timelapse');
    const mapEl = document.getElementById(opts.mapId || 'leaflet-map');
    if (!mapEl) { alert('Carte introuvable pour l\'export.'); return; }
    const frames = opts.frameCount || (slider ? Number(slider.max) + 1 : 7);
    const fps = opts.fps || 2;
    const apply = typeof opts.applyFrame === 'function' ? opts.applyFrame : null;

    // Chemin 1 : gif.js présent -> vrai GIF animé.
    if (window.GIF) {
      try {
        const gif = new window.GIF({ workers: 2, quality: 10 });
        for (let i = 0; i < frames; i++) {
          if (slider) slider.value = String(i);
          if (apply) apply(i);
          await raf(); await sleep(120);
          const cv = await captureCanvas(mapEl);
          if (cv && cv.getContext) gif.addFrame(cv, { copy: true, delay: 1000 / fps });
        }
        gif.on('finished', blob => downloadDataUrl(URL.createObjectURL(blob), 'timelapse-gabes.gif'));
        gif.render();
        return;
      } catch (e) { /* tombe vers le repli PNG */ }
    }

    // Chemin 2 (repli) : capture la frame courante en PNG.
    const cv = await captureCanvas(mapEl);
    if (cv && cv.toDataURL) {
      downloadDataUrl(cv.toDataURL('image/png'), 'timelapse-frame.png');
      alert('Export image (PNG) réussi. Pour un GIF animé, ajoutez gif.js (window.GIF).');
    } else {
      alert('Export indisponible : ajoutez html2canvas ou gif.js pour capturer la carte.');
    }
  }

  window.exportTimelapseGif = exportTimelapseGif;
})();
