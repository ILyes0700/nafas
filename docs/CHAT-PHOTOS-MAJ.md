# Mise à jour du 1er mai 2026 — Chat santé/citoyen + Photos signalements

## 1. Ce qui a changé

### Feature A — Chat santé ↔ citoyen (page « Suivi des symptômes »)
- L'admin santé / admin global voit la **liste des citoyens** ayant signalé un symptôme,
  avec **filtres pills** : *Tous · Nouveaux · En cours · Résolus*.
- Clic sur un citoyen → **panneau de chat** à droite, avec sélecteur de *status*.
- **Le chat n'est PAS visible côté citoyen** tant que l'équipe santé n'a pas envoyé son
  premier message. Dès qu'un agent santé écrit, le symptôme passe automatiquement à
  l'état `in_progress` et la conversation apparaît côté citoyen dans la section
  « Discussions avec l'équipe santé ».
- Le citoyen ne peut répondre **que** sur les fils déjà ouverts par l'équipe santé.
- Polling 15 s côté admin et côté citoyen pour récupérer les nouveaux messages.

### Feature B — Photo dans « Signalements citoyens »
- Boutons **Prendre une photo** (caméra arrière sur mobile via `capture="environment"`)
  et **Choisir une image** (galerie / disque).
- **Aperçu** instantané + bouton « × » pour retirer.
- Validation côté navigateur **et** serveur (max 5 Mo, mimes : jpeg / png / webp / gif).
- Image sauvegardée sous `uploads/reports/report-YYYYMMDD-HHMMSS-XXXXXXXX.<ext>`.
- Vignette dans la liste des signalements + **lightbox** (clic = agrandir, Échap = fermer).

## 2. Comment installer / mettre à jour

### Étape 1 — Mettre à jour la base
**Sur ta base existante** `gabes_tatenafas` (sans tout réimporter) :
1. Ouvrir phpMyAdmin → DB `gabes_tatenafas` → onglet *SQL*.
2. Coller le contenu de `db/migrations/2026-05-01-chat-and-photos.sql` et cliquer *Exécuter*.
3. Le message `Migration 2026-05-01 — OK` doit s'afficher.
   Le script est **idempotent** : tu peux le relancer sans risque.

Alternative en ligne de commande :
```bash
mysql -u root gabes_tatenafas < db/migrations/2026-05-01-chat-and-photos.sql
```

> Si tu pars d'une **base vierge** (réinstallation propre), tu peux aussi
> simplement importer `db/schema.sql` qui a été synchronisé : il contient désormais
> `users`, `school_absences`, `symptom_messages`, ainsi que les colonnes
> `symptoms.status`, `symptoms.citizen_id`, `reports.image_path`.

### Étape 2 — Vérifier le dossier d'upload
Le dossier `uploads/reports/` doit être **inscriptible par Apache** :
- Sous WAMP/Windows : par défaut OK (Apache écrit avec ton compte).
- Sous Linux : `chmod 775 uploads/reports/`.

Le fichier `.htaccess` déjà présent dans `uploads/reports/` interdit l'exécution de
PHP / scripts dans ce dossier (sécurité).

### Étape 3 — Tester
1. Connecte-toi avec **`health` / `health123`** → ouvre *Suivi des symptômes*.
2. Tu vois la liste des citoyens à gauche, avec compteurs par status.
3. Clique sur un citoyen → écris un message → il bascule automatiquement en *En cours*.
4. Déconnecte-toi, reconnecte-toi avec **`citizen1` / `citizen123`** → tu vois ta
   nouvelle discussion en bas de la page Symptômes.
5. Pour la photo : connecte-toi en `citizen1` → page *Signalements citoyens* →
   *Prendre une photo* (ou *Choisir une image*) → soumets → la vignette apparaît
   dans la liste à droite.

## 3. Fichiers ajoutés / modifiés

### Ajoutés
- `db/migrations/2026-05-01-chat-and-photos.sql`
- `backend/api/symptom-chat.php`
- `frontend/styles/symptoms-chat.css`
- `uploads/reports/.htaccess`
- `docs/CHAT-PHOTOS-MAJ.md` *(ce fichier)*

### Modifiés
- `db/schema.sql` *(intègre désormais users, school_absences, symptom_messages, status, image_path)*
- `backend/api/symptoms.php` *(rattache citizen_id, retourne health_msg_count)*
- `backend/api/reports.php` *(supporte multipart + sauvegarde l'image)*
- `frontend/index.php` *(charge symptoms-chat.css)*
- `frontend/scripts/api.js` *(ajout `postForm` et `asset`)*
- `frontend/pages/symptoms.html` *(2 vues : citoyen vs santé)*
- `frontend/scripts/pages/symptoms.js` *(complet : chat + filtres + status)*
- `frontend/pages/citizen-reports.html` *(boutons photo + preview + lightbox)*
- `frontend/scripts/pages/citizen-reports.js` *(submit multipart + thumbs)*

## 4. Endpoints API

### `GET backend/api/symptom-chat.php?action=threads&status=all|new|in_progress|resolved`
*(rôles `health` / `admin`)*  → `{ ok, threads[], counts: { all, new, in_progress, resolved } }`

### `GET backend/api/symptom-chat.php?action=thread&symptom_id=X`
Retourne le détail d'un fil + messages (avec contrôle d'accès citoyen → uniquement
**ses** symptômes ET seulement si l'équipe santé a déjà répondu).

### `GET backend/api/symptom-chat.php?action=my`
*(rôle citoyen)* → liste des symptômes du citoyen connecté **où l'équipe santé
a déjà envoyé au moins un message**.

### `POST backend/api/symptom-chat.php` body `{ action: "send", symptom_id, message }`
Envoie un message dans le fil. Promotion auto du status `new` → `in_progress`
au premier message envoyé par un compte staff.

### `POST backend/api/symptom-chat.php` body `{ action: "set_status", symptom_id, status }`
*(rôles `health` / `admin`)*  → change le status (new / in_progress / resolved).

### `POST backend/api/reports.php` (FormData multipart)
Champs : `citizen_name`, `zone_id`, `category`, `description`, **`image`** *(file, optionnel)*.
Le JSON simple reste supporté (rétro-compatibilité).
