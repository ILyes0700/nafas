# PART 0 — Système de données de pollution multi-API (fusion intelligente)
 
Ce module fusionne **3 sources** de qualité de l'air en un seul indice **US AQI (0–500)**
pour chacune des **6 zones de Gabès** (positions inchangées sur la carte).
 
| Source | Poids | Rôle |
|--------|------|------|
| AccuWeather | **75 %** ⭐ | source principale (+ polluants, météo, prévision 12 h) |
| WAQI | 15 % | station de référence |
| IQAir | 10 % | complément |
 
## Algorithme (7 étapes) — `backend/lib/fusion.php`
1. **Fetch parallèle** des 3 API (`curl_multi`, timeout 5 s) + cache MySQL 60 min.
2. **Normalisation** vers US AQI (AccuWeather : index catégorie → AQI ; WAQI/IQAir déjà en AQI US).
3. **Score qualité** par source (pénalités : réponse > 3 s, donnée en cache > 30 min, valeur hors plage).
4. **Détection d'aberrations** : si une API s'écarte de la médiane de > 40 AQI → poids ×0.3, puis renormalisation.
5. **Fusion pondérée dynamique** (replis si une/deux API indisponibles).
6. **Facteur de ville** (rural 0.60 → industriel lourd 1.90) + **ajustements temporels** (heures de pointe, nuit, week-end).
7. **Catégorie + couleur** (Bon → Dangereux).
 
> C'est l'étape 6 (facteur de ville) qui garantit que **chaque zone affiche un AQI différent**.
 
## Fichiers ajoutés
- `backend/config/accuweather.php` — config AccuWeather (⚠️ **renseigner `ACCUWEATHER_API_KEY`**).
- `backend/config/cities.php` — 6 zones (id, FR/AR, lat/lng, ids API, `pollution_factor`).
- `backend/lib/fusion.php` — moteur de fusion + persistance + vecteur de features ML/DL/Fuzzy.
- `backend/api/api-data.php` — endpoint REST (GET liste/détail, POST refresh).
- `frontend/pages/api-data.html` + `frontend/scripts/pages/api-data.js` — page « Real-Time API Data ».
- `db/migrations/2026-06-09-multi-api-fusion.sql` — tables `api_readings`, `api_config`, `fuzzy_assessments`, `model_performance`.
 
## Fichiers modifiés
- `frontend/index.php`, `frontend/scripts/router.js` — route + entrée de menu « Real-Time API Data ».
- `frontend/scripts/pages/map.js` — chaque ville affiche son AQI fusionné (marqueur coloré/dimensionné par catégorie, popup détaillé, panneau latéral).
- `backend/lib/auth.php` — exposition du module aux rôles autorisés.
- `db/schema.sql` — mêmes 4 tables ajoutées pour une installation neuve.
 
## Déploiement (WAMP)
1. Extraire l'archive par-dessus le projet existant.
2. Appliquer la migration :
   ```
   mysql -u root gabes_tatenafas < db/migrations/2026-06-09-multi-api-fusion.sql
   ```
   (Une base neuve via `db/schema.sql` contient déjà ces tables.)
3. Renseigner la clé AccuWeather dans `backend/config/accuweather.php`
   (`ACCUWEATHER_API_KEY`). WAQI et IQAir sont déjà configurées.
   > Sans clé/connexion, un repli **synthétique déterministe** par ville est utilisé
   > (chaque zone reste différenciée) afin que l'application reste démontrable hors-ligne.
4. Ouvrir l'application → menu **« Real-Time API Data »** et **« Map / Air Quality »**.
 
## Endpoint
- `GET  backend/api/api-data.php` → résumé des 6 villes (utilisé par la carte).
- `GET  backend/api/api-data.php?city_id=N` → détail (3 API, historique, vecteur de features).
- `POST backend/api/api-data.php` `{ "force": true [, "city_id": N] }` → recalcul forcé.
 
## Vecteur de features (ML/DL/Fuzzy)
`fusion_feature_vector()` construit, **à partir de `api_readings`**, un vecteur enrichi
(lags AQI, polluants, météo, contexte temporel, prévisions AccuWeather) consommable par
les modules ML/DL/Fuzzy (PARTS 1–6, hors périmètre de ce module).