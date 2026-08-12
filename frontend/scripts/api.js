/* Petit wrapper fetch vers les endpoints PHP locaux (WAMP) */

const API_BASE = (() => {
  // En navigateur normal sous WAMP : localhost/gabes-tatenafas/backend/api/...
  // En Electron : on charge directement http://localhost/gabes-tatenafas/frontend/index.php
  // donc le chemin relatif fonctionne aussi.
  const here = window.location.href;
  if (here.startsWith('file://')) {
    // si jamais lancé hors WAMP — fallback localhost
    return 'http://localhost/gabes-tatenafas/backend/api';
  }
  // chemin relatif depuis /frontend/
  return '../backend/api';
})();

async function apiGet(path, params = {}) {
  const q = new URLSearchParams(params).toString();
  const url = `${API_BASE}/${path}${q ? '?' + q : ''}`;
  const r = await fetch(url, { method: 'GET' });
  if (!r.ok) throw new Error(`GET ${path} → ${r.status}`);
  return r.json();
}

async function apiPost(path, body = {}) {
  const r = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!r.ok) throw new Error(`POST ${path} → ${r.status}`);
  return r.json();
}

/** POST en multipart/form-data — utilisé pour upload d'image. */
async function apiPostForm(path, formData) {
  const r = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    body: formData,
    // NE PAS forcer Content-Type : le navigateur ajoute le boundary multipart automatiquement
  });
  if (!r.ok) throw new Error(`POST ${path} → ${r.status}`);
  return r.json();
}

/** Construit l'URL absolue d'un fichier servi par WAMP (image, PDF, etc.) */
function apiAsset(relative) {
  if (!relative) return '';
  if (/^https?:\/\//.test(relative)) return relative;
  // API_BASE = "../backend/api" → racine projet = "../"
  if (API_BASE.startsWith('http')) {
    return API_BASE.replace(/\/backend\/api$/, '/') + relative.replace(/^\/+/, '');
  }
  return '../' + relative.replace(/^\/+/, '');
}

window.GT = window.GT || {};
window.GT.api = { get: apiGet, post: apiPost, postForm: apiPostForm, asset: apiAsset, base: API_BASE };
