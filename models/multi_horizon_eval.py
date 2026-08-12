"""PART 7 - Multi-horizon evaluation (+1h / +6h / +24h) for every model."""
from __future__ import annotations
import numpy as np
from sklearn.metrics import mean_squared_error, f1_score

HORIZONS = ["1h", "6h", "24h"]


def evaluate(models, X_by_h, y_by_h):
    """models: dict name -> dict horizon -> fitted regressor.
    X_by_h/y_by_h: dict horizon -> arrays. Returns nested dict of metrics."""
    out = {}
    for name, per_h in models.items():
        out[name] = {}
        for h in HORIZONS:
            if h not in per_h:
                continue
            pred = per_h[h].predict(X_by_h[h])
            rmse = float(np.sqrt(mean_squared_error(y_by_h[h], pred)))
            cls_true = (np.asarray(y_by_h[h]) > 100).astype(int)
            cls_pred = (pred > 100).astype(int)
            f1 = float(f1_score(cls_true, cls_pred, average="macro", zero_division=0))
            out[name][h] = {"rmse": round(rmse, 2), "f1": round(f1, 3)}
    return out


if __name__ == "__main__":
    print("multi_horizon_eval: evaluate all models across horizons.")
