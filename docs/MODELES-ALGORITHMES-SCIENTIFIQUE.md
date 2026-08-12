# Modeles & Algorithmes — Documentation scientifique detaillee

> Projet : prediction de la qualite de l'air (AQI) et des polluants a Gabes (Tunisie).
> Ce document explique **scientifiquement** chaque modele, chaque metrique, et les
> methodes d'**hybridation** (combinaison de plusieurs modeles). Les explications sont
> d'abord **generales** (theorie de l'etat de l'art) puis reliees a leur usage dans la prediction.

---

## Table des matieres
1. Le probleme scientifique : prediction de series temporelles
2. Notions fondamentales (fenetrage, normalisation, sur-apprentissage)
3. Modeles de Machine Learning classiques
   - 3.1 Regression lineaire & AR(p) (autoregressif)
   - 3.2 Random Forest
   - 3.3 XGBoost (Gradient Boosting)
   - 3.4 MLP (Perceptron multicouche)
4. Modeles de Deep Learning
   - 4.1 RNN & LSTM
   - 4.2 BiLSTM
   - 4.3 CNN / Conv1D
   - 4.4 Mecanisme d'attention & Multi-Head Attention
   - 4.5 Architecture CNN + BiLSTM + Attention
5. Logique floue de Type-2 (Fuzzy Type-2)
6. Modeles generatifs
   - 6.1 Autoencodeur (AE)
   - 6.2 GAN & CGAN
   - 6.3 AE-CGAN (le notre)
7. Explicabilite (XAI) : SHAP, Deep-SHAP, LIME
8. Apprentissage federe (Federated Learning)
9. **Hybridation : comment combiner 2, 3 modeles ou plus**
   - 9.1 Principes generaux (bagging, boosting, stacking, voting, ponderation)
   - 9.2 XGBoost + Fuzzy
   - 9.3 BiLSTM + Attention
   - 9.4 Ensemble dynamique (le notre)
10. Metriques d'evaluation (RMSE, MAE, MAPE, sMAPE, R2, Accuracy, Precision, Recall, F1, AUC-ROC, latence, coverage, Frechet)
11. Pipeline complet de prediction

---

## 1. Le probleme scientifique : prediction de series temporelles

Une **serie temporelle** est une suite de mesures ordonnees dans le temps :
`x_1, x_2, ..., x_t`. Ici, `x_t` = AQI (et polluants : PM2.5, PM10, NO2, SO2, O3, CO) a l'instant `t`.

Le **but** : predire la valeur future `x_{t+h}` (horizon `h` = +1h, +6h, +24h) a partir
du passe recent. Formellement, on cherche une fonction `f` telle que :

```
x_{t+h} ~= f(x_t, x_{t-1}, ..., x_{t-k+1} ; variables exogenes)
```

- `k` = taille de la fenetre d'historique (ici 24 heures).
- variables exogenes = meteo (temperature, humidite, vent), contexte (zone industrielle/cotiere), heure (cyclique).

Deux formulations coexistent :
- **Regression** : predire une valeur continue (AQI = 87.3).
- **Classification** : predire une categorie (Bon / Modere / Mauvais...).

---

## 2. Notions fondamentales

### 2.1 Fenetrage glissant (sliding window)
On transforme la serie en couples (entree, sortie) :

```
X_i = [x_i, ..., x_{i+23}]   (24 pas)   ->   y_i = x_{i+24+h}
```

Chaque fenetre de 24h devient un exemple d'apprentissage. C'est la base de tous les
modeles sequentiels (LSTM, CNN...).

### 2.2 Normalisation / Standardisation
Les reseaux de neurones apprennent mal si les variables ont des echelles differentes
(AQI ~ 0-300, CO ~ 0-2). On applique :
- **Standardisation (z-score)** : `z = (x - mu) / sigma` (moyenne 0, ecart-type 1).
- **Min-Max** : `x' = (x - min) / (max - min)` dans [0,1], ou [-1,1] pour les sorties `tanh`.

On **memorise** `mu, sigma` (ou min/max) du jeu d'entrainement pour appliquer la meme
transformation en prediction, puis on **denormalise** la sortie.

### 2.3 Sur-apprentissage (overfitting) & regularisation
Un modele **sur-apprend** quand il memorise le bruit du jeu d'entrainement et generalise mal.
Parades scientifiques :
- **Dropout** : desactive aleatoirement des neurones a l'entrainement.
- **Regularisation L1/L2** : penalise les poids trop grands (`+ lambda * ||w||`).
- **Early stopping** : arret quand l'erreur de validation remonte.
- **Batch Normalization** : stabilise et accelere l'apprentissage.

### 2.4 Découpage train / validation / test
On separe les donnees dans le **temps** (jamais aleatoirement pour une serie temporelle) :
passe -> entrainement, futur -> test. Cela evite la "fuite du futur" (data leakage).

---

## 3. Modeles de Machine Learning classiques

### 3.1 Regression lineaire & AR(p) — autoregressif
Le modele **autoregressif d'ordre p**, note AR(p), predit la valeur courante comme une
combinaison lineaire des `p` valeurs precedentes :

```
x_t = c + phi_1 * x_{t-1} + phi_2 * x_{t-2} + ... + phi_p * x_{t-p} + e_t
```

- `phi_i` = coefficients appris (moindres carres).
- `e_t` = bruit blanc (erreur).
- Dans le projet : **AR(7)** (7 dernieres heures). Simple, rapide, sert de **baseline**
  (reference) pour juger si les modeles complexes apportent vraiment un gain.

### 3.2 Random Forest (foret aleatoire)
Ensemble d'**arbres de decision**. Un arbre partitionne l'espace des variables par des
regles "si feature < seuil". Random Forest :
1. Cree N arbres, chacun entraine sur un **echantillon bootstrap** (tirage avec remise).
2. A chaque noeud, ne teste qu'un **sous-ensemble aleatoire** de variables (decorrelation).
3. **Prediction = moyenne** des arbres (regression) ou vote majoritaire (classification).

Avantage : robuste, peu de reglages, donne l'**importance des variables**. C'est du **bagging**
(voir section 9.1).

### 3.3 XGBoost — Extreme Gradient Boosting (detaille)
XGBoost est un **gradient boosting** : il construit les arbres **sequentiellement**, chaque
nouvel arbre corrigeant les **erreurs residuelles** des precedents.

Modele additif : la prediction apres `T` arbres est

```
y_hat_i = sum_{t=1..T} f_t(x_i),   f_t appartient a l'espace des arbres
```

A l'etape `t`, on ajoute l'arbre `f_t` qui minimise l'**objectif regularise** :

```
L^(t) = sum_i  l( y_i , y_hat_i^(t-1) + f_t(x_i) )  +  Omega(f_t)
Omega(f) = gamma * T_feuilles + (1/2) * lambda * sum_j w_j^2
```

- `l` = fonction de perte (ex : erreur quadratique pour la regression).
- `Omega` = penalise la complexite (nombre de feuilles `T_feuilles`, poids `w_j`).

XGBoost fait un **developpement de Taylor du 2e ordre** de la perte :

```
L^(t) ~= sum_i [ g_i * f_t(x_i) + (1/2) * h_i * f_t(x_i)^2 ] + Omega(f_t)
g_i = derivee 1ere de la perte,  h_i = derivee 2nde (hessienne)
```

Le **poids optimal** d'une feuille `j` (ensemble d'exemples `I_j`) est alors :

```
w_j* = - ( sum_{i in I_j} g_i ) / ( sum_{i in I_j} h_i + lambda )
```

et le **gain** d'une coupure (split) qui separe un noeud en gauche (L) / droite (R) :

```
Gain = (1/2) * [ G_L^2/(H_L+lambda) + G_R^2/(H_R+lambda) - (G_L+G_R)^2/(H_L+H_R+lambda) ] - gamma
```

On garde la coupure si `Gain > 0`. **Hyperparametres cles** :
- `n_estimators` (nombre d'arbres), `max_depth` (profondeur),
- `learning_rate` (eta, retrecissement de chaque arbre),
- `subsample`, `colsample_bytree` (echantillonnage lignes/colonnes),
- `lambda` (L2), `gamma` (elagage), `min_child_weight`.

**Pourquoi c'est fort pour l'AQI** : capture des relations **non lineaires** et des
interactions (ex : "vent faible + zone industrielle + nuit -> pic de SO2") sans qu'on ait
a les specifier. Rapide, precis sur donnees tabulaires (features = lags + meteo + contexte).

### 3.4 MLP — Perceptron multicouche
Reseau de neurones "dense". Chaque couche calcule `a = activation(W*x + b)`. Empile
plusieurs couches non lineaires (ReLU) pour approximer des fonctions complexes
(theoreme d'approximation universelle). Entraine par **retropropagation** + descente de
gradient. Bon pour les features tabulaires, mais ignore l'ordre temporel (contrairement au LSTM).

---

## 4. Modeles de Deep Learning

### 4.1 RNN & LSTM
Un **RNN** (reseau recurrent) traite la sequence pas a pas en maintenant un **etat cache**
`h_t = activation(W_x x_t + W_h h_{t-1} + b)`. Probleme : **gradient qui disparait**
(vanishing gradient) -> incapable de retenir le long terme.

Le **LSTM** (Long Short-Term Memory) resout cela avec une **cellule memoire** `C_t` et
trois **portes** (gates) qui controlent le flux d'information :

```
f_t = sigma(W_f [h_{t-1}, x_t] + b_f)      # porte d'oubli (forget)
i_t = sigma(W_i [h_{t-1}, x_t] + b_i)      # porte d'entree (input)
C~_t = tanh(W_c [h_{t-1}, x_t] + b_c)      # candidat memoire
C_t = f_t * C_{t-1} + i_t * C~_t           # mise a jour memoire
o_t = sigma(W_o [h_{t-1}, x_t] + b_o)      # porte de sortie (output)
h_t = o_t * tanh(C_t)                       # nouvel etat cache
```

- `sigma` = sigmoide (valeurs 0-1 = "combien laisser passer").
- La porte d'oubli permet de **garder** ou **effacer** l'information sur de longues durees.
- Ideal pour les cycles jour/nuit de la pollution.

### 4.2 BiLSTM (LSTM bidirectionnel)
Deux LSTM lisent la sequence dans les **deux sens** (avant et arriere), puis on concatene :

```
h_t = [ h_t(avant) ; h_t(arriere) ]
```

Chaque instant beneficie du contexte **passe ET futur** de la fenetre (durant l'entrainement).
Plus riche pour capturer la forme complete d'un episode de pollution.

### 4.3 CNN / Conv1D (convolution 1D)
Une couche **Conv1D** fait glisser des **filtres** (noyaux) le long de la sequence pour
detecter des **motifs locaux** (montee brutale, pic, plateau) :

```
(y * w)_t = sum_{j=0..K-1} w_j * x_{t-j}      # convolution causale de noyau K
```

- `padding="causal"` : n'utilise que le passe (pas de fuite du futur).
- Extrait des **caracteristiques locales** rapidement, avant de les passer au BiLSTM.

### 4.4 Mecanisme d'attention & Multi-Head Attention
L'**attention** permet au modele de **ponderer** les instants les plus utiles a la prediction
(ex : la valeur d'il y a 24h est-elle plus importante que celle d'il y a 3h ?).

Formule de l'attention "scaled dot-product" (Vaswani et al., 2017) :

```
Attention(Q, K, V) = softmax( Q * K^T / sqrt(d_k) ) * V
```

- `Q` (Query), `K` (Key), `V` (Value) sont des projections lineaires des entrees.
- `Q*K^T` = scores de similarite entre positions ; `softmax` -> poids qui somment a 1.
- `sqrt(d_k)` = mise a l'echelle qui stabilise le gradient.
- Le resultat = moyenne **ponderee** des valeurs `V` selon la pertinence.

**Multi-Head Attention** : on fait `h` attentions en parallele (h "tetes"), chacune
apprenant un type de dependance different, puis on concatene :

```
MultiHead = Concat(tete_1, ..., tete_h) * W_O
tete_i = Attention(Q W_i^Q, K W_i^K, V W_i^V)
```

Dans le projet : **4 tetes**. Nouveaux parametres appris : les matrices de projection
`W_i^Q, W_i^K, W_i^V` (par tete) et `W_O` (fusion). Avantage : le modele "regarde"
plusieurs horizons temporels simultanement.

### 4.5 Architecture CNN + BiLSTM + Attention (modele DL du projet)
Enchainement (entree = fenetre 24h x 7 variables) :

```
Input (24, 7)
  -> Conv1D(32, noyau 3, causal) + BatchNorm     # motifs locaux
  -> BiLSTM(48, return_sequences=True)            # dependances long terme, 2 sens
  -> Multi-Head Attention(4 tetes)                # ponderation des instants cles
  -> BiLSTM(24)                                    # resume la sequence
  -> Dense(24) -> Dense(1)                         # AQI predit
```

Chaque brique apporte une capacite complementaire : **CNN** (local) + **BiLSTM** (temporel)
+ **Attention** (importance relative). C'est une architecture **hybride** (voir section 9).

---

## 5. Logique floue de Type-2 (Fuzzy Type-2)

La **logique floue** modelise l'incertitude par des degres d'appartenance dans [0,1]
(ex : un AQI de 95 est "un peu Modere, un peu Mauvais"). Un systeme d'inference floue a 4 etapes :
1. **Fuzzification** : transformer les valeurs nettes en degres d'appartenance (fonctions triangulaires/gaussiennes).
2. **Regles** : "SI PM2.5 est Eleve ET vent est Faible ALORS risque est Fort".
3. **Inference** : combiner les regles (min/max, ou produit).
4. **Defuzzification** : revenir a une valeur nette (centre de gravite).

**Type-2** : ici, les fonctions d'appartenance ont elles-memes une **incertitude** (une
"empreinte d'incertitude", FOU = Footprint of Uncertainty). Au lieu d'un seul degre, on a
un **intervalle** de degres. Cela gere mieux le **bruit des capteurs** et la variabilite
des sources API. Etape supplementaire : la **reduction de type** (type-reduction, ex.
algorithme de Karnik-Mendel) avant la defuzzification.

Interet : robustesse face aux donnees imprecises/contradictoires entre AccuWeather, WAQI, IQAir.

---

## 6. Modeles generatifs

### 6.1 Autoencodeur (AE)
Reseau qui apprend a **compresser puis reconstruire** ses entrees :

```
z = Encodeur(x)      # code latent compact (petite dimension)
x_hat = Decodeur(z)  # reconstruction
Perte = || x - x_hat ||^2   (erreur de reconstruction, MSE)
```

Le **code latent** `z` capture la "forme essentielle" d'une sequence 24h (cycle diurne,
pics). Utile pour : debruitage, detection d'anomalies, et **generation** (via le decodeur).

### 6.2 GAN & CGAN
Un **GAN** (Generative Adversarial Network) oppose deux reseaux :
- **Generateur G** : transforme un bruit aleatoire `z` en donnee synthetique.
- **Discriminateur D** : distingue le reel du faux.

Jeu **minimax** (Goodfellow, 2014) :

```
min_G max_D  E_x[log D(x)] + E_z[log(1 - D(G(z)))]
```

G s'ameliore jusqu'a "tromper" D -> il genere des donnees realistes.

Le **CGAN** (Conditional GAN, Mirza & Osindero, 2014) ajoute une **condition** `c`
(saison, heure, contexte industriel) en entree de G et D :

```
G(z, c) -> echantillon conditionne ;   D(x, c) -> reel/faux sachant c
```

On genere ainsi des sequences "pour telle zone, telle heure".

### 6.3 AE-CGAN (le notre) — combinaison AE + CGAN
Probleme : entrainer un GAN directement sur des sequences 24h multivariees avec **peu de
donnees reelles** est instable. Solution **AE-CGAN** :
1. Un **autoencodeur** apprend d'abord la forme reelle des sequences (encodeur -> `z`, decodeur).
2. On encode toutes les sequences reelles -> nuage de **codes latents** reels.
3. Un **CGAN travaille dans l'espace latent** (plus petit, plus stable) : G genere de
   nouveaux codes latents conditionnes ; D les compare aux codes reels.
4. Le **decodeur** transforme les nouveaux codes en sequences AQI synthetiques realistes.

Avantage scientifique : generer dans un espace **latent compact** stabilise l'apprentissage
et donne des donnees augmentees plus proches du reel -> meilleurs modeles (surtout LSTM/BiLSTM).
Garde-fou honnete : si une zone a trop peu de points reels (< 32), on n'entraine PAS un
reseau (non fiable) ; on bascule sur un generateur **statistique** clairement etiquete.

---

## 7. Explicabilite (XAI) : SHAP, Deep-SHAP, LIME

Un modele precis mais "boite noire" n'est pas acceptable pour une decision de sante publique.
La XAI (eXplainable AI) rend les predictions **interpretables**.

### 7.1 SHAP (SHapley Additive exPlanations)
Base sur les **valeurs de Shapley** de la theorie des jeux : on attribue a chaque variable
sa **contribution moyenne** a la prediction, sur toutes les coalitions possibles de variables :

```
phi_j = sum_{S subset F\{j}}  [ |S|! (|F|-|S|-1)! / |F|! ] * [ f(S U {j}) - f(S) ]
```

- `phi_j` = importance (signee) de la variable `j` : positive = pousse l'AQI a la hausse.
- Propriete d'**additivite** : `prediction = valeur_de_base + sum_j phi_j`.
- Graphiques : **beeswarm** (impact global de chaque variable), **waterfall** (decomposition
  d'une prediction individuelle).

### 7.2 Deep-SHAP
Variante de SHAP optimisee pour les **reseaux profonds** (combine SHAP avec DeepLIFT) :
elle propage les contributions couche par couche, ce qui est bien plus rapide que le calcul
exact des valeurs de Shapley (exponentiel). Utilisee pour expliquer le modele DL (CNN-BiLSTM-Attention).

### 7.3 LIME (Local Interpretable Model-agnostic Explanations)
Explique **une** prediction en approximant localement le modele complexe par un **modele
lineaire simple**, autour du point a expliquer (on perturbe l'entree, on observe la sortie,
on ajuste une regression ponderee par la proximite). "Model-agnostic" = marche pour n'importe quel modele.

---

## 8. Apprentissage federe (Federated Learning)

Principe : entrainer un modele **sans centraliser les donnees**. Chaque **zone/client**
entraine localement sur ses propres donnees, puis on **agrege seulement les poids** sur un
serveur central. Algorithme **FedAvg** (McMahan, 2017) :

```
Pour chaque round r = 1..R :
   1. Le serveur envoie les poids globaux w_r a chaque client k.
   2. Chaque client entraine localement -> w_r^k.
   3. Agregation ponderee par la taille des donnees n_k :
        w_{r+1} = sum_k ( n_k / n_total ) * w_r^k
```

- **Rounds** = nombre de cycles de synchronisation.
- **But dans le projet** : simuler que chaque zone de Gabes garde ses donnees localement
  (confidentialite) tout en profitant d'un modele commun. Metrique de suivi : F1 par round.

---

## 9. HYBRIDATION : combiner 2, 3 modeles ou plus

### 9.1 Principes generaux de l'ensemble learning
Combiner plusieurs modeles reduit l'erreur si leurs erreurs sont **decorrelees** (ils se
trompent sur des cas differents). Quatre grandes familles :

- **Bagging** (ex : Random Forest) : entrainer N modeles sur des echantillons bootstrap,
  **moyenner**. Reduit la **variance**.
- **Boosting** (ex : XGBoost) : modeles **sequentiels**, chacun corrige les erreurs du precedent.
  Reduit le **biais**.
- **Voting / Averaging** : moyenne (regression) ou vote (classification) de modeles differents.
  - Moyenne simple : `y = (1/M) sum_m y_m`.
  - **Moyenne ponderee** : `y = sum_m alpha_m * y_m`, avec `sum alpha_m = 1`. Les poids
    `alpha_m` sont donnes selon la performance (ex : inversement proportionnels au RMSE).
- **Stacking** (empilement) : un **meta-modele** apprend a combiner les sorties des modeles
  de base. Les predictions des modeles de niveau 0 deviennent les **features** du modele de niveau 1 :

```
Niveau 0 : y1 = M1(x), y2 = M2(x), y3 = M3(x)
Niveau 1 : y_final = MetaModele(y1, y2, y3)   # ex : regression, ou MLP
```

Les **nouveaux parametres** d'un stacking sont les poids/coefficients du meta-modele.

### 9.2 XGBoost + Fuzzy (hybridation du projet)
Deux facons scientifiques de les marier :

**(a) Fuzzy en pre-traitement (feature engineering flou)**
La logique floue produit des **variables floues** (degre d'appartenance a "PM2.5 eleve",
"vent faible", "risque industriel") qui sont **ajoutees aux features** de XGBoost :

```
features_XGB = [ lags AQI, meteo, ... , mu_PM25_eleve, mu_vent_faible, mu_risque_fort ]
```

Avantage : XGBoost recoit un savoir expert (les regles floues) en plus des donnees brutes.

**(b) Fusion des sorties (ponderee)**
XGBoost donne une prediction `y_xgb` ; le systeme flou donne un **indice de risque** `y_fuzzy`.
On combine :

```
y_final = alpha * y_xgb + (1 - alpha) * y_fuzzy
```

- `alpha` (nouveau parametre, dans [0,1]) regle la confiance relative. On le choisit par
  validation (celui qui minimise le RMSE), ou dynamiquement selon le contexte (ex : plus de
  poids au flou quand les capteurs sont incoherents).
- **Comment ca marche** : XGBoost capture les relations statistiques fines ; le flou apporte
  robustesse et interpretabilite dans les zones incertaines. Le melange lisse les erreurs.

### 9.3 BiLSTM + Attention (hybridation du projet)
Le BiLSTM produit une sequence d'etats caches `h_1..h_24`. Sans attention, on ne garde
souvent que le dernier etat `h_24` (perte d'information). **Avec attention**, on calcule
des poids `a_t` (sommant a 1) et un **vecteur de contexte** :

```
score_t = fonction(h_t)          # pertinence de l'instant t
a_t = softmax(score_t)           # poids normalises
contexte = sum_t a_t * h_t       # resume pondere de toute la sequence
y = Dense(contexte)              # prediction
```

- **Nouveaux parametres** : les poids de la couche d'attention (matrices Q/K/V ou vecteur
  de score) — appris conjointement avec le BiLSTM par retropropagation.
- **Comment ca marche** : l'attention indique **quels instants** de la fenetre 24h ont le
  plus pese (ex : le pic d'il y a 6h). Cela **augmente la precision** ET fournit une
  explication (carte d'attention). C'est une hybridation "en serie" (une brique nourrit l'autre).

### 9.4 Ensemble dynamique (le "FULL SYSTEM" du projet)
Le systeme final combine plusieurs modeles (XGBoost+Fuzzy, RF, MLP, BiLSTM+Attention...)
par une **moyenne ponderee dynamique** : les poids `alpha_m` sont **recalcules** selon la
performance recente de chaque modele (ex : un modele qui derape voit son poids baisser).
C'est un **voting pondere adaptatif** — il tend a etre au moins aussi bon que son meilleur
membre, avec plus de stabilite.

---

## 10. Metriques d'evaluation (detaillees et scientifiques)

Notations : `y_i` = valeur reelle, `y_hat_i` = valeur predite, `n` = nombre d'exemples.

### 10.1 Metriques de REGRESSION (predire une valeur d'AQI)

**MAE — Mean Absolute Error (erreur absolue moyenne)**
```
MAE = (1/n) * sum_i | y_i - y_hat_i |
```
Moyenne des ecarts en valeur absolue. Meme unite que l'AQI. Peu sensible aux valeurs extremes.

**RMSE — Root Mean Squared Error (racine de l'erreur quadratique moyenne)**
```
RMSE = sqrt( (1/n) * sum_i ( y_i - y_hat_i )^2 )
```
- Eleve au carre -> **penalise fortement les grosses erreurs** (utile : un gros ratage sur
  un pic de pollution est grave). Meme unite que l'AQI.
- **RMSE >= MAE** toujours. Un grand ecart entre les deux signale des erreurs tres variables.
- **Interpretation** : RMSE = 4.9 signifie qu'en moyenne (quadratique) on se trompe d'environ
  5 points d'AQI. Plus c'est **bas**, mieux c'est.

**MAPE — Mean Absolute Percentage Error (erreur en pourcentage)**
```
MAPE = (100/n) * sum_i | (y_i - y_hat_i) / y_i |
```
Erreur relative en %. Facile a interpreter, mais instable quand `y_i` proche de 0.

**sMAPE — symmetric MAPE**
```
sMAPE = (100/n) * sum_i  | y_i - y_hat_i | / ( (|y_i| + |y_hat_i|) / 2 )
```
Version symetrique, bornee, corrige le defaut du MAPE pres de 0.

**R2 — coefficient de determination**
```
R2 = 1 - ( sum_i (y_i - y_hat_i)^2 ) / ( sum_i (y_i - y_bar)^2 )
```
- `y_bar` = moyenne des reels. Mesure la **part de variance expliquee** par le modele.
- R2 = 1 -> parfait ; R2 = 0 -> aussi bon que predire la moyenne ; R2 < 0 -> pire que la moyenne.

### 10.2 Metriques de CLASSIFICATION (predire une categorie de qualite d'air)
Basees sur la **matrice de confusion** : VP (vrais positifs), VN, FP (faux positifs), FN.

**Accuracy (exactitude)** — souvent notee "accu"
```
Accuracy = (VP + VN) / (VP + VN + FP + FN)
```
Proportion de predictions correctes. **Piege** : trompeuse si les classes sont desequilibrees
(ex : 95% de jours "Bon" -> un modele bete qui dit toujours "Bon" a 95% d'accuracy mais
rate tous les pics dangereux). D'ou l'importance du F1.

**Precision**
```
Precision = VP / (VP + FP)
```
Parmi les alertes "Mauvais" emises, combien etaient justes ? (evite les fausses alertes).

**Recall (rappel / sensibilite)**
```
Recall = VP / (VP + FN)
```
Parmi les vrais episodes "Mauvais", combien ont ete detectes ? (evite les oublis dangereux).

**F1-score** — moyenne harmonique precision/rappel
```
F1 = 2 * (Precision * Recall) / (Precision + Recall)
```
- Equilibre precision et rappel. **Robuste au desequilibre des classes** -> metrique reine
  pour la sante (on veut a la fois peu de fausses alertes ET peu d'oublis).
- `macro-F1` = moyenne des F1 par classe (traite chaque classe a egalite).

**AUC-ROC — Area Under the ROC Curve**
La courbe ROC trace le **taux de vrais positifs** (recall) contre le **taux de faux positifs**
quand on fait varier le seuil de decision. L'**AUC** = aire sous cette courbe, dans [0,1] :
- 0.5 = hasard, 1.0 = parfait. Mesure la capacite a **classer** correctement, independamment du seuil.

### 10.3 Metriques SYSTEME

**Latence (lat)**
Temps de calcul d'une prediction (en millisecondes, ex : `avg_latency_ms`). Cruciale pour le
**temps reel** : un modele tres precis mais lent est inutilisable pour des alertes minute par minute.
On arbitre toujours **precision vs latence**.

### 10.4 Metriques pour les modeles GENERATIFS (GAN / AE-CGAN)

**Coverage score (couverture)**
Verifie que les donnees generees couvrent la meme plage de valeurs que le reel (ex : sur 10
intervalles d'AQID, meme occupation). Detecte le **mode collapse** (le GAN ne produit qu'un
seul type de sortie).

**Distance de Frechet (FID simplifiee)**
Compare les **distributions** reelle et generee via leurs moyennes/covariances :
```
FID = || mu_r - mu_g ||^2 + Tr( Sigma_r + Sigma_g - 2 (Sigma_r Sigma_g)^{1/2} )
```
Plus c'est **bas**, plus le synthetique ressemble au reel.

**Similarite de distribution / fidelite**
Score (0-1) mesurant a quel point les statistiques du synthetique collent au reel. On l'affiche
pour **prouver** que l'augmentation de donnees est utile et non trompeuse.

---

## 11. Pipeline complet de prediction (du capteur a l'alerte)

```
1. COLLECTE      : APIs (AccuWeather + WAQI + IQAir) -> fusion ponderee -> table api_readings
                   (import automatique toutes les 1 min).
2. AUGMENTATION  : AE-CGAN genere des sequences realistes -> api_readings_augmented
                   (comble le manque de donnees pour l'entrainement).
3. FEATURES      : fenetrage 24h + lags + meteo + contexte + heure cyclique + (variables floues).
4. ENTRAINEMENT  : XGBoost, RF, MLP, BiLSTM+Attention, CNN-BiLSTM-Attention... (train_all.py)
                   -> metriques stockees dans model_performance ; artefacts DL dans dl_artifacts.
5. FUSION        : ensemble dynamique (moyenne ponderee) -> prediction finale +1h/+6h/+24h.
6. EXPLICABILITE : SHAP / Deep-SHAP (beeswarm, waterfall) + carte d'attention.
7. EVALUATION    : RMSE / MAE / R2 (regression) ; Accuracy / F1 / AUC (classification) ; latence.
8. DECISION      : categorisation (Bon..Dangereux) + alertes.
```

**Objectif final** : anticiper les pics de pollution a Gabes de facon **fiable, reelle et
explicable**, pour proteger la sante publique — sans jamais presenter de donnees fausses
comme reelles.

---

## References (etat de l'art)
- Goodfellow et al. (2014), *Generative Adversarial Networks*.
- Mirza & Osindero (2014), *Conditional GANs*.
- Hochreiter & Schmidhuber (1997), *Long Short-Term Memory*.
- Vaswani et al. (2017), *Attention Is All You Need*.
- Chen & Guestrin (2016), *XGBoost: A Scalable Tree Boosting System*.
- Lundberg & Lee (2017), *A Unified Approach to Interpreting Model Predictions (SHAP)*.
- Ribeiro et al. (2016), *"Why Should I Trust You?" (LIME)*.
- McMahan et al. (2017), *Communication-Efficient Learning (FedAvg)*.
- Karnik & Mendel (2001), *Type-2 Fuzzy Logic Systems*.
