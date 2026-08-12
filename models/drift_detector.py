"""PART 14 - Concept drift detection via KL divergence between the recent window
and the baseline distribution. Triggers retraining when drift >= 0.5.
Reference: Gama et al. (2014), ACM Surveys.
"""
from __future__ import annotations
import numpy as np


def _hist(values, bins, rng):
    h, _ = np.histogram(values, bins=bins, range=rng, density=True)
    h = h + 1e-9
    return h / h.sum()


def kl_divergence(recent, baseline, bins=20):
    from scipy.stats import entropy
    lo = min(np.min(recent), np.min(baseline))
    hi = max(np.max(recent), np.max(baseline))
    p = _hist(recent, bins, (lo, hi)); q = _hist(baseline, bins, (lo, hi))
    return float(entropy(p, q))


def statistical_shift(recent, baseline):
    return float(abs(np.mean(recent) - np.mean(baseline)) /
                 (np.std(baseline) + 1e-9))


def detect(recent, baseline):
    try:
        kl = kl_divergence(recent, baseline)
    except Exception:
        kl = statistical_shift(recent, baseline)
    shift = statistical_shift(recent, baseline)
    drift = 0.60 * min(1.0, kl) + 0.40 * min(1.0, shift)
    return {"kl_divergence": round(kl, 3), "statistical_shift": round(shift, 3),
            "drift_score": round(drift, 3), "drift_detected": bool(drift >= 0.5),
            "retraining_triggered": bool(drift >= 0.5)}


if __name__ == "__main__":
    rng = np.random.default_rng(0)
    base = rng.normal(80, 15, 500); rec = rng.normal(120, 20, 200)
    print(detect(rec, base))
