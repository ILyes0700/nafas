async function initZones() {
  let data = (await GT.api.get('zones.php')).zones;
  let sortKey = 'pollution_level';

  const render = () => {
    const q = (document.getElementById('zone-search').value || '').toLowerCase();
    const rows = data
      .filter(z => z.name.toLowerCase().includes(q) || z.name_ar.includes(q))
      .slice().sort((a,b) => (b[sortKey] ?? 0) - (a[sortKey] ?? 0));
    document.querySelector('#zones-table tbody').innerHTML = rows.map(z => `
      <tr>
        <td><strong>${z.name}</strong><div class="muted" style="font-size:12px">${z.name_ar} · ${z.category}</div></td>
        <td><span class="pill ${z.status}">${z.status}</span></td>
        <td><div class="row" style="gap:8px"><div class="bar ${z.status}" style="width:120px"><span style="width:${z.pollution_level}%"></span></div>${z.pollution_level}%</div></td>
        <td><strong>${z.risk_score ?? '—'}</strong>/100</td>
        <td>${z.reports_total}</td>
        <td>${z.symptoms_total}</td>
        <td>${(+z.population).toLocaleString('fr-FR')}</td>
      </tr>
    `).join('');
  };
  render();

  document.getElementById('zone-search').addEventListener('input', render);
  document.querySelectorAll('[data-sort]').forEach(b => b.addEventListener('click', () => { sortKey = b.dataset.sort; render(); }));
  document.getElementById('recompute').addEventListener('click', async () => {
    await GT.api.get('risk.php', { action: 'recompute' });
    data = (await GT.api.get('zones.php')).zones;
    render();
  });
}
