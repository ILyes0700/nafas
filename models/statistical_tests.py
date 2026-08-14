"""Tests statistiques sur des erreurs réellement produites par les modèles.

Les fonctions de ce module ne génèrent aucune donnée. Elles doivent recevoir les
vecteurs d'erreurs issus du même jeu de test réel et de la même zone/horizon.
"""
from __future__ import annotations
import numpy as np


ALLOWED_MODELS = (
    "Random Forest", "XGBoost + Fuzzy", "LSTM", "BiLSTM Simple",
    "BiLSTM+MultiHead Attn", "BiLSTM+AE", "CNN+AE",
)


def wilcoxon_vs(errors_best, errors_other):
    from scipy import stats
    best = np.asarray(errors_best, dtype=float)
    other = np.asarray(errors_other, dtype=float)
    if best.size == 0 or other.size == 0 or best.size != other.size:
        raise ValueError("Les erreurs doivent être non vides et alignées sur les mêmes observations réelles.")
    stat, p = stats.wilcoxon(best, other, alternative="less")
    return {"stat": float(stat), "p_value": float(p), "significant": bool(p < 0.05)}


def friedman(*error_arrays):
    from scipy.stats import friedmanchisquare
    arrays = [np.asarray(a, dtype=float) for a in error_arrays]
    if len(arrays) < 3 or any(a.size == 0 for a in arrays):
        raise ValueError("Friedman nécessite au moins trois vecteurs d'erreurs réels non vides.")
    if len({a.size for a in arrays}) != 1:
        raise ValueError("Les vecteurs d'erreurs doivent être alignés et de même longueur.")
    stat, p = friedmanchisquare(*arrays)
    return {"stat": float(stat), "p_value": float(p), "significant": bool(p < 0.05)}


def compare_best(errors_by_model, best_name):
    if best_name not in errors_by_model:
        raise KeyError(f"Modèle de référence absent : {best_name}")
    best = np.asarray(errors_by_model[best_name], dtype=float)
    rows = []
    for name, err in errors_by_model.items():
        if name == best_name:
            continue
        if name not in ALLOWED_MODELS:
            continue
        rows.append({"comparison": f"{best_name} vs {name}", **wilcoxon_vs(best, np.asarray(err, dtype=float))})
    return rows


if __name__ == "__main__":
    print("Aucune donnée synthétique n'est générée. Fournissez des erreurs réelles alignées pour exécuter un test statistique.")
