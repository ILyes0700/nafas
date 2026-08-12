"""PART 43 — Model Registry / Versioning.

Traçabilité complète des versions de modèles (table simple `model_versions`,
pas de serveur MLflow séparé — cohérent avec le déploiement WAMP local).
Reproductibilité scientifique : savoir exactement quelle version de quel
modèle a produit quel résultat.

Appelé à la fin de train_all.py. Dégrade proprement si la table est absente.
"""
from __future__ import annotations
import json
from datetime import datetime


def _next_version(db, model_name: str) -> str:
    """Incrémente v1 -> v2 ... par modèle."""
    try:
        cur = db.cursor()
        cur.execute("SELECT COUNT(*) FROM model_versions WHERE model_name = %s", (model_name,))
        n = int(cur.fetchone()[0])
        return f"v{n + 1}"
    except Exception:
        return "v1"


def register_version(db, model_name: str, metrics: dict, status: str = "staging") -> str | None:
    """Enregistre une nouvelle version avec un snapshot des métriques."""
    if db is None:
        print("[registry] pas de DB — sauté.")
        return None
    version = _next_version(db, model_name)
    try:
        cur = db.cursor()
        cur.execute(
            "INSERT INTO model_versions (model_name, version, trained_at, metrics_snapshot, status) "
            "VALUES (%s,%s,%s,%s,%s)",
            (model_name, version, datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
             json.dumps(metrics), status),
        )
        db.commit()
        print(f"[registry] {model_name} {version} enregistrée ({status}).")
        return version
    except Exception as e:  # pragma: no cover
        print(f"[registry] register: {e}")
        return None


def promote(db, model_name: str, version: str) -> bool:
    """Passe une version en production et archive l'ancienne production."""
    if db is None:
        return False
    try:
        cur = db.cursor()
        cur.execute(
            "UPDATE model_versions SET status='archived' "
            "WHERE model_name=%s AND status='production'", (model_name,))
        cur.execute(
            "UPDATE model_versions SET status='production', promoted_at=%s "
            "WHERE model_name=%s AND version=%s",
            (datetime.now().strftime("%Y-%m-%d %H:%M:%S"), model_name, version))
        db.commit()
        return True
    except Exception as e:  # pragma: no cover
        print(f"[registry] promote: {e}")
        return False


def archive(db, model_name: str, version: str) -> bool:
    if db is None:
        return False
    try:
        cur = db.cursor()
        cur.execute("UPDATE model_versions SET status='archived' "
                    "WHERE model_name=%s AND version=%s", (model_name, version))
        db.commit()
        return True
    except Exception as e:  # pragma: no cover
        print(f"[registry] archive: {e}")
        return False


def current_production(db, model_name: str):
    if db is None:
        return None
    try:
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM model_versions "
                    "WHERE model_name=%s AND status='production' "
                    "ORDER BY promoted_at DESC LIMIT 1", (model_name,))
        return cur.fetchone()
    except Exception:
        return None
