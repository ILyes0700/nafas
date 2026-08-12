/** Upgrade overview hub — links to every scientific module.
 *
 *  v4.0 - Migration donnees reelles Open-Meteo :
 *    - La carte 'cgan' (Conditional GAN, PART 2) a ete supprimee. Le modele
 *      generatif et toute l'augmentation de donnees ont ete retires du projet.
 *    - PART 2 designe desormais le BiLSTM + Autoencoder, entraine sur les
 *      donnees reelles de la table open_data.
 *    - Un bandeau rappelle la provenance des donnees, pour que la nature
 *      reelle du jeu d'entrainement soit visible des la page d'accueil
 *      scientifique.
 */
window.initUpgradeDashboard = function () {
  const items = [
    ['fuzzy-type2',            'Fuzzy Logic Type-2',        'PART 1 · Karnik-Mendel + FOU'],
    /* v4.0 : remplace ['cgan', 'Conditional GAN', 'PART 2 · augmentation de données'] */
    ['bilstm-ae',              'BiLSTM + Autoencoder',      'PART 2 · représentation latente + prévision'],
    ['forecast-ml',            'ML — SHAP / LIME / ROC',    'PART 4 · RF + XGBoost + Optuna'],
    ['deep-learning',          'Deep Learning BiLSTM',      'PART 5 · Multi-Head Attention'],
    ['anomaly',                'Détection d\'anomalies',     'PART 5.5 · Autoencoder + IsoForest'],
    ['health-impact',          'Impact Sanitaire',          'PART 6 · indice spécifique Gabès'],
    ['comparison',             'Comparaison des modèles',   'PART 7/8/9/18 · ablation + stats'],
    ['granger',                'Causalité de Granger',      'PART 10 · SO2 → PM2.5'],
    ['comparative-literature', 'Comparaison littérature',   'PART 11 · vs publications'],
    ['ensemble',               'Ensemble & Trust',          'PART 12/13 · résiduel + incertitude'],
    ['drift',                  'Dérive & Auto-Optimisation','PART 14/16 · KL + Optuna'],
    ['spatial',                'Propagation spatiale',      'PART 15 · vent inter-villes'],
    ['smart-alerts',           'Alertes intelligentes',     'PART 17 · SHAP + LIME'],
    ['federated',              'Apprentissage fédéré',      'PART 20 · FedAvg'],
  ];

  const grid = document.getElementById('up-grid');
  if (!grid) return;

  /* v4.0 : bandeau de provenance. Remplace symboliquement la carte CGAN :
     la question "d'ou viennent les donnees ?" est desormais repondue
     explicitement au lieu d'etre masquee derriere un module generatif. */
  const banner = `
    <div class="up-banner">                                    <!-- ⚠ A VERIFIER -->
      <strong>Données réelles uniquement</strong> —
      Open-Meteo Air Quality (CAMS Europe) + ERA5 · 7 villes du gouvernorat de Gabès ·
      granularité horaire · 2024-01-01 → 2026-07-02 ·
      split chronologique 80/20 · <em>aucune donnée synthétique</em>.
    </div>`;

  grid.innerHTML = banner + items.map(([route, title, sub]) => `
    <a class="up-card" href="#/${route}">                       <!-- ⚠ A VERIFIER -->
      <h3 class="up-card-title">${title}</h3>
      <p class="up-card-sub">${sub}</p>
      <span class="up-card-link">Ouvrir →</span>
    </a>`).join('');                                            /* ⚠ A VERIFIER */
};