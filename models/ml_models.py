"""PART 4 - Classical ML module: Random Forest + XGBoost (Optuna-tuned),
SHAP (global + local), LIME, ROC AUC, walk-forward CV.

Feature vector = the shared 27 features (PART 3). fuzzy_score_type2 is a key
input coming from models/fuzzy_type2.py.

Optional deps: xgboost, optuna, shap, lime. The module degrades gracefully:
if a lib is missing it skips that step instead of crashing.
"""
from __future__ import annotations
import json, time
import numpy as np
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
from sklearn.metrics import (mean_absolute_error, mean_squared_error, r2_score,
                             f1_score, accuracy_score, roc_curve, auc)
from sklearn.model_selection import TimeSeriesSplit
from sklearn.preprocessing import label_binarize

FEATURE_NAMES = [
    "aqi_t1", "aqi_t2", "aqi_t3", "aqi_t4", "aqi_t5", "aqi_t6", "aqi_t7",
    "aqi_t24", "aqi_t168", "fuzzy_score_type2", "uncertainty_lower",
    "uncertainty_upper", "pm25", "pm10", "so2", "no2", "temperature",
    "humidity", "wind_speed", "wind_direction", "pressure", "uv_index",
    "forecast_3h", "forecast_6h", "hour_of_day", "is_weekend", "season",
]
HORIZONS = ["1h", "6h", "24h"]
CLASSES = [0, 1, 2, 3]  # SAFE / WARNING / CRITICAL / HAZARDOUS


def smape(y, p):
    y, p = np.asarray(y), np.asarray(p)
    return float(np.mean(2 * np.abs(p - y) / (np.abs(y) + np.abs(p) + 1e-9)) * 100)


def metrics(y, p):
    rmse = float(np.sqrt(mean_squared_error(y, p)))
    return {
        "mae": float(mean_absolute_error(y, p)), "rmse": rmse,
        "mape": float(np.mean(np.abs((np.asarray(y) - p) / (np.asarray(y) + 1e-9))) * 100),
        "smape": smape(y, p), "r2": float(r2_score(y, p)),
    }


def train_random_forest(X, y, **kw):
    params = dict(n_estimators=150, max_depth=12, min_samples_split=10,
                  max_features="sqrt", n_jobs=-1, random_state=42)
    params.update(kw)
    m = RandomForestRegressor(**params)
    m.fit(X, y)
    return m


def tune_xgboost(X_tr, y_tr, X_val, y_val, n_trials=200, timeout=3600):
    """Optuna hyper-parameter search for XGBoost. Returns best_params dict."""
    try:
        import optuna, xgboost as xgb
    except Exception as e:  # pragma: no cover
        print("optuna/xgboost missing, using defaults:", e)
        return {"n_estimators": 300, "max_depth": 6, "learning_rate": 0.05}

    def objective(trial):
        params = {
            "n_estimators": trial.suggest_int("n_estimators", 100, 500),
            "max_depth": trial.suggest_int("max_depth", 3, 10),
            "learning_rate": trial.suggest_float("lr", 0.01, 0.3, log=True),
            "subsample": trial.suggest_float("subsample", 0.6, 1.0),
            "colsample_bytree": trial.suggest_float("colsample", 0.6, 1.0),
            "reg_lambda": trial.suggest_float("lambda", 0.1, 5.0),
            "gamma": trial.suggest_float("gamma", 0.0, 1.0),
        }
        model = xgb.XGBRegressor(**params)
        model.fit(X_tr, y_tr, eval_set=[(X_val, y_val)], verbose=False)
        return float(np.sqrt(mean_squared_error(y_val, model.predict(X_val))))

    study = optuna.create_study(direction="minimize")
    study.optimize(objective, n_trials=n_trials, timeout=timeout)
    return study.best_params


def roc_data(y_true, proba):
    """One-vs-rest ROC per class + macro. proba shape [n, 4]."""
    yb = label_binarize(y_true, classes=CLASSES)
    out = {}
    aucs = []
    for k in CLASSES:
        fpr, tpr, _ = roc_curve(yb[:, k], proba[:, k])
        a = float(auc(fpr, tpr)); aucs.append(a)
        out[str(k)] = {"fpr": fpr.tolist(), "tpr": tpr.tolist(), "auc": a}
    out["macro"] = float(np.mean(aucs))
    return out


def shap_values(model, X):
    try:
        import shap
        return shap.TreeExplainer(model).shap_values(X)
    except Exception as e:  # pragma: no cover
        print("shap unavailable:", e); return None


def lime_explain(model, X_train, x_instance, num_features=10):
    try:
        import lime, lime.lime_tabular
        expl = lime.lime_tabular.LimeTabularExplainer(
            X_train, feature_names=FEATURE_NAMES, mode="regression")
        return expl.explain_instance(x_instance, model.predict, num_features=num_features).as_list()
    except Exception as e:  # pragma: no cover
        print("lime unavailable:", e); return None


def walk_forward_cv(make_model, X, y, n_splits=10):
    tscv = TimeSeriesSplit(n_splits=n_splits)
    rmses = []
    for tr, te in tscv.split(X):
        m = make_model(); m.fit(X[tr], y[tr])
        rmses.append(float(np.sqrt(mean_squared_error(y[te], m.predict(X[te])))))
    return {"cv_mean_rmse": float(np.mean(rmses)), "cv_std_rmse": float(np.std(rmses))}


if __name__ == "__main__":
    rng = np.random.default_rng(0)
    X = rng.normal(size=(400, len(FEATURE_NAMES)))
    y = X[:, 14] * 8 + X[:, 9] * 4 + rng.normal(scale=3, size=400) + 90
    rf = train_random_forest(X[:300], y[:300])
    print("RF metrics:", metrics(y[300:], rf.predict(X[300:])))
    print("CV:", walk_forward_cv(lambda: RandomForestRegressor(n_estimators=80, random_state=0), X, y, 5))


# ============================================================================
# PART 41 — SHAP INTERACTION VALUES (extension, ne réécrit pas le fichier)
# ----------------------------------------------------------------------------
# Détecte les interactions entre polluants (ex: SO2 × humidité) au-delà du
# SHAP simple. Un pic de SO2 par temps humide n'a pas le même impact qu'un pic
# par temps sec — scientifiquement pertinent pour Gabès (zone industrielle
# côtière). Dégrade proprement si shap est absent.
# ============================================================================
from datetime import datetime as _dt_part41


def shap_interaction(model, X, top_n: int = 5):
    """Retourne les top_n paires de features aux plus fortes interactions.

    model : modèle arbre (XGBoost / RandomForest / LightGBM)
    X     : DataFrame des features
    return: list[dict] {feature_a, feature_b, interaction_strength}
    """
    try:
        import shap
        import numpy as np
    except Exception as e:  # pragma: no cover
        print(f"[shap_interaction] shap indisponible: {e}")
        return []
    try:
        explainer = shap.TreeExplainer(model)
        inter = explainer.shap_interaction_values(X)
        if isinstance(inter, list):  # multiclasse -> on agrège
            inter = np.mean([np.abs(m) for m in inter], axis=0)
        else:
            inter = np.abs(inter)
        mean_inter = inter.mean(axis=0)  # (n_features, n_features)
        cols = list(X.columns)
        pairs = []
        for i in range(len(cols)):
            for j in range(i + 1, len(cols)):
                pairs.append({
                    "feature_a": cols[i],
                    "feature_b": cols[j],
                    "interaction_strength": float(mean_inter[i, j]),
                })
        pairs.sort(key=lambda p: p["interaction_strength"], reverse=True)
        return pairs[:top_n]
    except Exception as e:  # pragma: no cover
        print(f"[shap_interaction] échec: {e}")
        return []


def store_shap_interactions(db, model, X, zone_id: int, top_n: int = 5):
    """Calcule et écrit les interactions dans xai_interactions (best effort)."""
    pairs = shap_interaction(model, X, top_n)
    if not pairs or db is None:
        return pairs
    try:
        cur = db.cursor()
        now = _dt_part41.now().strftime("%Y-%m-%d %H:%M:%S")
        for rank, p in enumerate(pairs, start=1):
            cur.execute(
                "INSERT INTO xai_interactions "
                "(zone_id, computed_at, feature_a, feature_b, interaction_strength, rank_order) "
                "VALUES (%s,%s,%s,%s,%s,%s)",
                (zone_id, now, p["feature_a"], p["feature_b"],
                 p["interaction_strength"], rank))
        db.commit()
    except Exception as e:  # pragma: no cover
        print(f"[shap_interaction] insert: {e}")
    return pairs
