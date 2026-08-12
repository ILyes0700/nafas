Guide complet pour démarrer le projet de zéro, après la migration Open-Meteo. Suis les étapes dans l'ordre : chacune dépend de la précédente.

## Comprendre l'architecture avant de lancer

Ton projet n'est pas une seule application, c'en est **quatre** qui communiquent par la base de données. C'est la clé pour comprendre quoi lancer et dans quel ordre.

| Couche | Technologie | Rôle | Obligatoire ? |
| --- | --- | --- | --- |
| MySQL | port 3306 | Stocke tout : `open_data`, `zones`, `model_performance`… | **Oui** |
| Apache + PHP | port 80 | Sert `frontend/index.php` et les 25 endpoints `backend/api/` | **Oui** |
| Electron | Node.js | Fenêtre bureau qui affiche l'app PHP + export PDF | Non |
| Python `models/` | — | Entraîne les modèles et écrit les métriques en base | Une fois |

Le point important : **Python ne tourne pas en permanence**. Tu le lances une fois pour entraîner, il remplit les tables de résultats, puis PHP lit ces tables. Si tu ne lances jamais Python, l'app fonctionne quand même mais tous les écrans IA affichent le badge « Modèle non entraîné ».

---

## Étape 1 — Prérequis à installer

**WAMP ou XAMPP** (Apache + PHP 8.1+ + MySQL). WAMP est ce que ton projet vise par défaut, les commentaires du code le mentionnent explicitement.

**Python 3.10 ou 3.11.** Attention : évite 3.12 et 3.13, TensorFlow n'y est pas toujours disponible. Vérifie avec `python --version`.

**Node.js 18+** uniquement si tu veux la fenêtre Electron.

Vérifie ensuite que ces trois extensions PHP sont activées dans `php.ini` (retire le `;` devant) :

```
extension=pdo_mysql
extension=curl
extension=openssl
```

`pdo_mysql` est indispensable, `curl` sert aux appels Groq et aux API météo, `openssl` à l'envoi des e-mails OTP.

---

## Étape 2 — Placer le projet au bon endroit

Le dossier doit être servi par Apache. Copie le contenu du dossier `gabes-tatenafas/` (celui qui contient `backend/`, `frontend/`, `models/`…) dans :

```
WAMP   : C:\wamp64\www\gabes-tatenafas\
XAMPP  : C:\xampp\htdocs\gabes-tatenafas\
Linux  : /var/www/html/gabes-tatenafas/
```

<aside>
⚠️

Ton dépôt GitHub a un dossier `gabes-tatenafas/` **à l'intérieur** de la racine, avec `db.sql` à côté. Ne copie pas le niveau du dessus, sinon ton URL deviendrait `/gabes-tatenafas/gabes-tatenafas/frontend/` et rien ne marcherait.

</aside>

Démarre Apache et MySQL depuis le panneau WAMP/XAMPP. L'icône doit être verte.

---

## Étape 3 — Créer et remplir la base

Ouvre `http://localhost/phpmyadmin`.

**3.1** Crée une base nommée exactement `gabes_tatenafas`, interclassement `utf8mb4_general_ci`. Le nom est codé en dur dans `backend/config/database.php` et `models/db_config.py`.

**3.2** Sélectionne-la, onglet **Importer**, choisis `db.sql` (7,6 Mo) et lance. Si le fichier est refusé pour cause de taille, augmente dans `php.ini` :

```
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

Puis redémarre Apache.

**3.3** Exécute maintenant le script de migration — le fichier complet est sur la page Fichier complet 16/N — migration_open_data.sql. N'oublie pas d'adapter le chemin du CSV à l'ÉTAPE B.

En fin d'exécution tu dois voir 7 zones peuplées et **zéro orphelin** dans la requête E.3.

---

## Étape 4 — Vérifier la configuration

Les identifiants par défaut sont déjà ceux de WAMP :

```php
// backend/config/database.php
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'gabes_tatenafas';
const DB_USER = 'root';
const DB_PASS = '';        // <- mets ton mot de passe ici si tu en as un
```

Si tu changes le mot de passe, **change-le aussi côté Python**. Deux options :

```bash
# Option A : variable d'environnement (recommandé, ne touche pas au code)
set GT_DB_PASS=ton_mot_de_passe        # Windows
export GT_DB_PASS=ton_mot_de_passe     # Linux / Mac

# Option B : éditer models/db_config.py directement
```

`db_config.py` lit `GT_DB_HOST`, `GT_DB_PORT`, `GT_DB_NAME`, `GT_DB_USER` et `GT_DB_PASS`.

---

## Étape 5 — Lancer le diagnostic intégré

Ouvre dans ton navigateur :

```
http://localhost/gabes-tatenafas/verify-install.php
```

C'est le meilleur moyen de savoir ce qui manque **avant** d'ouvrir l'app. Il vérifie les extensions PHP, la connexion MySQL, la présence des tables, les fichiers critiques, et depuis la migration il contrôle aussi que `open_data` est peuplée et que les tables CGAN ont bien disparu.

Corrige tout ce qui est en rouge avant de continuer. Les avertissements orange ne sont pas bloquants.

---

## Étape 6 — Ouvrir l'application web

```
http://localhost/gabes-tatenafas/frontend/index.php
```

C'est le seul point d'entrée. Tout le reste passe par le routeur côté client (`#/dashboard`, `#/forecast-ml`, `#/bilstm-ae`…).

Connecte-toi avec un compte admin existant de ta base. Si tu n'en as aucun ou que tu as perdu le mot de passe, crées-en un via phpMyAdmin :

```sql
-- Genere d'abord le hash avec :  php -r "echo password_hash('TonMotDePasse', PASSWORD_DEFAULT);"
UPDATE `users`
SET `password_hash` = 'COLLE_LE_HASH_ICI',
    `role` = 'admin',
    `email_verified` = 1
WHERE `email` = 'ton.email@isimg.tn';
```

<aside>
🔑

Le rôle `admin` est obligatoire pour voir les écrans IA. `backend/lib/auth.php` bloque les routes `forecast-ml`, `deep-learning`, `bilstm-ae`, `comparison` et `ablation` avec un 403 pour les autres rôles.

</aside>

---

## Étape 7 — Entraîner les modèles

Sans cette étape, les pages IA sont vides. Ouvre un terminal **à la racine du projet** (le dossier qui contient `models/`) :

```bash
cd C:\wamp64\www\gabes-tatenafas

# 7.1 Environnement virtuel (fortement conseille)
python -m venv .venv
.venv\Scripts\activate            # Windows
source .venv/bin/activate          # Linux / Mac

# 7.2 Dependances
pip install -r models/requirements.txt

# 7.3 Test de connexion avant de lancer 3 heures d'entrainement
python -c "from models.db_config import get_connection; c = get_connection(); print('MySQL OK'); c.close()"

# 7.4 Test du chargement des donnees reelles
python -c "from models.data_loader import build_frames; f = build_frames(); print(len(f), 'zones chargees')"

# 7.5 Entrainement complet
python -m models.train_all
```

L'étape 7.4 doit afficher `7 zones chargees` et une ligne par ville avec les vraies dates du split 80/20. Si elle échoue, le problème vient de la migration SQL, pas de Python — inutile d'aller plus loin.

<aside>
⏱️

**Compte plusieurs heures sur CPU.** 7 zones × 3 horizons × une dizaine de modèles sur ~21 000 lignes réelles chacune. Avant la migration c'était rapide parce que les données étaient dix fois moins nombreuses et largement dupliquées. Pour un premier test, réduis `EPOCHS` à 20 dans `models/deep_models.py`.

</aside>

Si TensorFlow n'est pas installé, ce n'est pas bloquant : les modèles à arbres (Random Forest, XGBoost) s'entraînent quand même, et le BiLSTM ainsi que le BiLSTM+AE sont simplement sautés. `available()` renvoie `False` et rien n'est inventé.

---

## Étape 8 — Lancer la fenêtre Electron (optionnel)

```bash
cd electron
npm install
npm start
```

<aside>
🐛

**Bug à corriger dans `electron/main.js`.** Le commentaire en haut du fichier annonce l'URL `http://localhost/gabes-tatenafas/frontend/index.php`, mais le code utilise en réalité `gabes-tatenafas-v2` : `const APP_URL = process.env.GABES_URL || 'http://localhost/gabes-tatenafas-v2/frontend/index.php';`
Si ton dossier s'appelle `gabes-tatenafas`, Electron affichera la page `fallback.html` au lieu de l'application.

</aside>

Deux façons de régler ça. Soit tu passes l'URL sans toucher au code :

```bash
set GABES_URL=http://localhost/gabes-tatenafas/frontend/index.php
npm start
```

Soit tu corriges la ligne 12 de `main.js` pour qu'elle corresponde à ton dossier réel.

Pour générer un exécutable Windows : `npm run build:win` produit un installeur et une version portable dans `electron/dist/`. Attention, l'app packagée a **toujours besoin de WAMP allumé** — Electron n'est qu'une fenêtre, il n'embarque ni PHP ni MySQL.

---

## Routine quotidienne

Une fois tout installé, démarrer le projet se résume à :

1. Lancer WAMP/XAMPP, attendre l'icône verte
2. Ouvrir `http://localhost/gabes-tatenafas/frontend/index.php` (ou `npm start` dans `electron/`)

C'est tout. Python ne se relance que quand tu veux recalculer les métriques.

---

## Dépannage

| Symptôme | Cause | Solution |
| --- | --- | --- |
| Page blanche | Erreurs PHP masquées | Mets `display_errors = On` dans `php.ini`, relis `logs/php_error.log` |
| `SQLSTATE[HY000] [1049]` | Base introuvable | Le nom doit être exactement `gabes_tatenafas` |
| `SQLSTATE[HY000] [1045]` | Mauvais mot de passe | Corrige `DB_PASS` dans `database.php` |
| `could not find driver` | `pdo_mysql` désactivé | Décommente-le dans `php.ini`, redémarre Apache |
| Toutes les pages IA en « non entraîné » | Python jamais lancé | Fais l'étape 7 |
| Menu « BiLSTM + Autoencoder » → page blanche | Fichiers manquants | Crée `bilstm-ae.html` et `bilstm-ae.js` (page 17/N) |
| 403 sur les écrans IA | Rôle insuffisant | Passe ton compte en `admin` |
| Electron affiche « Connexion impossible » | URL `-v2` erronée ou WAMP éteint | Voir l'étape 8 |
| `ModuleNotFoundError: models` | Mauvais dossier | Lance depuis la racine avec `python -m models.train_all`, jamais `python models/train_all.py` |
| `Cannot connect to MySQL` côté Python | MySQL éteint ou driver absent | `pip install mysql-connector-python` |
| Caractères arabes en `????` | Mauvais encodage | La base et les tables doivent être en `utf8mb4_general_ci` |

---

## Ordre de démarrage si tout casse

Quand tu ne sais plus d'où vient le problème, remonte la chaîne dans cet ordre. Chaque maillon dépend du précédent, donc le premier qui échoue est ta vraie cause.

```
MySQL demarre ?
  -> phpMyAdmin accessible ?
     -> base gabes_tatenafas presente et peuplee ?
        -> verify-install.php tout vert ?
           -> frontend/index.php s'affiche ?
              -> connexion admin OK ?
                 -> pages IA remplies ? (sinon : entrainer)
```

<aside>
💡

Le dossier `models/api_server.py` existe (Flask + SocketIO) mais **n'est pas nécessaire** au fonctionnement normal. Le projet communique par la base de données, pas par HTTP entre PHP et Python. Ne le lance que si tu veux des prédictions temps réel poussées via WebSocket.

</aside>