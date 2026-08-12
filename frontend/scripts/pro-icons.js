/* ============================================================================
 * pro-icons.js — Remplace TOUS les emojis de l'UI par des icônes SVG PRO
 * (même style Lucide que la sidebar : stroke 1.85, currentColor).
 *
 * Fonctionne à l'exécution : parcourt les nœuds de texte de la page (y compris
 * le contenu injecté dynamiquement par les scripts de page) et remplace chaque
 * emoji par un <span class="pico"> contenant l'icône SVG correspondante.
 * → aucune modification des 44 fichiers sources, aucun risque de casse.
 *
 * Expose aussi window.ICON('check') pour usage direct dans le code.
 * ========================================================================== */
(function () {
  'use strict';
  var S = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" class="pico-svg" aria-hidden="true">';
  var E = '</svg>';

  var ICONS = {
    chartBar:'<path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/>',
    chartUp:'<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    chartDown:'<path d="M3 7l6 6 4-4 8 8"/><path d="M15 17h6v-6"/>',
    target:'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
    brain:'<path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5 3 3 0 0 0 2 5 3 3 0 0 0 3 2V4z"/><path d="M15 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5 3 3 0 0 1-2 5 3 3 0 0 1-3 2V4z"/>',
    robot:'<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4"/><circle cx="9" cy="14" r="1.2" fill="currentColor"/><circle cx="15" cy="14" r="1.2" fill="currentColor"/>',
    lab:'<path d="M9 3v6l-5 9a2 2 0 0 0 1.8 3h12.4a2 2 0 0 0 1.8-3l-5-9V3"/><path d="M8 3h8"/>',
    sat:'<path d="M4 10l4-4 4 4-4 4z"/><path d="M13 5l6 6"/><path d="M15 15a4 4 0 0 0 4-4"/>',
    calendar:'<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    bell:'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    lock:'<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    unlock:'<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
    folder:'<path d="M3 7a2 2 0 0 1 2-2h4l2 3h8a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    pin:'<path d="M12 21s-6-5.7-6-10a6 6 0 0 1 12 0c0 4.3-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>',
    book:'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/>',
    memo:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
    clipboard:'<rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/>',
    mag:'<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    envelope:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    chat:'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    wind:'<path d="M3 8h11a3 3 0 1 0-3-3"/><path d="M3 12h16a3 3 0 1 1-3 3"/><path d="M3 16h9a3 3 0 1 1-3 3"/>',
    cloud:'<path d="M6 18a4 4 0 0 1 0-8 6 6 0 0 1 11.5-1.5A4 4 0 0 1 18 18z"/>',
    factory:'<path d="M3 21V9l6 4V9l6 4V5l6 4v12z"/><path d="M3 21h18"/>',
    droplet:'<path d="M12 3s6 6 6 11a6 6 0 0 1-12 0c0-5 6-11 6-11z"/>',
    zap:'<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
    grad:'<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v4c3 2 9 2 12 0v-4"/>',
    mask:'<path d="M4 9c0-1 1-2 3-2h10c2 0 3 1 3 2v3a5 5 0 0 1-5 5h-1a5 5 0 0 1-3 0h-1a5 5 0 0 1-5-5z"/><path d="M4 10H2M22 10h-2M8 12h8"/>',
    muscle:'<path d="M4 20v-4a4 4 0 0 1 4-4h2V8a3 3 0 0 1 6 0c2 0 4 2 4 5v7z"/>',
    hands:'<path d="M12 3v8M8 5v6M16 5v6M6 11h12v4a6 6 0 0 1-12 0z"/>',
    speaker:'<path d="M4 9v6h4l5 4V5L8 9z"/><path d="M17 8a5 5 0 0 1 0 8"/>',
    check:  '<path d="M20 6L9 17l-5-5"/>',
    x:      '<path d="M18 6L6 18M6 6l12 12"/>',
    warn:   '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13.5"/><circle cx="12" cy="17" r="0.6" fill="currentColor"/>',
    siren:  '<path d="M6 20v-6a6 6 0 0 1 12 0v6"/><path d="M4 20h16"/><path d="M12 3V2"/><path d="M18.7 5.3l.7-.7"/><path d="M4.6 4.6l.7.7"/>',
    heart:  '<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 0 0-7.1 7.1l1.7 1.7L12 21l7.1-7.1 1.7-1.7a5 5 0 0 0 0-6.6z"/>',
    thermo: '<path d="M14 14.76V4a2 2 0 1 0-4 0v10.76a4 4 0 1 0 4 0z"/>',
    globe:  '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/>',
    monitor:'<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
    clip:   '<path d="M21 11.5l-8.6 8.6a5 5 0 0 1-7.1-7.1l8.6-8.6a3.3 3.3 0 0 1 4.7 4.7L9.6 17.4a1.7 1.7 0 0 1-2.4-2.4l7.9-7.9"/>',
    image:  '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    key:    '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2"/><path d="M16.5 6.5l3 3"/><path d="M14.5 8.5l3 3"/>',
    wave:   '<path d="M6 12V5.5a1.5 1.5 0 0 1 3 0V11"/><path d="M9 11V4a1.5 1.5 0 0 1 3 0v7"/><path d="M12 11V5a1.5 1.5 0 0 1 3 0v6"/><path d="M15 11.5V7.5a1.5 1.5 0 0 1 3 0V13a6 6 0 0 1-6 6a6 6 0 0 1-5.2-3L4 15a1.5 1.5 0 0 1 2.6-1.5L8 15"/>',
    dot:    '<circle cx="12" cy="12" r="6" fill="currentColor" stroke="none"/>',
    circle: '<circle cx="12" cy="12" r="7"/>',
    info:   '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="0.6" fill="currentColor"/>',
    star:   '<path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.2l1-5.8L3.5 9.2l5.9-.9z"/>',
    bulb:   '<path d="M9 18h6"/><path d="M10 21h4"/><path d="M8.5 15a5.5 5.5 0 1 1 7 0c-.7.6-1 1.2-1 2H9.5c0-.8-.3-1.4-1-2z"/>',
    rocket: '<path d="M5 15l-2 6 6-2"/><path d="M14 4c3 0 6 3 6 6l-8 8-4-4z"/><circle cx="14.5" cy="9.5" r="1.4"/>',
    fire:   '<path d="M12 3c1 3-1 4-1 6a3 3 0 0 0 5 2c1 3-1 7-4 7a5 5 0 0 1-5-5c0-3 3-4 3-7 0-1 1-2 2-3z"/>'
  };

  function svg(name) { var p = ICONS[name]; return p ? (S + p + E) : ''; }

  window.ICON = function (name, cls) {
    if (!ICONS[name]) return '';
    return '<span class="pico ' + (cls || '') + '">' + svg(name) + '</span>';
  };

  // emoji -> [iconName, colorClass]
  var MAP = {
    '\uD83D\uDCCA': ['chartBar',''], '\uD83D\uDCC8': ['chartUp','pico-green'], '\uD83D\uDCC9': ['chartDown','pico-red'],
    '\uD83C\uDFAF': ['target',''], '\uD83E\uDDE0': ['brain',''], '\uD83E\uDD16': ['robot',''],
    '\uD83D\uDD2C': ['lab',''], '\uD83E\uDDEA': ['lab',''], '\uD83D\uDCE1': ['sat',''],
    '\uD83D\uDCC5': ['calendar',''], '\uD83D\uDCC6': ['calendar',''], '\u23F0': ['clock',''], '\u23F1': ['clock',''],
    '\uD83D\uDD14': ['bell',''], '\uD83D\uDD15': ['bell',''],
    '\uD83D\uDD12': ['lock',''], '\uD83D\uDD10': ['lock',''], '\uD83D\uDD13': ['unlock',''],
    '\uD83D\uDCC1': ['folder',''], '\uD83D\uDCC2': ['folder',''], '\uD83D\uDCCC': ['pin','pico-red'], '\uD83D\uDCCD': ['pin','pico-red'],
    '\uD83D\uDCDA': ['book',''], '\uD83D\uDCD6': ['book',''], '\uD83D\uDCDD': ['memo',''], '\u270F': ['memo',''], '\uD83D\uDD8A': ['memo',''],
    '\uD83D\uDCCB': ['clipboard',''], '\uD83D\uDD0D': ['mag',''], '\uD83D\uDD0E': ['mag',''],
    '\u2709': ['envelope',''], '\uD83D\uDCE7': ['envelope',''], '\uD83D\uDCE8': ['envelope',''], '\uD83D\uDCE9': ['envelope',''],
    '\uD83D\uDCAC': ['chat',''], '\uD83D\uDCAD': ['chat',''], '\uD83D\uDDE8': ['chat',''],
    '\uD83D\uDCA8': ['wind',''], '\uD83C\uDF2B': ['cloud',''], '\u2601': ['cloud',''], '\u26C5': ['cloud',''],
    '\uD83C\uDFED': ['factory',''], '\uD83D\uDCA7': ['droplet',''], '\u26A1': ['zap','pico-amber'], '\uD83C\uDF93': ['grad',''],
    '\uD83D\uDE37': ['mask',''], '\uD83D\uDCAA': ['muscle',''], '\uD83D\uDC4F': ['hands',''], '\uD83D\uDE4F': ['hands',''],
    '\uD83D\uDD0A': ['speaker',''], '\uD83D\uDD07': ['speaker',''], '\uD83D\uDD08': ['speaker',''],
    '\uD83C\uDF89': ['star','pico-amber'], '\uD83D\uDCAF': ['star','pico-amber'],
    '\u2705': ['check', 'pico-green'], '\u2611': ['check', 'pico-green'], '\u2714': ['check', 'pico-green'], '\u2713': ['check', ''],
    '\u274C': ['x', 'pico-red'], '\u2716': ['x', 'pico-red'], '\u2717': ['x', 'pico-red'], '\u2715': ['x', ''],
    '\u26A0': ['warn', 'pico-amber'],
    '\uD83D\uDEA8': ['siren', 'pico-red'],
    '\uD83D\uDD34': ['dot', 'pico-red'], '\uD83D\uDFE2': ['dot', 'pico-green'], '\uD83D\uDFE0': ['dot', 'pico-amber'],
    '\uD83D\uDFE1': ['dot', 'pico-amber'], '\u26AA': ['circle', ''], '\u2B55': ['circle', 'pico-red'],
    '\u25CF': ['dot', ''], '\u25CB': ['circle', ''],
    '\u2764': ['heart', 'pico-red'], '\u2665': ['heart', 'pico-red'],
    '\uD83C\uDF21': ['thermo', ''],
    '\uD83C\uDF10': ['globe', ''], '\uD83D\uDDA5': ['monitor', ''],
    '\uD83D\uDCCE': ['clip', ''], '\uD83D\uDDBC': ['image', ''],
    '\uD83D\uDD11': ['key', ''], '\uD83D\uDC4B': ['wave', ''],
    '\u2139': ['info', ''], '\u2B50': ['star', 'pico-amber'], '\u2728': ['star', 'pico-amber'],
    '\uD83D\uDCA1': ['bulb', 'pico-amber'], '\uD83D\uDE80': ['rocket', ''], '\uD83D\uDD25': ['fire', 'pico-amber']
  };

  function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  // trie par longueur décroissante pour matcher les paires de substitution d'abord
  var keys = Object.keys(MAP).sort(function (a, b) { return b.length - a.length; });
  var rxSrc = '(' + keys.map(esc).join('|') + ')(\\uFE0F)?';

  function shouldSkip(parent) {
    if (!parent) return true;
    var tag = parent.nodeName;
    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA' || tag === 'OPTION' || tag === 'CANVAS') return true;
    if (parent.isContentEditable) return true;
    if (parent.closest && parent.closest('.pico')) return true;
    if (parent.closest && parent.closest('[data-react-pick],[data-reactors],[data-like],.df-rx-pop,.df-reactions,.df-reaction,.df-rx-badge,.df-rx-people,.msgr-react')) return true;
    return false;
  }

  function upgrade(root) {
    if (!root || !root.querySelectorAll) return;
    var testRx = new RegExp(rxSrc);
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        if (shouldSkip(n.parentNode)) return NodeFilter.FILTER_REJECT;
        return testRx.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    var nodes = [], cur;
    while ((cur = walker.nextNode())) nodes.push(cur);
    if (!nodes.length) return;
    var splitRx = new RegExp(rxSrc, 'g');
    nodes.forEach(function (n) {
      var parts = n.nodeValue.split(splitRx);
      var frag = document.createDocumentFragment();
      for (var i = 0; i < parts.length; i++) {
        var part = parts[i];
        if (part == null || part === '\uFE0F') continue;
        if (MAP[part]) {
          var m = MAP[part];
          var span = document.createElement('span');
          span.className = 'pico ' + m[1];
          span.innerHTML = svg(m[0]);
          frag.appendChild(span);
        } else if (part) {
          frag.appendChild(document.createTextNode(part));
        }
      }
      if (n.parentNode) n.parentNode.replaceChild(frag, n);
    });
  }

  // Styles (injectés pour rester autonome, pas de dépendance CSS)
  function injectCss() {
    if (document.getElementById('pico-style')) return;
    var css = '.pico{display:inline-flex;align-items:center;justify-content:center;vertical-align:-0.15em;line-height:0}' +
      '.pico-svg{width:1.05em;height:1.05em;flex:0 0 auto}' +
      '.pico-green{color:#1a9d5a}.pico-red{color:#e5484d}.pico-amber{color:#e0952b}';
    var st = document.createElement('style');
    st.id = 'pico-style'; st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  var scheduled = false, observer = null;
  function run() {
    scheduled = false;
    if (observer) observer.disconnect();
    try { upgrade(document.body); } catch (e) { /* silencieux */ }
    if (observer) observer.observe(document.body, { childList: true, subtree: true });
  }
  function schedule() {
    if (scheduled) return;
    scheduled = true;
    (window.requestAnimationFrame || window.setTimeout)(run, 16);
  }

  function start() {
    injectCss();
    run();
    observer = new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        if (muts[i].addedNodes && muts[i].addedNodes.length) { schedule(); break; }
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
