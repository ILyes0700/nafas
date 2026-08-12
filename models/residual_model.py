"""PART 12 - Residual correction. Trains a lightweight model on the ensemble's
errors and adds the correction back. Reference: He et al. (2016), CVPR.
"""
from __future__ import annotations
import numpy as np
from sklearn.ensemble import GradientBoostingRegressor


class ResidualCorrector:
    def __init__(self):
        self.model = GradientBoostingRegressor(n_estimators=120, max_depth=3, learning_rate=0.05)
        self.fitted = False

    def fit(self, X, actual, ensemble_pred):
        residual = np.asarray(actual) - np.asarray(ensemble_pred)
        self.model.fit(X, residual)
        self.fitted = True
        return self

    def correct(self, X, ensemble_pred):
        if not self.fitted:
            return np.asarray(ensemble_pred)
        return np.asarray(ensemble_pred) + self.model.predict(X)


if __name__ == "__main__":
    rng = np.random.default_rng(0)
    X = rng.normal(size=(200, 5))
    actual = X[:, 0] * 5 + 100
    ens = actual + rng.normal(scale=6, size=200)
    rc = ResidualCorrector().fit(X, actual, ens)
    corrected = rc.correct(X, ens)
    print("RMSE ens:", float(np.sqrt(np.mean((actual - ens) ** 2))))
    print("RMSE corrected:", float(np.sqrt(np.mean((actual - corrected) ** 2))))
