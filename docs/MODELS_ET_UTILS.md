# GABES-TATENAFAS v2.0 — Modèles & Utilitaires scientifiques

> Documentation scientifique de tous les modèles d'IA, techniques statistiques et
> utilitaires ajoutés au projet (surveillance de la qualité de l'air à Gabès).
> Chaque composant est **réel** (aucune valeur inventée) et **se dégrade proprement** :
> si une librairie lourde (TensorFlow, PyTorch, SHAP…) est absente, le module est
> ignoré ou remplacé par un repli, sans jamais casser le pipeline ni afficher de faux chiffres.
>
> Emplacement du code : `models/*.py` et `backend/api/*.php`.

---

## Table des matières

1. [Explicabilité (XAI)](#1-explicabilité-xai)
2. [Génération / augmentation de données](#2-génération--augmentation-de-données)
3. [Modèles Machine Learning classiques](#3-modèles-machine-learning-classiques)
4. [Modèles Deep Learning](#4-modèles-deep-learning)
5. [Logique floue](#5-logique-floue)
6. [Ensemble & correction](#6-ensemble--correction)
7. [Incertitude & fiabilité](#7-incertitude--fiabilité)
8. [Détection (anomalies & drift)](#8-détection-anomalies--drift)
9. [Analyse statistique & causale](#9-analyse-statistique--causale)
10. [Apprentissage distribué](#10-apprentissage-distribué)
11. [MLOps (versioning, A/B, auto-optim)](#11-mlops-versioning-ab-auto-optimisation)
12. [Simulation & physique](#12-simulation--physique)
13. [Santé publique](#13-santé-publique)
14. [Infrastructure & données](#14-infrastructure--données)

---

## 1. Explicabilité (XAI)

L'explicabilité répond à la question : **« pourquoi le modèle a-t-il prédit ça ? »**.
Indispensable pour un système d'alerte santé publique (une autorité ne peut pas agir
sur une « boîte noire »).

### 1.1 SHAP — principe commun (Shapley Additive exPlanations)

SHAP attribue à **chaque variable** (SO₂, PM2.5, vent…) une **contribution chiffrée**
à une prédiction donnée. La base théorique vient de la **théorie des jeux coopératifs**
(valeur de Shapley, 1953) : on considère chaque variable comme un « joueur » et on
mesure sa contribution marginale moyenne au résultat, sur toutes les combinaisons
possibles de variables. Propriété clé : **additivité** — la somme des contributions +
la valeur de base = la prédiction exacte. C'est l'explication la plus rigoureuse
mathématiquement.

> Référence : Lundberg & Lee (2017), *A Unified Approach to Interpreting Model Predictions*, NeurIPS.

### 1.2 TreeSHAP (pour les modèles à base d'arbres)

- **Pour quoi :** Random Forest, XGBoost (nos modèles ML principaux).
- **Code :** `models/ml_models.py` → `shap_values()` = `shap.TreeExplainer(model).shap_values(X)`.
- **Science :** calcul **exact** des valeurs de Shapley en temps **polynomial** en
  exploitant la structure d'arbre (au lieu du temps exponentiel du SHAP naïf). On
  parcourt les chemins de décision de l'arbre et on pondère par la proportion
  d'échantillons de chaque feuille. → rapide et exact pour les arbres.
- **Sortie projet :** importance globale (barres) + explication locale par prédiction.

### 1.3 DeepSHAP (pour les réseaux profonds)

- **Pour quoi :** BiLSTM / BiLSTM+Attention (`models/dl_models.py`, `deep_models.py`).
- **Science :** TreeSHAP ne fonctionne **pas** sur un réseau de neurones (pas d'arbres).
  DeepSHAP est une **approximation** des valeurs de Shapley adaptée aux réseaux profonds :
  il combine SHAP avec **DeepLIFT** (Shrikumar 2017), qui propage les contributions
  **couche par couche** depuis la sortie vers l'entrée par rapport à une **référence**
  (baseline, ex. moyenne des entrées). Chaque neurone reçoit une part du « crédit »
  via la règle de chaînage DeepLIFT. → explicabilité des modèles temporels profonds.
- **Différence TreeSHAP vs DeepSHAP :**

| Critère | **TreeSHAP** | **DeepSHAP** |
|---|---|---|
| Modèle cible | Arbres (RF, XGBoost) | Réseaux profonds (BiLSTM, CNN…) |
| Calcul | **Exact**, polynomial | **Approximé** (via DeepLIFT) |
| Backend | Structure d'arbre | Rétro-propagation couche par couche |
| Référence (baseline) | Non requise | Requise (échantillon de fond) |
| Vitesse | Très rapide | Plus lourd (dépend du réseau) |

### 1.4 SHAP Interaction Values

- **Code :** `models/ml_models.py` → `shap_interaction()` / `store_shap_interactions()`.
- **Science :** décompose l'effet d'une paire de variables en **effet principal** +
  **effet d'interaction**. Exemple concret Gabès : un pic de **SO₂ par temps humide**
  n'a pas le même impact qu'un pic de SO₂ par temps sec — l'interaction le capture.
- **Dépendance :** `shap` (sinon ignoré proprement).

### 1.5 LIME (Local Interpretable Model-agnostic Explanations)

- **Code :** `models/ml_models.py` → `lime_explain()`.
- **Science :** explication **locale** et **agnostique** au modèle. On perturbe
  légèrement l'instance à expliquer, on observe les prédictions, et on ajuste un
  **modèle linéaire simple** localement autour du point. Les poids de ce modèle
  linéaire = l'explication. Complémentaire de SHAP : plus rapide, mais approximation
  locale (pas de garantie d'additivité globale).
- **Référence :** Ribeiro et al. (2016), *"Why Should I Trust You?"*, KDD.

### 1.6 Counterfactual / DiCE (Explications contrefactuelles)

- **Code :** `models/xai_counterfactual.py`.
- **Science :** répond à **« que faudrait-il changer pour changer le résultat ? »**.
  Ex : *« si le SO₂ avait été 20 % plus bas, l'AQI serait passé de CRITICAL à WARNING »*.
  Rend les recommandations **actionnables** (QUOI changer), là où SHAP/LIME disent
  seulement QUOI a causé. Utilise la librairie `dice-ml` si dispo, sinon un repli
  « recherche 1D » (on réduit chaque variable par paliers jusqu'à changer de classe).
- **Référence :** Mothilal, Sharma & Tan (2020), *DiCE*, FAT*.

---

## 2. Génération / augmentation de données

Problème réel : chaque zone n'a que ~150 points horaires réels → trop peu pour du DL
solide. On **augmente** la base avec des données synthétiques réalistes.

### 2.1 AE-CGAN (Autoencoder + Conditional GAN) — *modèle principal actuel*

- **Code :** `models/augment_db.py` (`augment_ae_cgan`, `augment_api_ae_cgan`).
- **Science :** combine deux idées.
  1. **Autoencoder** : un encodeur compresse la séquence réelle 24 h en un espace
     latent réduit, un décodeur la reconstruit. Il apprend la **structure interne**
     (corrélations pollution ↔ AQI ↔ météo).
  2. **CGAN (GAN conditionnel)** : un **générateur** produit des séquences synthétiques
     à partir d'un bruit aléatoire **conditionné** par un contexte (heure cyclique,
     saison, zone industrielle/côtière) ; un **discriminateur** apprend à distinguer
     vrai/faux. Les deux s'affrontent (jeu min-max de Goodfellow 2014) jusqu'à ce que
     le générateur produise des séquences indiscernables du réel.
- **Multivarié :** version `ae-cgan-mv` génère AQI **+** PM2.5/PM10/SO₂/NO₂/O₃
  conjointement (préserve les corrélations entre polluants).
- **Versions écrites en base :** `ae-cgan-v1` (univarié), `ae-cgan-mv-v1` (multivarié),
  fidélité 0.90.
- **Référence :** Goodfellow (2014) ; Mirza & Osindero (2014) *Conditional GAN* ;
  Yoon (2019) *TimeGAN*.

### 2.2 CGAN (cgan_trainer.py)

- **Code :** `models/cgan_trainer.py`. Générateur/discriminateur Keras, séquences 24 h
  conditionnées par un vecteur contexte 12-dim.
- **Science :** GAN conditionnel « pur » (sans autoencoder). Utilisé quand TensorFlow
  est présent. Version `cgan-v2`, fidélité 0.88.

### 2.3 Repli statistique (sans TensorFlow)

- **Science :** si aucun GAN dispo, on génère par **jitter** (bruit gaussien léger),
  **magnitude-warping** (déformation d'amplitude) et **bootstrap** — techniques
  classiques d'augmentation de séries temporelles qui **préservent la distribution**
  réelle. Étiqueté honnêtement (`stat-v1`, `stat-small-v1`) pour ne jamais faire passer
  du statistique pour du neuronal.

---

## 3. Modèles Machine Learning classiques

**Code :** `models/ml_models.py`. Vecteur de 27 features partagé (lags AQI, fuzzy
Type-2, polluants, météo, temps). Se dégrade si `xgboost`/`optuna` absents.

### 3.1 AR(7) — baseline autorégressive
- **Science :** modèle statistique de référence : l'AQI à t est une combinaison
  linéaire des 7 heures précédentes. Sert de **point de comparaison** obligatoire
  pour prouver que les modèles avancés font vraiment mieux.

### 3.2 Random Forest
- **Science :** ensemble d'arbres de décision entraînés sur des sous-échantillons
  aléatoires (bagging). Robuste, peu de surapprentissage, importance de variables native.
- **Référence :** Breiman (2001).

### 3.3 XGBoost (+ Optuna)
- **Science :** *gradient boosting* — arbres construits séquentiellement, chacun
  corrigeant les erreurs du précédent. **Optuna** optimise les hyperparamètres par
  recherche bayésienne (TPE). Généralement le meilleur modèle tabulaire.
- **Référence :** Chen & Guestrin (2016), *XGBoost*, KDD.

### 3.4 Validation & métriques
- **Walk-forward / TimeSeriesSplit** : validation croisée **temporelle** (jamais de
  fuite du futur vers le passé).
- **Métriques :** MAE, RMSE, MAPE, SMAPE, R² (régression) ; F1, Accuracy, ROC-AUC
  (classification SAFE/WARNING/CRITICAL/HAZARDOUS).

---

## 4. Modèles Deep Learning

> Tous **optionnels** (TensorFlow / PyTorch). `available()` renvoie False si absent →
> le modèle est **sauté**, jamais affiché en faux.

> **STATUT RÉEL dans le projet (vérifié dans le code) :**
>
> | Modèle | Fichier | Statut réel |
> |---|---|---|
> | **BiLSTM + Attention** | `deep_models.py`, `dl_models.py` | ✅ Entraîné (si TensorFlow installé) |
> | **CNN** (Conv1D front-end) | `deep_models.py` L128, `cgan_trainer.py` L165 | ✅ Réel, **intégré dans le BiLSTM** (CNN-BiLSTM-Attention) et le discriminateur CGAN |
> | **GNN spatial** | `gnn_spatial.py` | ✅ **Actif** : lancé par `train_all.py`, écrit `gnn_spatial_edges`, affiché sur la page Spatial/Carte |
> | **PINN** (panache) | `pinn_dispersion.py` | ✅ Actif comme **module physique** (utilisé par le Digital Twin), pas un réseau entraîné |
> | **TFT** | `tft_forecaster.py` | ⚠️ Fichier présent mais **NON entraîné par défaut** : nécessite `pip install pytorch-forecasting lightning` + appel manuel de `train_tft()`. Sinon il est simplement ignoré (référencé seulement comme adversaire A/B). |

### 4.1 BiLSTM (`dl_models.py`, `deep_models.py`)
- **Science :** LSTM **bidirectionnel** — lit la séquence dans les deux sens (passé→futur
  et futur→passé) pour capter le contexte temporel complet. Adapté aux séries horaires.
- **Référence :** Schuster & Paliwal (1997) ; Hochreiter & Schmidhuber (1997).

### 4.2 BiLSTM + Multi-Head Attention
- **Science :** ajoute un mécanisme d'**attention** (Transformer) : le modèle apprend
  **quelles heures passées** sont les plus importantes pour prédire. Les poids
  d'attention sont extraits pour la **heatmap** interprétable.
- **Référence :** Vaswani et al. (2017), *Attention Is All You Need*, NeurIPS.

### 4.3 TFT — Temporal Fusion Transformer (`tft_forecaster.py`)
- **Science :** architecture Transformer spécialisée séries temporelles multi-horizon
  (+1h/+6h/+24h) avec **attention interprétable native** et sélection de variables.
  Complète le BiLSTM (ne l'écrase pas). Repli propre si `pytorch-forecasting` absent.
- **⚠️ Statut honnête :** ce modèle n'est PAS entraîné automatiquement par `train_all.py`. Pour l'activer : `pip install pytorch-forecasting lightning` puis appeler `train_tft()`. Sans cela, aucune ligne TFT n'est produite (c'est une extension optionnelle, pas un résultat affiché par défaut).
- **Référence :** Lim et al. (2021), *TFT*, Int. J. Forecasting.

### 4.4 PINN — Physics-Informed Neural Network (`pinn_dispersion.py`)
- **Science :** injecte une **équation physique** (panache gaussien de dispersion
  atmosphérique, coefficients Pasquill-Gifford) directement dans la **fonction de perte**.
  Le modèle est ainsi ancré dans la physique réelle de Gabès (complexe chimique au NE,
  vent dominant) au lieu d'être purement data-driven. Les helpers physiques tournent en
  NumPy pur ; l'entraînement torch est sauté si torch absent.
- **Référence :** Raissi, Perdikaris & Karniadakis (2019), *PINNs*, J. Comp. Physics.

### 4.5 GNN spatial — Graph Neural Network (`gnn_spatial.py`)
- **Science :** modélise les 6 zones réelles comme un **graphe** (nœuds = zones,
  arêtes pondérées par vent + proximité GPS haversine) pour capter la **propagation**
  de pollution d'une zone à l'autre. Repli sur graphe géométrique NumPy si
  `torch_geometric` absent (les arêtes restent calculées).
- **Référence :** Kipf & Welling (2017), *GCN*, ICLR.

### 4.6 CNN — couche convolutive (front-end) *(intégrée, pas un fichier séparé)*
- **Où :** `deep_models.py` (L128 : `Conv1D(32,3,padding="causal")`) et `cgan_trainer.py` (L165, discriminateur).
- **Science :** une couche **convolutive 1D causale** balaye la séquence horaire pour extraire les **motifs locaux court-terme** (ex : une montée brutale de SO₂ sur 2-3 h) AVANT de les passer au BiLSTM. C'est l'architecture état-de-l'art **CNN-BiLSTM-Attention** (littérature 2024-2025 sur la prévision d'AQI). Le "causal" garantit qu'on ne regarde jamais le futur.
- **Statut :** ✅ Réel et actif dès que TensorFlow est installé (fait partie du modèle profond principal). Il n'y a **pas** de fichier `cnn.py` séparé — le CNN est une **couche** à l'intérieur du réseau profond.

---

## 5. Logique floue

### 5.1 Fuzzy Type-2 par intervalles (`fuzzy_type2.py`)
- **Science :** logique floue de **Type-2** (Mamdani → Type-2) avec réduction de type
  **Karnik-Mendel**. Là où le flou Type-1 donne une seule appartenance, le Type-2
  gère l'**incertitude sur l'incertitude** (bande d'appartenance UMF/LMF) — utile
  quand les capteurs eux-mêmes sont incertains. Produit `fuzzy_score_type2`, une
  **feature clé** injectée dans tous les modèles ML/DL, + sa bande d'incertitude.
- **Référence :** Mendel (2017), *Type-2 Fuzzy Systems*, Springer.

---

## 6. Ensemble & correction

### 6.1 Ensemble adaptatif (`ensemble.py`)
- **Science :** combine plusieurs modèles avec des **poids softmax dynamiques** basés
  sur R², F1, RMSE et latence. Les modèles à score composite **négatif** (pires que la
  référence) sont **exclus** puis renormalisation → vote plus défendable.
- **Référence :** Dietterich (2000) ; Gal & Ghahramani (2016) (incertitude).

### 6.2 Correction résiduelle (`residual_model.py`)
- **Science :** un GradientBoosting léger apprend les **erreurs** de l'ensemble et les
  ajoute en correction (inspiré des *residual connections*). Réduit le biais restant.
- **Référence :** He et al. (2016), *ResNet*, CVPR.

### 6.3 RL — Bandit contextuel LinUCB (`rl_ensemble_agent.py`)
- **Science :** remplace les poids **statiques** par un **bandit contextuel LinUCB** qui
  apprend les meilleurs poids **selon le contexte** (zone, heure, saison, drift).
  Contexte → action (poids), récompense = −RMSE. LinUCB (plutôt qu'un DQN complet) car
  seulement ~4 modèles à pondérer → plus simple à justifier. Repli poids uniformes.
- **Référence :** Li et al. (2010), *LinUCB*, WWW.

---

## 7. Incertitude & fiabilité

### 7.1 Conformal Prediction (`conformal_predictor.py`)
- **Science :** **split conformal** — donne des intervalles de prédiction avec une
  **garantie statistique de couverture** (ex. 90 % des vraies valeurs tombent dans
  l'intervalle), calibrée sur un hold-out de 20 %, avec la correction (n+1)(1−α)/n.
  Contrairement aux bornes heuristiques, la garantie est **prouvée** (distribution-free).
- **Référence :** Vovk et al. (2005) ; Angelopoulos & Bates (2021).

### 7.2 Calibration + Brier score (`calibration_eval.py`)
- **Science :** un modèle peut être **précis mais mal calibré** (dire « 90 % critique »
  alors que ce n'est vrai que 60 % du temps) — dangereux pour une alerte santé. On
  mesure la **courbe de fiabilité** et le **score de Brier** (erreur quadratique moyenne
  des probabilités). Écrit dans `calibration_metrics`.
- **Référence :** Brier (1950) ; Guo et al. (2017), *On Calibration of NN*, ICML.

---

## 8. Détection (anomalies & drift)

### 8.1 Détection d'anomalies (`anomaly_detector.py`)
- **Science :** double approche — **Autoencoder** (erreur de reconstruction élevée =
  anomalie, entraîné sur jours normaux uniquement) + **Isolation Forest** (isole les
  points rares par partitionnement aléatoire). Classifie ensuite le **type** d'anomalie
  (tempête de sable, événement chimique multi-polluants, pic industriel SO₂…), du plus
  spécifique au moins spécifique pour éviter que tout soit classé « pic industriel ».
- **Référence :** Liu et al. (2008), *Isolation Forest* ; Goodfellow et al. (2016).

### 8.2 Détection de drift (`drift_detector.py`)
- **Science :** détecte le **concept drift** (la distribution des données change dans le
  temps → le modèle devient obsolète) via la **divergence KL** entre la fenêtre récente
  et la baseline. Déclenche un ré-entraînement si drift ≥ 0.5.
- **Référence :** Gama et al. (2014), ACM Computing Surveys.

---

## 9. Analyse statistique & causale

### 9.1 Causalité de Granger (`granger_causality.py`)
- **Science :** teste si une variable **aide à prédire** une autre dans le futur (ex :
  SO₂ → PM2.5, vent → AQI, pic industriel → SO₂) sur plusieurs lags (1..24 h). Ce n'est
  pas une causalité physique stricte mais une **précédence prédictive** statistique.
- **Référence :** Granger (1969), *Econometrica*.

### 9.2 Tests de significativité (`statistical_tests.py`)
- **Science :** **Wilcoxon signed-rank** (meilleur modèle vs chaque autre, apparié) et
  **Friedman** (comparaison globale de tous les modèles). Prouvent que les écarts de
  performance sont **statistiquement significatifs** (p < 0.05), pas dus au hasard.
- **Référence :** Wilcoxon (1945) ; Demšar (2006), JMLR.

### 9.3 Étude d'ablation (`ablation_study.py`)
- **Science :** 9 expériences **cumulatives** (XGBoost seul → + Fuzzy → + BiLSTM → +
  Attention → + CGAN → + Ensemble → + Résiduel → + Autoencoder → système complet)
  prouvant que **chaque composant apporte ≥ 3 %** d'amélioration (exigence type Springer).

---

## 10. Apprentissage distribué

### 10.1 Federated Learning — FedAvg (`federated_learning.py`)
- **Science :** chaque ville entraîne un modèle **localement** et ne partage que les
  **poids** (jamais les données brutes → confidentialité). Le serveur les agrège par
  **moyenne pondérée** par le nombre d'échantillons (FedAvg).
- **Référence :** McMahan et al. (2017), AISTATS.

---

## 11. MLOps (versioning, A/B, auto-optimisation)

### 11.1 Model Registry / Versioning (`model_registry_manager.py`)
- **Rôle :** traçabilité complète des versions de modèles (table `model_versions`,
  sans serveur MLflow séparé, cohérent avec WAMP local). Reproductibilité scientifique :
  savoir quelle version a produit quel résultat.

### 11.2 A/B Testing automatique (`ab_testing_controller.py`)
- **Science :** teste deux modèles en production ; **promotion automatique** du
  challenger si drift détecté sur le modèle actif **ET** meilleur RMSE sur 7 jours
  glissants **ET** amélioration ≥ 3 %.

### 11.3 Boucle d'auto-optimisation (`auto_optimizer.py`)
- **Rôle :** toutes les 24 h — vérification de drift, **élagage de features** basé SHAP,
  ingénierie d'interactions, re-tuning Optuna, ré-entraînement *keep-if-better*, refresh
  des poids d'ensemble, ré-entraînement résiduel.

---

## 12. Simulation & physique

### 12.1 Digital Twin (`digital_twin.py`)
- **Science :** **jumeau numérique** — simule l'impact de scénarios *« et si »* sur
  l'AQI futur (ex : fermeture de l'usine de Ghannouche → réduction source 80 %, pic de
  vent). Combine le CGAN + le PINN (panache gaussien). Permet aux autorités de tester
  des décisions avant de les prendre. Écrit dans `digital_twin_scenarios`.

### 12.2 Simulateur de données (`data_simulator.py`)
- **Rôle :** génère 8760 lignes/ville (1 an horaire) comme **repli** quand les API live
  sont indisponibles, avec le facteur de pollution de chaque ville et les patterns
  saisonniers/industriels spécifiques à Gabès.

---

## 13. Santé publique

### 13.1 Indice d'impact santé (`health_impact.py`)
- **Science :** indice spécifique Gabès (exposition **chronique au SO₂**) combinant
  AQI, PM2.5, SO₂, % population vulnérable et heures d'exposition → score 0-100 et
  niveau (Négligeable → Critique) avec recommandations concrètes (masque FFP2,
  évacuation zones industrielles…).

---

## 14. Infrastructure & données

| Fichier | Rôle |
|---|---|
| `data_loader.py` | Charge la **vraie** série horaire multivariée depuis `api_readings` (AQI + polluants + météo), interpolée sur grille horaire, densifiée en préservant les corrélations. Repli synthétique corrélé pour les zones sans lectures. |
| `db_config.py` | Connexion MySQL (WAMP : 127.0.0.1 / root / sans mot de passe), surchargeable par variables d'environnement. `try_connection()` pour repli gracieux. |
| `train_all.py` | **Pipeline maître** : charge le réel → 27 features → AR(7)/RF/XGBoost(+Optuna)/BiLSTM → évalue MAE/RMSE/MAPE/SMAPE/R²/F1 + Wilcoxon → sauve `saved/*.pkl`/`.h5` → écrit `model_performance`, `model_predictions`, `fuzzy_assessments`, `health_impact`. Lancer : `python -m models.train_all`. |
| `api_server.py` | API REST **Flask** (port 5000) : `/predict`, `/evaluate`, `/granger`, `/attention_heatmap`, `/optuna_results`. Charge les modèles de `saved/`, repli analytique sinon. Appelée par PHP via cURL. |
| `websocket_server.py` | Serveur **WebSocket** temps réel (Flask-SocketIO, port 5001) : pousse les mises à jour AQI au dashboard toutes les 5 min. |
| `multi_horizon_eval.py` | Évaluation multi-horizon (+1h/+6h/+24h) pour chaque modèle. |

---

## Résumé — dépendances optionnelles

| Librairie | Active… | Si absente |
|---|---|---|
| `tensorflow` | BiLSTM, Attention, Autoencoder, CGAN | modèles DL sautés (jamais faux) |
| `torch` / `torch_geometric` / `pytorch-forecasting` | PINN, GNN, TFT | repli NumPy / sauté |
| `xgboost`, `optuna` | XGBoost tuné | RF seul |
| `shap`, `lime`, `dice-ml` | XAI complet | explications sautées |
| `statsmodels`, `scipy` | Granger, Wilcoxon/Friedman, KL | NaN honnête / repli |
| `mysql-connector-python` | écriture en base | calcul en mémoire seulement |

> **Principe directeur du projet : honnêteté scientifique.** Aucun module n'affiche de
> valeur inventée. Tout résultat provient soit d'un vrai calcul, soit d'un repli
> clairement étiqueté (`stat-*`), soit est simplement absent.
