"""PART 44 — A/B testing automatique.

Teste deux modèles en production simultanément et bascule automatiquement si
l'un dérive (drift détecté par drift_detector.py / drift_monitoring). Relie
model_versions (Part 43) au drift_monitoring existant.

Règle simple : si drift_monitoring.drift_detected=1 sur le modèle en
production ET le challenger a un meilleur RMSE sur 7 jours glissants ET
l'amélioration >= 3% -> promotion automatique via model_registry_manager.

Dégrade proprement si une table manque.
"""
from __future__ import annotations
from datetime import datetime

MIN_IMPROVEMENT = 0.03  # 3%


def _rmse_last_7d(db, model_name):
    try:
        cur = db.cursor()
        cur.execute(
            "SELECT AVG(POW(predicted_aqi - actual_aqi, 2)) FROM model_predictions "
            "WHERE model_name=%s AND actual_aqi IS NOT NULL "
            "AND timestamp >= NOW() - INTERVAL 7 DAY", (model_name,))
        v = cur.fetchone()[0]
        return (float(v) ** 0.5) if v is not None else None
    except Exception as e:  # pragma: no cover
        print(f"[ab] rmse {model_name}: {e}")
        return None


def _has_drift(db, model_name) -> bool:
    try:
        cur = db.cursor()
        cur.execute(
            "SELECT drift_detected FROM drift_monitoring "
            "WHERE model_name=%s ORDER BY id DESC LIMIT 1", (model_name,))
        row = cur.fetchone()
        return bool(row and row[0])
    except Exception as e:  # pragma: no cover
        print(f"[ab] drift {model_name}: {e}")
        return False


def run_ab_test(db, model_a: str, model_b: str, traffic_split: float = 0.5):
    """Évalue A (prod) vs B (challenger). Promeut B si conditions réunies."""
    if db is None:
        print("[ab] pas de DB — sauté.")
        return None
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    rmse_a = _rmse_last_7d(db, model_a)
    rmse_b = _rmse_last_7d(db, model_b)
    drift_a = _has_drift(db, model_a)

    winner, reason = None, "insufficient_data"
    if rmse_a is not None and rmse_b is not None and rmse_a > 0:
        improvement = (rmse_a - rmse_b) / rmse_a
        if drift_a and improvement >= MIN_IMPROVEMENT:
            winner = model_b
            reason = (f"drift sur {model_a} + challenger meilleur de "
                      f"{improvement*100:.1f}% (>= 3%) -> promotion {model_b}")
            try:
                from model_registry_manager import promote  # type: ignore
                promote(db, model_b, version=_latest_version(db, model_b))
            except Exception as e:  # pragma: no cover
                print(f"[ab] promotion auto échouée: {e}")
        else:
            winner = model_a
            reason = (f"pas de bascule (drift={drift_a}, "
                      f"amélioration={improvement*100:.1f}%)")

    try:
        cur = db.cursor()
        cur.execute(
            "INSERT INTO ab_test_runs (model_a, model_b, started_at, ended_at, "
            "traffic_split, winner, decision_reason) VALUES (%s,%s,%s,%s,%s,%s,%s)",
            (model_a, model_b, now, now, traffic_split, winner, reason))
        db.commit()
    except Exception as e:  # pragma: no cover
        print(f"[ab] insert run: {e}")
    print(f"[ab] winner={winner} | {reason}")
    return {"winner": winner, "reason": reason, "rmse_a": rmse_a, "rmse_b": rmse_b}


def _latest_version(db, model_name):
    try:
        cur = db.cursor()
        cur.execute("SELECT version FROM model_versions WHERE model_name=%s "
                    "ORDER BY id DESC LIMIT 1", (model_name,))
        row = cur.fetchone()
        return row[0] if row else "v1"
    except Exception:
        return "v1"
