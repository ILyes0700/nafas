"""PART 10 - Granger causality between Gabès pollution variables.
Uses statsmodels if available. Reference: Granger (1969), Econometrica.
"""
from __future__ import annotations
import numpy as np

PAIRS = [("SO2", "PM2.5"), ("SO2", "PM10"), ("wind", "AQI"),
         ("temp", "O3"), ("NO2", "AQI"), ("industrial_peak", "SO2"),
         ("AQI_ghannouche", "AQI_gabes")]
LAGS = [1, 2, 3, 6, 12, 24]


def granger_pair(effect, cause, maxlag=24):
    """effect, cause: 1D arrays. Returns dict lag -> (f_stat, p_value)."""
    try:
        from statsmodels.tsa.stattools import grangercausalitytests
        data = np.column_stack([effect, cause])
        res = grangercausalitytests(data, maxlag=maxlag, verbose=False)
        out = {}
        for lag in LAGS:
            if lag in res:
                f = res[lag][0]["ssr_ftest"][0]; p = res[lag][0]["ssr_ftest"][1]
                out[lag] = (float(f), float(p))
        return out
    except Exception as e:  # pragma: no cover
        print("statsmodels unavailable:", e)
        return {lag: (float("nan"), float("nan")) for lag in LAGS}


def run_all(series_by_name):
    """series_by_name: dict var_name -> 1D array. Returns list of result rows."""
    rows = []
    for cause, effect in PAIRS:
        if cause not in series_by_name or effect not in series_by_name:
            continue
        res = granger_pair(series_by_name[effect], series_by_name[cause])
        best_lag = min(res, key=lambda l: res[l][1])
        f, p = res[best_lag]
        rows.append({"relation": f"{cause} -> {effect}", "best_lag": best_lag,
                     "f_stat": round(f, 3), "p_value": round(p, 4),
                     "is_causal": bool(p < 0.05)})
    return rows


if __name__ == "__main__":
    print("granger_causality: define variable pairs (needs statsmodels to run).")
