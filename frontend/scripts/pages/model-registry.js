/* PART 43 + 44 — Model Registry & A/B testing (admin). Graceful: shows an empty
 * state if the v6 migration/tables are not present yet. */
async function initModelRegistry() {
  const head = document.getElementById('mr-head');
  const body = document.getElementById('mr-body');
  const hint = document.getElementById('mr-hint');
  const tabV = document.getElementById('mr-tab-versions');
  const tabA = document.getElementById('mr-tab-ab');
  if (!body) return;

  const API = (window.GT && GT.api && GT.api.base) ? GT.api.base : 'backend/api';

  function render(cols, rows) {
    head.innerHTML = cols.map(c => `<th>${c}</th>`).join('');
    if (!rows || !rows.length) {
      body.innerHTML = `<tr><td colspan="${cols.length}" class="mr-empty">Aucune donnée. Lancez <code>python models/train_all.py</code> après la migration v6.</td></tr>`;
      return;
    }
    body.innerHTML = rows.map(r =>
      '<tr>' + cols.map(c => `<td>${r[c] != null ? r[c] : '—'}</td>`).join('') + '</tr>'
    ).join('');
  }

  async function loadVersions() {
    hint.textContent = 'Chargement des versions…';
    try {
      const res = await fetch(`${API}/model-registry.php`, { credentials: 'same-origin' });
      const j = await res.json();
      render(['model_name', 'version', 'status', 'trained_at', 'promoted_at'], j.versions || []);
      hint.textContent = j.note ? j.note : `${(j.versions || []).length} version(s)`;
    } catch (e) {
      render(['model_name', 'version', 'status'], []);
      hint.textContent = 'Registry indisponible.';
    }
  }

  async function loadAb() {
    hint.textContent = 'Chargement des tests A/B…';
    try {
      const res = await fetch(`${API}/model-registry.php?ab=1`, { credentials: 'same-origin' });
      const j = await res.json();
      render(['model_a', 'model_b', 'winner', 'decision_reason', 'started_at'], j.ab_runs || []);
      hint.textContent = j.note ? j.note : `${(j.ab_runs || []).length} run(s)`;
    } catch (e) {
      render(['model_a', 'model_b', 'winner'], []);
      hint.textContent = 'A/B runs indisponibles.';
    }
  }

  if (tabV) tabV.onclick = () => { tabV.classList.remove('ghost'); tabA.classList.add('ghost'); loadVersions(); };
  if (tabA) tabA.onclick = () => { tabA.classList.remove('ghost'); tabV.classList.add('ghost'); loadAb(); };
  loadVersions();
}
window.initModelRegistry = initModelRegistry;
