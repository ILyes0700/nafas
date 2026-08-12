"""PART 42 — Calibration curves + Brier score.

Valide que les probabilités prédites (SAFE/WARNING/CRITICAL/HAZARDOUS) sont
FIABLES, pas juste précises. Un modèle peut être précis mais mal calibré
(dit "90% critique" alors que ce n'est vrai que 60% du temps) — dangereux
pour un système d'alerte santé publique.

Écrit brier_score + reliability_bin dans calibration_metrics (affiché par
comparison.php). numpy si dispo, sinon fallback python pur.
"""
from __future__ import annotations
import json
from datetime import datetime


def brier_score_multiclass(y_true_onehot, y_prob):
    """Brier multiclasse = moyenne des (p - y)^2 sommée sur les classes."""
    try:
        import numpy as np
        yt = np.asarray(y_true_onehot, dtype=float)
        yp = np.asarray(y_prob, dtype=float)
        return float(np.mean(np.sum((yp - yt) ** 2, axis=1)))
    except Exception:
        n = len(y_true_onehot)
        if n == 0:
            return 0.0
        total = 0.0
        for yt, yp in zip(y_true_onehot, y_prob):
            total += sum((p - t) ** 2 for p, t in zip(yp, yt))
        return total / n


def reliability_bins(confidences, correct, n_bins=10):
    """Courbe de fiabilité : par bin de confiance, accuracy empirique observée."""
    bins = []
    for b in range(n_bins):
        lo, hi = b / n_bins, (b + 1) / n_bins
        idx = [i for i, c in enumerate(confidences) if lo <= c < hi]
        if not idx:
            bins.append({"bin": f"{lo:.1f}-{hi:.1f}", "n": 0, "acc": None, "conf": None})
            continue
        acc = sum(correct[i] for i in idx) / len(idx)
        conf = sum(confidences[i] for i in idx) / len(idx)
        bins.append({"bin": f"{lo:.1f}-{hi:.1f}", "n": len(idx),
                     "acc": round(acc, 3), "conf": round(conf, 3)})
    return bins


def evaluate_and_store(db, model_name, y_true_onehot, y_prob):
    """Calcule Brier + reliability et écrit dans calibration_metrics."""
    brier = brier_score_multiclass(y_true_onehot, y_prob)
    # confiance = proba max ; correct = argmax == vraie classe
    confidences, correct = [], []
    for yt, yp in zip(y_true_onehot, y_prob):
        mx = max(range(len(yp)), key=lambda k: yp[k])
        confidences.append(float(yp[mx]))
        correct.append(1 if yt[mx] == 1 else 0)
    bins = reliability_bins(confidences, correct)
    if db is not None:
        try:
            cur = db.cursor()
            cur.execute(
                "INSERT INTO calibration_metrics (model_name, evaluated_at, brier_score, reliability_bin) "
                "VALUES (%s,%s,%s,%s)",
                (model_name, datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                 brier, json.dumps(bins)),
            )
            db.commit()
        except Exception as e:  # pragma: no cover
            print(f"[calibration] insert: {e}")
    print(f"[calibration] {model_name} Brier={brier:.4f}")
    return {"brier_score": brier, "reliability_bin": bins}


if __name__ == "__main__":
    yt = [[1, 0, 0], [0, 1, 0], [0, 0, 1]]
    yp = [[0.8, 0.1, 0.1], [0.2, 0.7, 0.1], [0.1, 0.2, 0.7]]
    print(evaluate_and_store(None, "demo", yt, yp))
