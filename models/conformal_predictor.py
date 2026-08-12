"""PART 39 — Conformal Prediction (split conformal).

Intervalles de confiance calibrés statistiquement garantis, en complément des
colonnes uncertainty_lower/upper (heuristiques) de model_predictions. Garantit
un taux de couverture empirique (ex. 90%).

Calibré sur un hold-out de 20%. Écrit conformal_lower / conformal_upper /
conformal_coverage_target dans model_predictions.

Dépendances : numpy seulement (dispo dans l'environnement WAMP Python type).
Dégrade proprement si numpy absent.
"""
from __future__ import annotations


def split_conformal_quantile(residuals, coverage=0.90):
    """Retourne le quantile conforme q tel que |y - y_hat| <= q couvre `coverage`.

    residuals : liste des |y_true - y_pred| sur le set de calibration.
    Applique la correction (n+1)(1-alpha)/n de la conformal prediction.
    """
    try:
        import numpy as np
        r = np.sort(np.abs(np.asarray(residuals, dtype=float)))
        n = len(r)
        if n == 0:
            return 0.0
        alpha = 1.0 - coverage
        k = int(np.ceil((n + 1) * (1 - alpha)))
        k = min(max(k, 1), n)
        return float(r[k - 1])
    except Exception as e:  # pragma: no cover
        print(f"[conformal] numpy indisponible: {e}")
        # Fallback pur python
        r = sorted(abs(x) for x in residuals)
        if not r:
            return 0.0
        import math
        n = len(r)
        k = min(max(int(math.ceil((n + 1) * coverage)), 1), n)
        return float(r[k - 1])


def calibrate(y_true, y_pred, coverage=0.90, cal_fraction=0.20):
    """Split conformal : renvoie q calculé sur les derniers `cal_fraction`."""
    n = min(len(y_true), len(y_pred))
    if n == 0:
        return 0.0
    cut = max(1, int(n * (1 - cal_fraction)))
    residuals = [abs(y_true[i] - y_pred[i]) for i in range(cut, n)] or \
                [abs(y_true[i] - y_pred[i]) for i in range(n)]
    return split_conformal_quantile(residuals, coverage)


def apply_intervals(db, coverage=0.90):
    """Calcule q par modèle sur model_predictions ayant actual_aqi, puis remplit
    conformal_lower/upper. Dégrade proprement si la table/colonnes manquent.
    """
    if db is None:
        print("[conformal] pas de DB — sauté.")
        return {}
    out = {}
    try:
        cur = db.cursor(dictionary=True)
        cur.execute(
            "SELECT model_name, predicted_aqi, actual_aqi FROM model_predictions "
            "WHERE actual_aqi IS NOT NULL"
        )
        rows = cur.fetchall()
    except Exception as e:  # pragma: no cover
        print(f"[conformal] lecture impossible: {e}")
        return {}

    by_model = {}
    for r in rows:
        by_model.setdefault(r["model_name"], []).append(
            (float(r["predicted_aqi"]), float(r["actual_aqi"]))
        )

    for model, pairs in by_model.items():
        preds = [p for p, _ in pairs]
        acts = [a for _, a in pairs]
        q = calibrate(acts, preds, coverage)
        out[model] = q
        try:
            cur2 = db.cursor()
            cur2.execute(
                "UPDATE model_predictions SET conformal_lower = predicted_aqi - %s, "
                "conformal_upper = predicted_aqi + %s, conformal_coverage_target = %s "
                "WHERE model_name = %s",
                (q, q, coverage, model),
            )
            db.commit()
        except Exception as e:  # pragma: no cover
            print(f"[conformal] update {model}: {e}")
    print(f"[conformal] quantiles: {out}")
    return out


if __name__ == "__main__":
    yt = [10, 12, 9, 11, 13, 8, 10, 12, 14, 9]
    yp = [10.5, 11, 9.5, 12, 12, 9, 10, 11, 13, 10]
    print("q90 =", calibrate(yt, yp, 0.90))
