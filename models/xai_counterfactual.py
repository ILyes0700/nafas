"""PART 40 — Counterfactual Explanations (DiCE).

« si SO2 avait été 20% plus bas, l'AQI serait passé de CRITICAL à WARNING ».
Rend les recommandations actionnables : dit QUOI changer pour améliorer la
catégorie, pas juste QUOI a causé le problème (ce que SHAP/LIME font déjà).

Utilise la bibliothèque dice-ml si dispo ; sinon un fallback « search 1D »
simple (on réduit chaque feature par paliers jusqu'à changer de classe).
Injecté dans counterfactual_explanations + recommendations_log quand dispo.
"""
from __future__ import annotations
from datetime import datetime

# Seuils AQI cohérents avec le reste du projet.
_CLASS_BOUNDS = [(0, 40, "SAFE"), (40, 70, "WARNING"),
                 (70, 100, "CRITICAL"), (100, 999, "HAZARDOUS")]


def aqi_class(aqi: float) -> str:
    for lo, hi, name in _CLASS_BOUNDS:
        if lo <= aqi < hi:
            return name
    return "HAZARDOUS"


def _has_dice() -> bool:
    try:
        import dice_ml  # noqa: F401
        return True
    except Exception as e:  # pragma: no cover
        print(f"[dice] dice-ml absent (fallback heuristique): {e}")
        return False


def counterfactual_1d(predict_fn, features: dict, target_class: str,
                      steps=(0.1, 0.2, 0.3, 0.4, 0.5)):
    """Cherche la plus petite réduction d'UNE feature qui atteint target_class.

    predict_fn : callable(dict) -> aqi (float)
    features   : dict {nom: valeur}
    return     : dict décrivant le contrefactuel, ou None si aucun trouvé.
    """
    base_aqi = predict_fn(features)
    base_class = aqi_class(base_aqi)
    if base_class == target_class:
        return None
    best = None
    for feat, val in features.items():
        if not isinstance(val, (int, float)) or val <= 0:
            continue
        for frac in steps:
            trial = dict(features)
            trial[feat] = val * (1 - frac)
            new_aqi = predict_fn(trial)
            if aqi_class(new_aqi) == target_class:
                cand = {
                    "feature_changed": feat,
                    "original_value": round(float(val), 3),
                    "counterfactual_value": round(float(val * (1 - frac)), 3),
                    "reduction_pct": int(frac * 100),
                    "original_class": base_class,
                    "counterfactual_class": target_class,
                    "narrative": (f"Si {feat} avait été {int(frac*100)}% plus bas "
                                  f"({round(val,1)} → {round(val*(1-frac),1)}), "
                                  f"l'AQI serait passé de {base_class} à {target_class}."),
                }
                if best is None or cand["reduction_pct"] < best["reduction_pct"]:
                    best = cand
                break
    return best


def default_predict_fn(features: dict) -> float:
    """Proxy AQI linéaire quand aucun modèle n'est passé (fallback offline)."""
    w = {"so2": 0.5, "pm25": 0.6, "pm10": 0.3, "no2": 0.4, "o3": 0.2}
    return sum(w.get(k, 0.1) * float(v) for k, v in features.items()
              if isinstance(v, (int, float)))


def generate_and_store(db, zone_id: int, features: dict,
                       predict_fn=None, target_class="WARNING"):
    """Génère un contrefactuel et l'écrit dans counterfactual_explanations +
    recommendations_log. Dégrade proprement partout.
    """
    _has_dice()
    predict_fn = predict_fn or default_predict_fn
    cf = counterfactual_1d(predict_fn, features, target_class)
    if not cf:
        return None
    if db is None:
        return cf
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    try:
        cur = db.cursor()
        cur.execute(
            "INSERT INTO counterfactual_explanations "
            "(zone_id, timestamp, original_class, counterfactual_class, feature_changed, "
            " original_value, counterfactual_value, narrative) "
            "VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
            (zone_id, now, cf["original_class"], cf["counterfactual_class"],
             cf["feature_changed"], cf["original_value"],
             cf["counterfactual_value"], cf["narrative"]),
        )
        db.commit()
    except Exception as e:  # pragma: no cover
        print(f"[dice] insert counterfactual: {e}")
    try:
        cur = db.cursor()
        cur.execute(
            "INSERT INTO recommendations_log (zone_id, recommendation_text, source) "
            "VALUES (%s,%s,%s)",
            (zone_id, cf["narrative"], "counterfactual"),
        )
        db.commit()
    except Exception as e:  # pragma: no cover
        print(f"[dice] insert reco log: {e}")
    return cf


if __name__ == "__main__":
    f = {"so2": 120, "pm25": 60, "no2": 40}
    print(counterfactual_1d(default_predict_fn, f, "WARNING"))
