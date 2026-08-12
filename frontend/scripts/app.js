/* App globale : utilisateur courant injecté par PHP, helpers UI */

window.GT = window.GT || {};
GT.user = window.GT_USER || { role: 'citizen', allowed: [] };
GT.role = GT.user.role;

GT.notTrainedGuard = function (notReady) {
  var host = document.querySelector('#view, .content, main, .page') || document.body;
  var id = 'gt-not-trained';
  var ex = document.getElementById(id);
  if (!notReady) { if (ex) ex.remove(); return false; }
  if (!ex) {
    ex = document.createElement('div');
    ex.id = id;
    ex.style.cssText = 'margin:14px 0;padding:16px 18px;border:1px solid #cfe0f1;border-left:4px solid #0d3b66;background:#f3f8fd;border-radius:10px;color:#0d3b66;font-size:14px;line-height:1.55';
    ex.innerHTML = '<b>Mod\u00e8les non entra\u00een\u00e9s \u2014 aucune donn\u00e9e r\u00e9elle</b><br>Cette page n\'affiche <b>aucun</b> r\u00e9sultat de d\u00e9monstration. Lancez <code style="background:#e2edf8;padding:1px 6px;border-radius:5px">python -m models.train_all</code> pour g\u00e9n\u00e9rer de vrais r\u00e9sultats, puis rechargez la page.';
    if (host.firstChild) host.insertBefore(ex, host.firstChild); else host.appendChild(ex);
  }
  try {
    document.querySelectorAll('canvas').forEach(function (c) { var cx = c.getContext && c.getContext('2d'); if (cx) cx.clearRect(0, 0, c.width, c.height); });
    document.querySelectorAll('table tbody').forEach(function (tb) { tb.innerHTML = '<tr><td colspan="14" class="muted" style="text-align:center;padding:16px">Aucune donn\u00e9e \u2014 lancez l\'entra\u00eenement des mod\u00e8les</td></tr>'; });
  } catch (e) {}
  return true;
};

GT.fmt = {
  date: (s) => {
    if (!s) return '—';
    const d = new Date(s.replace(' ','T'));
    return d.toLocaleString('en-US', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
  },
  pct: (n) => `${Math.round(n)}%`,
  pill: (status) => `<span class="pill ${status}">${status}</span>`,
};

/* Global status displayed in the sidebar */
async function refreshGlobalStatus() {
  try {
    const data = await GT.api.get('dashboard.php');
    const dot = document.getElementById('global-dot');
    const lbl = document.getElementById('global-label');
    const cnt = document.getElementById('global-alerts');
    if (dot && lbl) {
      dot.className = 'dot ' + data.global_status;
      lbl.textContent = ({safe:'Safe', warning:'Warning', critical:'Critical'})[data.global_status] || '—';
    }
    if (cnt) cnt.textContent = `${data.counts.alerts} alerts (24h)`;
  } catch (e) {
    const lbl = document.getElementById('global-label');
    if (lbl) lbl.textContent = 'Offline';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.body.setAttribute('data-role', GT.role);
  refreshGlobalStatus();
  setInterval(refreshGlobalStatus, 30000);

  /* --------------------------------------------------------------------
     Auto-ingestion des donnees API dans la base : CHAQUE 1 MINUTE.
     Reserve aux roles admin / health / super_admin (le serveur revalide).
     Appelle iqair-refresh.php?force=1 -> UPDATE zones + INSERT risk_scores
     (une vraie ligne horodatee par passage). Donnees 100% reelles.
     -------------------------------------------------------------------- */
  (function autoIngestApi() {
    var role = (GT.user && GT.user.role) || '';
    if (['admin', 'health', 'super_admin'].indexOf(role) === -1) return;
    var busy = false;
    async function ingest() {
      if (busy || document.hidden) return;
      busy = true;
      try {
        var r = await GT.api.get('iqair-refresh.php', { force: '1' });
        if (r && r.ok) {
          window.__lastApiIngest = {
            at: new Date().toISOString(),
            zones: r.total || 0, success: r.success || 0, changed: r.changed || 0
          };
          if (window.console && console.info) {
            console.info('[auto-ingest] ' + new Date().toLocaleTimeString() +
              ' — ' + (r.success || 0) + '/' + (r.total || 0) + ' zones, ' +
              (r.changed || 0) + ' changed, enregistre en base.');
          }
        }
      } catch (e) { /* silencieux : reseau ou permission */ }
      busy = false;
    }
    setTimeout(ingest, 8000);       // premier passage apres 8s
    setInterval(ingest, 60000);     // puis chaque 60 secondes
  })();
});
