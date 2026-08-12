"""PART 1 - Interval Type-2 Fuzzy Logic (Mamdani -> Type-2) with Karnik-Mendel.

Produces `fuzzy_score_type2` (the key ML/DL feature) plus its interval
uncertainty band. Pure NumPy - no heavy deps required.

Reference: Mendel (2017). Type-2 Fuzzy Systems. Springer.
"""
from __future__ import annotations
import numpy as np


def tri(x, a, b, c):
    x = np.asarray(x, dtype=float)
    left = (x - a) / (b - a + 1e-9)
    right = (c - x) / (c - b + 1e-9)
    return np.clip(np.minimum(left, right), 0, 1)


def trap(x, a, b, c, d):
    x = np.asarray(x, dtype=float)
    left = (x - a) / (b - a + 1e-9)
    right = (d - x) / (d - c + 1e-9)
    return np.clip(np.minimum(np.minimum(left, right), 1), 0, 1)


# Interval Type-2 membership functions (UMF, LMF) for every variable
SETS = {
    "LOW":     {"umf": ("trap", 0, 0, 20, 35),    "lmf": ("trap", 0, 0, 15, 28)},
    "MEDIUM":  {"umf": ("tri", 25, 50, 75),       "lmf": ("tri", 30, 50, 70)},
    "HIGH":    {"umf": ("trap", 60, 75, 90, 100), "lmf": ("trap", 65, 78, 88, 100)},
    "EXTREME": {"umf": ("trap", 80, 90, 100, 100),"lmf": ("trap", 85, 93, 100, 100)},
}
CENTROIDS = {"LOW": 15, "MEDIUM": 40, "HIGH": 70, "EXTREME": 90}


def _mf(defn, x):
    return tri(x, *defn[1:]) if defn[0] == "tri" else trap(x, *defn[1:])


def membership(value):
    """Return dict set -> (lmf_degree, umf_degree) for a scalar input."""
    out = {}
    for name, d in SETS.items():
        out[name] = (float(_mf(d["lmf"], value)), float(_mf(d["umf"], value)))
    return out


def karnik_mendel(firing):
    """Simplified KM type-reduction over centroids given interval firing.

    firing: dict set -> (lower, upper). Returns (y_l, y_r).
    """
    xs = np.array([CENTROIDS[k] for k in SETS])
    lo = np.array([firing[k][0] for k in SETS])
    hi = np.array([firing[k][1] for k in SETS])
    yl = float((xs * hi).sum() / (hi.sum() + 1e-9))  # left uses UMF (wider)
    yr = float((xs * lo).sum() / (lo.sum() + 1e-9))  # right uses LMF
    if yl > yr:
        yl, yr = yr, yl
    return yl, yr


def compute_inputs(row, population=75000):
    """Build the 4 fuzzy inputs from an api_readings row (dict-like)."""
    pollution = min(100.0, float(row.get("final_aqi", 0)) / 5.0)
    vulnerability = min(100.0, max(0.0, (
        (float(row.get("final_humidity", 50)) / 100) * 0.3 +
        ((float(row.get("final_temperature", 25)) - 20) / 40) * 0.3 +
        (population / 150000) * 0.4) * 100))
    symptom = min(100.0, max(0.0, (
        (float(row.get("final_pm25", 0)) / 75) * 0.5 +
        (float(row.get("final_pm10", 0)) / 150) * 0.3 +
        (float(row.get("final_so2", 0)) / 100) * 0.2) * 100))
    return pollution, vulnerability, symptom


def assess(pollution, vulnerability=50.0, symptom=50.0, alerts_24h=0):
    """Full Type-2 assessment. Returns dict ready for `fuzzy_assessments`."""
    # aggregate firing across inputs (rule weight via mean of inputs)
    firing = {}
    for k in SETS:
        lo = np.mean([membership(v)[k][0] for v in (pollution, vulnerability, symptom)])
        hi = np.mean([membership(v)[k][1] for v in (pollution, vulnerability, symptom)])
        firing[k] = (float(lo), float(hi))
    yl, yr = karnik_mendel(firing)
    score = (yl + yr) / 2
    band = yr - yl
    risk = ("low" if score < 30 else "moderate" if score < 55
            else "high" if score < 80 else "critical")
    return {
        "fuzzy_score_type2": round(score, 2),
        "uncertainty_lower": round(yl, 2),
        "uncertainty_upper": round(yr, 2),
        "uncertainty_band": round(band, 2),
        "risk_level": risk,
    }


if __name__ == "__main__":
    demo = assess(78, 62, 55, 4)
    print("Type-2 fuzzy assessment:", demo)
