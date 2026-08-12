"""PART 9 - Statistical significance: Wilcoxon signed-rank (best vs each model)
and Friedman test across all models. Reference: Wilcoxon (1945); Demšar (2006).
"""
from __future__ import annotations
import numpy as np


def wilcoxon_vs(errors_best, errors_other):
    from scipy import stats
    stat, p = stats.wilcoxon(errors_best, errors_other, alternative="less")
    return {"stat": float(stat), "p_value": float(p), "significant": bool(p < 0.05)}


def friedman(*error_arrays):
    from scipy.stats import friedmanchisquare
    stat, p = friedmanchisquare(*error_arrays)
    return {"stat": float(stat), "p_value": float(p), "significant": bool(p < 0.05)}


def compare_best(errors_by_model, best_name):
    best = np.asarray(errors_by_model[best_name])
    rows = []
    for name, err in errors_by_model.items():
        if name == best_name:
            continue
        rows.append({"comparison": f"{best_name} vs {name}", **wilcoxon_vs(best, np.asarray(err))})
    return rows


if __name__ == "__main__":
    rng = np.random.default_rng(0)
    errs = {"FULL SYSTEM": np.abs(rng.normal(0, 5, 200)),
            "AR(7)": np.abs(rng.normal(0, 17, 200)),
            "XGBoost": np.abs(rng.normal(0, 10, 200))}
    for r in compare_best(errs, "FULL SYSTEM"):
        print(r)
    print("Friedman:", friedman(*errs.values()))
