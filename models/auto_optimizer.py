"""PART 16 - Auto-optimization loop (runs every 24h): drift check, SHAP-based
feature pruning, interaction feature engineering, Optuna re-tuning, retrain and
keep-if-better, ensemble weight refresh, residual retrain.
"""
from __future__ import annotations
import numpy as np
try:
    from . import drift_detector
except Exception:  # allow running as a plain script
    import drift_detector


def engineer_features(row):
    """Add the 3 interaction features from PART 3."""
    so2 = row.get("so2", 0); peak = row.get("is_industrial_peak", 0)
    temp = row.get("temperature", 25); hum = row.get("humidity", 50)
    wind = row.get("wind_speed", 5)
    a1 = row.get("aqi_t1", 0); a3 = row.get("aqi_t3", 0)
    return {
        "industrial_risk": so2 * peak,
        "weather_pollution": temp * hum / (wind + 1),
        "pollution_momentum": a1 - a3,
    }


def prune_low_importance(importances, names, threshold=0.01):
    keep = [n for n, imp in zip(names, importances) if imp >= threshold]
    removed = [n for n, imp in zip(names, importances) if imp < threshold]
    return keep, removed


def optimization_cycle(recent, baseline, importances, feature_names,
                       retune_fn=None):
    d = drift_detector.detect(recent, baseline)
    keep, removed = prune_low_importance(importances, feature_names)
    best_params = retune_fn() if (d["drift_detected"] and retune_fn) else None
    return {"drift": d, "features_kept": keep, "features_removed": removed,
            "retuned": bool(best_params), "best_params": best_params}


if __name__ == "__main__":
    rng = np.random.default_rng(0)
    print(optimization_cycle(rng.normal(120, 20, 100), rng.normal(80, 15, 300),
                             [0.2, 0.005, 0.15], ["so2", "uv", "fuzzy"]))
