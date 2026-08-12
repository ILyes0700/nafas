"""PART 12 - Adaptive ensemble with dynamic softmax weights + PART 13 uncertainty
& trust score. Pure NumPy.

References: Dietterich (2000); Gal & Ghahramani (2016).
"""
from __future__ import annotations
import numpy as np


def softmax(x, t=2.0):
    x = np.asarray(x, dtype=float) * t
    x -= x.max()
    e = np.exp(x)
    return e / e.sum()


def ensemble_weights(members):
    """members: list of dicts with r2, f1, rmse, latency. Returns weights list."""
    max_rmse = max(m["rmse"] for m in members)
    max_lat = max(m["latency"] for m in members)
    scores = [0.30 * m["r2"] + 0.30 * m["f1"] - 0.25 * (m["rmse"] / max_rmse)
              - 0.15 * (m["latency"] / max_lat) for m in members]
    w = softmax(scores)
    # Les modeles a score composite NEGATIF (plus mauvais que la reference) sont
    # exclus du vote final : softmax est toujours positif par construction, ce
    # qui donnait sinon ~6-7% de poids a des modeles faibles. On les met a 0 et
    # on renormalise sur les modeles positifs (plus defendable en soutenance).
    mask = np.array([1.0 if s >= 0 else 0.0 for s in scores])
    if mask.sum() > 0:
        w = np.asarray(w) * mask
        w = w / w.sum()
    return w.tolist(), scores


def ensemble_predict(preds, weights):
    preds = np.asarray(preds); weights = np.asarray(weights)
    return float((preds * weights).sum())


def uncertainty_and_trust(preds, weights, fuzzy_lower, fuzzy_upper,
                          accuracy=0.9, drift_score=0.2):
    preds = np.asarray(preds); weights = np.asarray(weights)
    mean = (preds * weights).sum()
    ens_var = float((weights * (preds - mean) ** 2).sum())
    fuzzy_unc = (fuzzy_upper - fuzzy_lower) / 100.0
    total_unc = float(np.sqrt(0.60 * ens_var + 0.40 * fuzzy_unc ** 2))
    ci = (mean - 1.645 * total_unc, mean + 1.645 * total_unc)
    confidence = 1.0 / (1.0 + total_unc)
    trust = 0.40 * accuracy + 0.35 * confidence + 0.25 * (1 - min(1.0, drift_score))
    level = "HIGH" if trust >= 0.8 else "MEDIUM" if trust >= 0.6 else "LOW"
    return {"pred": round(mean, 1), "ci90": [round(ci[0], 1), round(ci[1], 1)],
            "uncertainty": round(total_unc, 3), "confidence": round(confidence, 3),
            "trust": round(trust, 3), "trust_level": level}


if __name__ == "__main__":
    members = [dict(r2=0.80, f1=0.82, rmse=12.0, latency=6),
               dict(r2=0.85, f1=0.86, rmse=10.2, latency=7),
               dict(r2=0.92, f1=0.91, rmse=7.3, latency=31)]
    w, s = ensemble_weights(members)
    print("weights:", [round(x, 3) for x in w])
    print(uncertainty_and_trust([88, 92, 85], w, 70, 82))


# ============================================================================
# PART 46 — RL DYNAMIC ENSEMBLE (patch, ne réécrit pas la pondération existante)
# ----------------------------------------------------------------------------
# Remplace l'appel STATIQUE aux poids par un agent contextuel (LinUCB) quand il
# est disponible. On garde la pondération statique existante en fallback : si
# l'agent RL n'est pas importable, get_dynamic_weights() renvoie None et
# l'appelant continue avec sa logique d'origine (dégradation gracieuse).
# ============================================================================
def get_dynamic_weights(db=None, arms=("bilstm", "xgboost", "lstm", "tft"),
                        zone_id: int = 1, drift_score: float = 0.0):
    """Retourne des poids d'ensemble contextuels via le bandit LinUCB, ou None.

    Usage recommandé (patch minimal là où les poids statiques sont lus) :
        w = get_dynamic_weights(db, zone_id=zid, drift_score=ds)
        if w is None:
            w = STATIC_WEIGHTS   # comportement d'origine conservé
    """
    try:
        from rl_ensemble_agent import compute_and_store_weights
    except Exception as e:  # pragma: no cover
        print(f"[ensemble] RL agent indisponible, poids statiques conservés: {e}")
        return None
    try:
        return compute_and_store_weights(db, arms=arms, zone_id=zone_id,
                                         drift_score=drift_score)
    except Exception as e:  # pragma: no cover
        print(f"[ensemble] RL weights échec, fallback statique: {e}")
        return None
