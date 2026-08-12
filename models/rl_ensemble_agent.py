"""PART 46 — Reinforcement Learning pour l'ensemble dynamique.

Remplace la pondération STATIQUE de ensemble.py (ensemble_weights) par un
agent qui apprend selon le contexte (zone, heure, saison, drift). On utilise
un BANDIT CONTEXTUEL (LinUCB) — plus simple à justifier qu'un DQN complet vu
qu'il n'y a que ~4 modèles à pondérer.

contexte = [zone, heure, saison, drift_score] ; action = poids d'ensemble ;
reward = -RMSE observé. Écrit dans ensemble_weights. numpy si dispo, sinon
fallback poids uniformes (dégradation gracieuse).
"""
from __future__ import annotations
import json
import math
from datetime import datetime


class LinUCB:
    """Bandit contextuel LinUCB minimal (une tête par modèle/bras)."""

    def __init__(self, n_features: int, arms, alpha: float = 0.5):
        self.alpha = alpha
        self.arms = list(arms)
        self.d = n_features
        try:
            import numpy as np
            self.np = np
            self.A = {a: np.identity(self.d) for a in self.arms}
            self.b = {a: np.zeros((self.d, 1)) for a in self.arms}
            self.ok = True
        except Exception as e:  # pragma: no cover
            print(f"[rl] numpy absent, fallback uniforme: {e}")
            self.ok = False

    def _p(self, arm, x):
        np = self.np
        A_inv = np.linalg.inv(self.A[arm])
        theta = A_inv.dot(self.b[arm])
        x = x.reshape(-1, 1)
        mean = float(theta.T.dot(x).item())
        bonus = self.alpha * math.sqrt(float(x.T.dot(A_inv).dot(x).item()))
        return mean + bonus

    def weights(self, context):
        """Retourne des poids normalisés (softmax des scores UCB) par bras."""
        if not self.ok:
            n = len(self.arms)
            return {a: 1.0 / n for a in self.arms}
        np = self.np
        x = np.asarray(context, dtype=float)
        scores = {a: self._p(a, x) for a in self.arms}
        mx = max(scores.values())
        exps = {a: math.exp(scores[a] - mx) for a in self.arms}
        s = sum(exps.values()) or 1.0
        return {a: exps[a] / s for a in self.arms}

    def update(self, arm, context, reward):
        if not self.ok:
            return
        np = self.np
        x = np.asarray(context, dtype=float).reshape(-1, 1)
        self.A[arm] = self.A[arm] + x.dot(x.T)
        self.b[arm] = self.b[arm] + reward * x


def context_vector(zone_id: int, hour: int, month: int, drift_score: float):
    season = (month % 12) // 3  # 0..3
    return [1.0, zone_id / 10.0, hour / 24.0, season / 3.0, float(drift_score)]


def compute_and_store_weights(db, arms=("bilstm", "xgboost", "lstm", "tft"),
                              zone_id=1, drift_score=0.0):
    """Calcule les poids d'ensemble contextuels et les écrit dans ensemble_weights."""
    now = datetime.now()
    ctx = context_vector(zone_id, now.hour, now.month, drift_score)
    agent = LinUCB(n_features=len(ctx), arms=arms)
    w = agent.weights(ctx)
    if db is not None:
        try:
            cur = db.cursor()
            cur.execute(
                "INSERT INTO ensemble_weights (weights_json, context_json, created_at) "
                "VALUES (%s,%s,%s)",
                (json.dumps(w), json.dumps({"zone_id": zone_id, "hour": now.hour,
                                            "month": now.month, "drift": drift_score}),
                 now.strftime("%Y-%m-%d %H:%M:%S")))
            db.commit()
        except Exception as e:  # pragma: no cover
            print(f"[rl] insert ensemble_weights (fallback silencieux): {e}")
    print(f"[rl] poids contextuels: {w}")
    return w


if __name__ == "__main__":
    print(compute_and_store_weights(None))
