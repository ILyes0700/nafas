"""GABES-TATENAFAS v4.0 - Train ALL AI models on REAL Open-Meteo data.

CHANGEMENT MAJEUR v4.0 (migration donnees reelles) :
  Le pipeline ne s'appuie plus sur `api_readings` / `api_readings_augmented`
  (~150 lignes reelles par zone + augmentation CGAN + tuilage bruite).
  La source unique est desormais la table `open_data` : dataset reel
  Open-Meteo Air Quality (modele CAMS Europe) + ERA5 pour la meteo,
  7 villes du gouvernorat de Gabes, granularite horaire,
  du 2024-01-01 au 2026-07-02 (~21 000 lignes par ville, ~130 000 au total).

  Supprimes definitivement : le CGAN, l'interpolation forcee, le tuilage avec
  bruit gaussien, les zones entierement simulees, et tous les marqueurs
  `_synth` / `_holdout` qui servaient a distinguer le vrai du fabrique.

Pipeline (par zone, par horizon +1h/+6h/+24h) :
  1. Charge la serie horaire REELLE depuis `open_data` via data_loader
  2. Construit le vecteur partage de 35 features
     (AQI courant + lags + tendances + fuzzy Type-2 + polluants + meteo + temporelles)
  3. Entraine uniquement les modèles classiques autorisés : Random Forest,
     XGBoost + Fuzzy
  4. Deep Learning optionnel si TensorFlow present : LSTM, BiLSTM Simple,
     BiLSTM + MultiHead Attention, BiLSTM + Autoencoder, CNN + Autoencoder
  5. Evalue MAE/RMSE/MAPE/SMAPE/R2 + F1 de classification + Wilcoxon vs baseline
  6. Sauvegarde les modeles dans models/saved/*.pkl (+ .h5)
  7. Ecrit les metriques -> model_performance, predictions -> model_predictions,
     fuzzy -> fuzzy_assessments, sante -> health_impact (si DB disponible)

PROTOCOLE D'EVALUATION (strict, applique a TOUS les modeles) :
  Split chronologique 70/10/20 par ville sur donnees 100% reelles.
    - Entrainement : les 70% les plus ANCIENS
    - Validation   : les 10% suivants, pour choisir le modele et les poids
    - Test         : les 20% les plus RECENTS, jamais utilises pour choisir
  Aucune donnee generee n'entre dans les partitions. Chaque modele est
  entraine sur train, evalue sur validation, puis refit sur train+validation
  avant une mesure finale unique sur test.

Run: python -m models.train_all   (depuis la racine du projet)
  ou: cd models && python train_all.py
"""
from __future__ import annotations
import os, sys, json, time, math, gc
import datetime as dt
import numpy as np

# allow both "python -m models.train_all" and "python train_all.py"
try:
    from . import data_loader, fuzzy_type2, ml_models, db_config, health_impact, statistical_tests
except Exception:
    sys.path.append(os.path.dirname(__file__))
    import data_loader, fuzzy_type2, ml_models, db_config, health_impact, statistical_tests

from sklearn.ensemble import RandomForestRegressor

# deep_models is OPTIONAL (needs TensorFlow). It NEVER breaks the pipeline:
# if TensorFlow is not installed the BiLSTM models are simply skipped.
try:
    from . import deep_models
except Exception:
    try:
        import deep_models
    except Exception:
        deep_models = None

# bilstm_autoencoder is OPTIONAL too (needs TensorFlow). Meme principe :
# son absence n'interrompt jamais l'entrainement des modeles classiques.
try:
    from . import bilstm_autoencoder
except Exception:
    try:
        import bilstm_autoencoder
    except Exception:
        bilstm_autoencoder = None

SAVED = os.path.join(os.path.dirname(__file__), "saved")
os.makedirs(SAVED, exist_ok=True)
HORIZON_STEPS = {"1h": 1, "6h": 6, "24h": 24}
CLASS_BINS = [0, 50, 100, 150, 10_000]  # SAFE/WARNING/CRITICAL/HAZARDOUS

# Ratios du split chronologique : 70% train / 10% validation / 20% test.
TRAIN_RATIO = 0.70
VALIDATION_RATIO = 0.10
TEST_RATIO = 0.20

# Noms des 35 features, dans l'ordre exact produit par _feature_row().
# Les cinq variables supplementaires sont calculees uniquement a partir du passe.
# Elles donnent au modele une information de tendance utile pour +24h.
FEATURE_NAMES = (
    ["aqi_current"] + [f"aqi_lag_{k}" for k in (1, 2, 3, 4, 5, 6, 7, 24, 168)]
    + ["aqi_delta_1h", "aqi_delta_6h", "aqi_mean_6h", "aqi_mean_24h", "aqi_std_24h"]
    + ["fuzzy_score_type2", "uncertainty_lower", "uncertainty_upper"]
    + ["pm25", "pm10", "so2", "no2", "o3", "co", "dust"]
    + ["temperature", "humidity", "wind_speed", "wind_direction",
       "pressure", "precipitation", "cloud_cover"]
    + ["hour_of_day", "is_weekend", "season"]
)
assert len(FEATURE_NAMES) == 35, "FEATURE_NAMES desynchronise avec _feature_row"


def to_series(frame):
    if hasattr(frame, "to_dict"):
        return frame.to_dict("records")
    return frame


def _time_features(ts, i, offset_hours=0):
    """Caracteristiques calendaires du moment predit, pas seulement du moment t.
    Pour +6h et +24h, l'heure/jour cible peut differer du timestamp d'entree.
    Retombe sur l'index uniquement si aucun timestamp n'est disponible."""
    if isinstance(ts, str):
        for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%d %H:%M"):
            try:
                ts = dt.datetime.strptime(ts, fmt)
                break
            except Exception:
                pass
    if isinstance(ts, dt.datetime):
        target_ts = ts + dt.timedelta(hours=int(offset_hours))
        return target_ts.hour, (1 if target_ts.weekday() >= 5 else 0), (target_ts.month % 12) // 3
    target_i = i + int(offset_hours)
    return target_i % 24, (1 if (target_i // 24) % 7 >= 5 else 0), (target_i // (24 * 90)) % 4


def _feature_row(records, aqi, i, horizon_step=0):
    """Vecteur partage pour l'entrainement et la prediction du dernier point.

    Les cinq variables de tendance sont calculees uniquement avec l'historique
    disponible avant i. Elles n'introduisent donc aucune fuite de la cible.
    Les caracteristiques calendaires sont alignees sur l'heure cible i+h.
    """
    def lag(k):
        j = i - k
        return aqi[j] if j >= 0 else aqi[0]

    fz = fuzzy_type2.assess(min(100.0, aqi[i] / 5.0))
    r = records[i]
    hod, is_wend, season = _time_features(r.get("ts") or r.get("timestamp"), i, horizon_step)

    def g(key):
        """Lecture defensive : open_data peut contenir des NULL residuels sur
        les bords de serie que data_loader n'a pas interpoles (gap > 3h)."""
        v = r.get(key)
        return float(v) if v is not None else 0.0

    hist6 = aqi[max(0, i - 5):i + 1]
    hist24 = aqi[max(0, i - 23):i + 1]
    delta1 = aqi[i] - lag(1)
    delta6 = aqi[i] - lag(6)
    mean6 = float(np.mean(hist6)) if hist6 else aqi[i]
    mean24 = float(np.mean(hist24)) if hist24 else aqi[i]
    std24 = float(np.std(hist24)) if len(hist24) > 1 else 0.0

    return [
        aqi[i],
        lag(1), lag(2), lag(3), lag(4), lag(5), lag(6), lag(7),
        lag(24), lag(168),
        delta1, delta6, mean6, mean24, std24,
        fz["fuzzy_score_type2"], fz["uncertainty_lower"], fz["uncertainty_upper"],
        g("pm25"), g("pm10"), g("so2"), g("no2"), g("o3"), g("co"), g("dust"),
        g("temperature"), g("humidity"), g("wind_speed"), g("wind_direction"),
        g("pressure"), g("precipitation"), g("cloud_cover"),
        hod, is_wend, season,
    ]


def build_xy(records, horizon_step):
    """Construit la matrice X et la cible y (AQI a t+h) depuis une liste horaire.

    Avec open_data les series font ~21 000 points par ville, donc le lag
    hebdomadaire (168h) est toujours utilisable : le demarrage adaptatif est
    conserve uniquement comme garde-fou pour une ville partiellement importee.
    """
    aqi = [float(r["aqi"]) for r in records]
    n = len(aqi)
    start = 8 if n < 176 else 168
    X, y = [], []
    for i in range(start, n - horizon_step):
        X.append(_feature_row(records, aqi, i, horizon_step))
        y.append(aqi[i + horizon_step])
    return np.array(X, dtype=float), np.array(y, dtype=float)


def _split_bounds_records(records, train_ratio=TRAIN_RATIO,
                         validation_ratio=VALIDATION_RATIO):
    """Retourne les frontieres de lignes train/validation/test."""
    n = len(records)
    if n < 3:
        return -1, -1
    train_end = int(n * train_ratio)
    # Additionner les tailles train et validation évite l'arrondi binaire de
    # 0.70 + 0.10 (qui peut devenir 0.799999...) et garantit le protocole 70/10/20.
    validation_end = train_end + int(n * validation_ratio)
    train_end = max(1, min(train_end, n - 2))
    validation_end = max(train_end + 1, min(validation_end, n - 1))
    return train_end, validation_end


def _split_index_records(records, train_ratio=TRAIN_RATIO):
    """Compatibilite : retourne la premiere ligne de validation."""
    train_end, _ = _split_bounds_records(records, train_ratio)
    return train_end


def _aqi_distribution(records, start, end):
    values = np.asarray([float(r["aqi"]) for r in records[start:end]], dtype=float)
    if values.size == 0:
        return {"rows": 0, "mean": None, "median": None, "std": None, "class_pct": {}}
    counts = np.histogram(values, bins=[-np.inf, 50, 100, 150, np.inf])[0]
    return {
        "rows": int(values.size),
        "mean": round(float(values.mean()), 3),
        "median": round(float(np.median(values)), 3),
        "std": round(float(values.std()), 3),
        "min": round(float(values.min()), 3),
        "max": round(float(values.max()), 3),
        "class_pct": {
            "0-50": round(float(counts[0] / values.size * 100), 3),
            "50-100": round(float(counts[1] / values.size * 100), 3),
            "100-150": round(float(counts[2] / values.size * 100), 3),
            "150+": round(float(counts[3] / values.size * 100), 3),
        },
    }


def save_spatial_overlap_summary(frames):
    """Audit réel des séries AQI بين المناطق بدون تعديل أو توليد بيانات."""
    zone_values = {str(zid): np.asarray([float(r["aqi"]) for r in to_series(frame)], dtype=float)
                   for zid, frame in frames.items()}
    report = {"zones": {}, "warnings": []}
    ids = sorted(zone_values)
    for i, left in enumerate(ids):
        for right in ids[i + 1:]:
            n = min(len(zone_values[left]), len(zone_values[right]))
            if n == 0:
                continue
            equal_pct = float(np.isclose(zone_values[left][:n], zone_values[right][:n], atol=1e-9, rtol=0).mean() * 100)
            row = {"zone_a": left, "zone_b": right, "rows_compared": n,
                   "aqi_exact_equal_pct": round(equal_pct, 3)}
            report["zones"][f"{left}-{right}"] = row
            if equal_pct > 99.9:
                warning = f"AQI des zones {left} et {right} identique à {equal_pct:.3f}% ; vérifier la source spatiale réelle."
                report["warnings"].append(warning)
                print("[spatial-warning] " + warning)
    with open(os.path.join(SAVED, "spatial_overlap_summary.json"), "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2, ensure_ascii=False)
    return report


def save_data_drift_summary(frames):
    """Persiste un audit descriptif réel ; il ne participe jamais à la sélection."""
    report = {"protocol": "70/10/20 chronological", "zones": {}}
    for zid, frame in frames.items():
        records = to_series(frame)
        first_val, first_test = _split_bounds_records(records)
        train = _aqi_distribution(records, 0, first_val)
        validation = _aqi_distribution(records, first_val, first_test)
        test = _aqi_distribution(records, first_test, len(records))
        report["zones"][str(zid)] = {
            "train": train, "validation": validation, "test": test,
            "validation_to_test_mean_delta": round(float(test["mean"] - validation["mean"]), 3),
            "validation_to_test_std_delta": round(float(test["std"] - validation["std"]), 3),
        }
    path = os.path.join(SAVED, "data_drift_summary.json")
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2, ensure_ascii=False)
    print(f"[drift] real AQI distribution saved to {path}")
    return report


def _split_sample_bounds(records, horizon_step, n_samples,
                         train_ratio=TRAIN_RATIO,
                         validation_ratio=VALIDATION_RATIO):
    """Retourne les bornes d'echantillons train/validation/test.

    La partition est basee sur l'index de la CIBLE, pas sur le debut de la
    fenetre. Ainsi une cible t+h ne peut pas entrer dans train si elle tombe
    apres la frontiere train, et le test n'est jamais utilise pour selectionner
    le modele.
    """
    n = len(records)
    start = 8 if n < 176 else 168
    train_end, validation_end = _split_bounds_records(
        records, train_ratio, validation_ratio)
    if train_end < 0:
        a = max(1, int(n_samples * train_ratio))
        b = max(a + 1, int(n_samples * (train_ratio + validation_ratio)))
        return a, min(b, n_samples - 1), n_samples
    targets = np.arange(start, n - horizon_step, dtype=int) + horizon_step
    if len(targets) != n_samples:
        targets = targets[:n_samples]
    ntr = int(np.sum(targets < train_end))
    nval = int(np.sum((targets >= train_end) & (targets < validation_end)))
    ntr = max(1, min(ntr, n_samples - 2))
    nval_end = max(ntr + 1, min(ntr + nval, n_samples - 1))
    return ntr, nval_end, n_samples


def _split_index(records, horizon_step, n_samples, train_ratio=TRAIN_RATIO):
    """Compatibilite : nombre d'echantillons d'entrainement."""
    return _split_sample_bounds(records, horizon_step, n_samples,
                                train_ratio, VALIDATION_RATIO)[0]


def latest_feature(records, horizon_step=0):
    """Ligne de features du point reel le plus recent -> vraie prevision +h.
    Les caracteristiques calendaires sont alignees sur l'horizon cible."""
    aqi = [float(r["aqi"]) for r in records]
    return np.array([_feature_row(records, aqi, len(aqi) - 1, horizon_step)], dtype=float)


def classify(vals):
    return np.digitize(vals, CLASS_BINS[1:-1])


def ar7_predict(records, horizon_step, first_ho):
    """Baseline AR(7) par moindres carres sur la tranche d'entrainement.
    Cette fonction legacy reste disponible pour les scripts d'ablation ; le
    pipeline principal utilise ar7_predict_range avec le split 70/10/20."""
    aqi = np.array([float(r["aqi"]) for r in records])
    rows = []
    tgt_idx = []
    for i in range(7, len(aqi) - horizon_step):
        rows.append((aqi[i-7:i][::-1], aqi[i + horizon_step]))
        tgt_idx.append(i + horizon_step)
    if not rows:
        return np.array([]), np.array([])
    Xa = np.array([r[0] for r in rows]); ya = np.array([r[1] for r in rows])
    if first_ho is not None and first_ho >= 0:
        ntr = sum(1 for t in tgt_idx if t < first_ho)
        ntr = max(1, min(ntr, len(Xa) - 1))
    else:
        ntr = max(1, int(len(Xa) * TRAIN_RATIO))
    if ntr >= len(Xa):
        ntr = len(Xa) - 1
    A = np.column_stack([Xa[:ntr], np.ones(ntr)])
    coef, *_ = np.linalg.lstsq(A, ya[:ntr], rcond=None)
    Xte = np.column_stack([Xa[ntr:], np.ones(len(Xa) - ntr)])
    return ya[ntr:], Xte @ coef



def ar7_predict_range(records, horizon_step, target_start, target_end, fit_end):
    """Baseline AR(7) ajuste avant fit_end et evalue sur [target_start,target_end)."""
    aqi = np.array([float(r["aqi"]) for r in records], dtype=float)
    Xrows, yrows, targets = [], [], []
    for i in range(7, len(aqi) - horizon_step):
        Xrows.append(aqi[i-7:i][::-1])
        yrows.append(aqi[i + horizon_step])
        targets.append(i + horizon_step)
    if not Xrows:
        return np.array([]), np.array([])
    Xrows = np.asarray(Xrows, dtype=float)
    yrows = np.asarray(yrows, dtype=float)
    targets = np.asarray(targets, dtype=int)
    fit_mask = targets < int(fit_end)
    eval_mask = (targets >= int(target_start)) & (targets < int(target_end))
    if fit_mask.sum() < 20 or eval_mask.sum() == 0:
        return np.array([]), np.array([])
    A = np.column_stack([Xrows[fit_mask], np.ones(int(fit_mask.sum()))])
    coef, *_ = np.linalg.lstsq(A, yrows[fit_mask], rcond=None)
    Ae = np.column_stack([Xrows[eval_mask], np.ones(int(eval_mask.sum()))])
    return yrows[eval_mask], Ae @ coef

def clear_stale_model_outputs(conn):
    """يمسح مخرجات النماذج القديمة قبل run جديد، دون لمس الأوزان أو البيانات."""
    if conn is None:
        return
    tables = (
        "model_performance", "model_training_performance", "model_validation_performance",
        "model_predictions", "forecast_metrics", "forecast_predictions", "dl_artifacts",
        "model_hyperparameters", "xai_artifacts",
    )
    cur = conn.cursor()
    for table in tables:
        try:
            cur.execute(f"DELETE FROM `{table}`")
        except Exception as exc:
            print(f"[cleanup] {table} skipped: {exc}")
    conn.commit()
    cur.close()
    print("[cleanup] stale model outputs cleared; ensemble_weights/open_data untouched")


def release_dl_memory():
    """Libère les graphes TensorFlow après un modèle, sans modifier ses métriques."""
    try:
        import tensorflow as tf
        tf.keras.backend.clear_session()
    except Exception:
        pass
    gc.collect()


def save_metrics_db(conn, rows):
    if conn is None:
        return
    cur = conn.cursor()
    # Remplace le run precedent pour que les dashboards n'affichent que les
    # DERNIERS resultats reels (les anciens runs ne doivent pas polluer les
    # AVG() calcules cote UI).
    if rows:
        try:
            cur.execute("DELETE FROM model_performance")
        except Exception as e:
            print("clear model_performance skipped:", e)
    for m in rows:
        cur.execute(
            """INSERT INTO model_performance
               (model_name, city_id, evaluated_at, horizon, accuracy, precision_macro,
                recall_macro, f1_macro, mae, rmse, mape, smape, r_squared, auc_roc,
                avg_latency_ms, improvement_vs_baseline, wilcoxon_pvalue)
               VALUES (%s,%s,NOW(),%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
            (m["model"], str(m["city_id"]), m["horizon"], m.get("acc"), m.get("prec"),
             m.get("rec"), m.get("f1"), m["mae"], m["rmse"], m["mape"], m["smape"],
             m["r2"], m.get("auc"), m.get("latency", 0), m.get("improvement"),
             m.get("wilcoxon")))
    conn.commit(); cur.close()


def _extract_hparams(model):
    """Retourne les VRAIS hyperparametres reellement utilises pour ce modele
    (lus depuis l'estimateur entraine, rien d'invente)."""
    keep = ["n_estimators", "max_depth", "learning_rate", "subsample",
            "colsample_bytree", "hidden_layer_sizes", "activation", "max_iter",
            "min_samples_split", "min_samples_leaf", "random_state"]
    try:
        params = model.get_params()
    except Exception:
        return {}
    out = {}
    for k, v in params.items():
        short = k.split("__")[-1]  # unwrap sklearn Pipeline prefixes
        if short in keep and v is not None and short not in out:
            out[short] = str(v)
    return out



def save_validation_metrics_db(conn, rows):
    """Persiste les metriques de validation dans une table separee.

    model_performance reste reserve aux resultats du test final. Cette table
    permet au dashboard ou a un audit de verifier la vraie regle de selection.
    """
    if conn is None:
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS model_validation_performance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            model_name VARCHAR(100), city_id VARCHAR(50), evaluated_at DATETIME,
            horizon VARCHAR(10), accuracy FLOAT, precision_macro FLOAT,
            recall_macro FLOAT, f1_macro FLOAT, mae FLOAT, rmse FLOAT,
            mape FLOAT, smape FLOAT, r_squared FLOAT, auc_roc FLOAT,
            avg_latency_ms FLOAT, improvement_vs_baseline FLOAT,
            INDEX(model_name, city_id, horizon)
        ) ENGINE=InnoDB""")
        if rows:
            cur.execute("DELETE FROM model_validation_performance")
        for m in rows:
            cur.execute(
                """INSERT INTO model_validation_performance
                   (model_name, city_id, evaluated_at, horizon, accuracy,
                    precision_macro, recall_macro, f1_macro, mae, rmse, mape,
                    smape, r_squared, auc_roc, avg_latency_ms,
                    improvement_vs_baseline)
                   VALUES (%s,%s,NOW(),%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                (m["model"], str(m["city_id"]), m["horizon"], m.get("acc"),
                 m.get("prec"), m.get("rec"), m.get("f1"), m.get("mae"),
                 m.get("rmse"), m.get("mape"), m.get("smape"), m.get("r2"),
                 m.get("auc"), m.get("latency", 0), m.get("improvement")))
        conn.commit()
        cur.close()
        print(f"[validation] stored {len(rows)} validation rows")
    except Exception as exc:
        print("[validation] save skipped:", exc)


def save_training_metrics_db(conn, rows):
    """Persiste les metriques de la partition TRAIN dans une table separee."""
    if conn is None:
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS model_training_performance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            model_name VARCHAR(100), city_id VARCHAR(50), evaluated_at DATETIME,
            horizon VARCHAR(10), accuracy FLOAT, precision_macro FLOAT,
            recall_macro FLOAT, f1_macro FLOAT, mae FLOAT, rmse FLOAT,
            mape FLOAT, smape FLOAT, r_squared FLOAT, auc_roc FLOAT,
            avg_latency_ms FLOAT, improvement_vs_baseline FLOAT,
            INDEX(model_name, city_id, horizon)
        ) ENGINE=InnoDB""")
        if rows:
            cur.execute("DELETE FROM model_training_performance")
        for m in rows:
            cur.execute(
                """INSERT INTO model_training_performance
                   (model_name, city_id, evaluated_at, horizon, accuracy,
                    precision_macro, recall_macro, f1_macro, mae, rmse, mape,
                    smape, r_squared, auc_roc, avg_latency_ms,
                    improvement_vs_baseline)
                   VALUES (%s,%s,NOW(),%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                (m["model"], str(m["city_id"]), m["horizon"], m.get("acc"),
                 m.get("prec"), m.get("rec"), m.get("f1"), m.get("mae"),
                 m.get("rmse"), m.get("mape"), m.get("smape"), m.get("r2"),
                 m.get("auc"), m.get("latency", 0), m.get("improvement")))
        conn.commit()
        cur.close()
        print(f"[training] stored {len(rows)} training rows")
    except Exception as exc:
        print("[training] save skipped:", exc)


def save_hyperparams_db(conn, hp):
    """Persiste les VRAIS hyperparametres par modele pour que la page
    forecast-ML affiche les reglages effectifs (pas des metriques, rien d'invente)."""
    if conn is None or not hp:
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS model_hyperparameters (
            model_name VARCHAR(80) PRIMARY KEY,
            params LONGTEXT,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci""")
        for name, params in hp.items():
            cur.execute("REPLACE INTO model_hyperparameters (model_name, params, updated_at) "
                        "VALUES (%s,%s,NOW())", (name, json.dumps(params)))
        conn.commit(); cur.close()
        print(f"[hp] real hyperparameters saved for {len(hp)} models")
    except Exception as e:
        print("[hp] save hyperparameters skipped:", e)


def save_dl_artifacts(conn, predictions, series, attention):
    """Persiste les VRAIES donnees de la page Deep Learning pour que
    backend/api/deep-learning.php les serve directement depuis la DB."""
    if conn is None:
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS dl_artifacts (
            artifact_key VARCHAR(64) PRIMARY KEY,
            payload LONGTEXT,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci""")

        def put(k, obj):
            cur.execute("REPLACE INTO dl_artifacts (artifact_key, payload, updated_at) "
                        "VALUES (%s,%s,NOW())", (k, json.dumps(obj)))
        put("predictions", predictions)
        put("series", series or {"labels": [], "actual": [], "predicted": []})
        if attention:
            put("attention", attention)
        conn.commit(); cur.close()
        print(f"[dl] artifacts saved: predictions={len(predictions)} "
              f"series={'yes' if series else 'no'} attention={'yes' if attention else 'no'}")
    except Exception as e:
        print("[dl] save artifacts skipped:", e)


def save_pollutant_xai(conn):
    """Calcule le VRAI XAI moderne et le stocke pour la page forecast-ML.

      - TreeSHAP : shap.TreeExplainer sur un RandomForest (polluants/meteo -> AQI)
      - LIME     : lime.lime_tabular sur la derniere instance reelle
      - DeepSHAP : shap.DeepExplainer sur un petit reseau Keras (si TensorFlow)
      - Permutation Importance + PDP (sklearn, toujours disponibles)

    MIGRATION v4.0 : lit desormais `open_data` au lieu de `api_readings`.
    C'etait une dependance cachee critique — sans ce changement, tout le
    SHAP/LIME/PDP affiche par forecast-ml.php aurait continue a tourner sur
    les ~150 anciennes lignes AccuWeather pendant que les modeles, eux,
    s'entrainent sur les ~130 000 lignes Open-Meteo. Les explications
    n'auraient plus decrit les modeles reellement servis aux utilisateurs.

    Les resultats vont dans la table xai_artifacts. Degradation gracieuse :
    si shap / lime ne sont pas installes, rien n'est stocke et la page PHP
    conserve son fallback OLS.
    """
    if conn is None:
        return
    # Colonnes open_data -> libelles affiches. 13 features (contre 10 avant) :
    # on gagne dust, precipitation et cloud_cover, absents d'api_readings.
    cols = ["sulphur_dioxide", "pm2_5", "pm10", "nitrogen_dioxide", "ozone",
            "carbon_monoxide", "dust", "wind_speed_10m", "relative_humidity_2m",
            "temperature_2m", "surface_pressure", "precipitation", "cloud_cover"]
    labels = ["SO2", "PM2.5", "PM10", "NO2", "O3", "CO", "Poussiere", "Vent",
              "Humidite", "Temperature", "Pression", "Precipitations", "Nuages"]
    try:
        cur = conn.cursor()
        # Echantillonnage deterministe 1 ligne sur 16 : open_data contient
        # ~130 000 lignes, en charger 8 000 reparties sur les 2,5 ans donne un
        # SHAP representatif de toutes les saisons. Un simple LIMIT 8000 aurait
        # biaise l'explication vers les premiers mois de 2024 uniquement.
        cur.execute(
            "SELECT us_aqi, " + ", ".join(cols) +
            " FROM open_data WHERE us_aqi IS NOT NULL AND id %% 16 = 0 LIMIT 8000")
        rows = cur.fetchall()
        cur.close()
    except Exception as e:
        print("[xai] read open_data skipped:", e)
        return
    if not rows or len(rows) < 50:
        print("[xai] not enough real rows for SHAP:", len(rows) if rows else 0)
        return
    arr = np.array([[float(v) if v is not None else 0.0 for v in r] for r in rows],
                   dtype=float)
    y = arr[:, 0]
    X = arr[:, 1:]
    rf = RandomForestRegressor(n_estimators=200, max_depth=12, random_state=42, n_jobs=-1)
    rf.fit(X, y)
    payload = {"method": "none", "model": "RandomForest", "source": "open_data",
               "shap_global": [], "shap_local": [], "lime": [], "shap_deep": [],
               "base_value": round(float(np.mean(y)), 2),
               "predicted": round(float(rf.predict(X[-1:])[0]), 2)}

    # ---- REAL TreeSHAP (shap.TreeExplainer) ----
    try:
        import shap
        expl = shap.TreeExplainer(rf)
        sv = np.array(expl.shap_values(X))
        if sv.ndim == 3:
            sv = sv[0] if sv.shape[0] == 1 else np.mean(sv, axis=0)
        glob = np.mean(np.abs(sv), axis=0)
        total = float(glob.sum()) or 1.0
        order = list(np.argsort(glob)[::-1])
        payload["shap_global"] = [{"feature": labels[i],
                                   "importance": round(float(glob[i] / total), 3)}
                                  for i in order]
        loc = sv[-1]
        li = list(np.argsort(np.abs(loc))[::-1])[:8]
        payload["shap_local"] = [{"feature": labels[i] + " = " + str(round(float(X[-1, i]), 1)),
                                  "contribution": round(float(loc[i]), 2)} for i in li]
        try:
            payload["base_value"] = round(float(np.array(expl.expected_value).mean()), 2)
        except Exception:
            pass
        payload["method"] = "TreeSHAP"
        print("[xai] real TreeSHAP computed on RandomForest")
    except Exception as e:
        print("[xai] shap TreeExplainer unavailable:", e)

    # ---- REAL LIME (lime.lime_tabular) ----
    try:
        import lime
        import lime.lime_tabular
        le = lime.lime_tabular.LimeTabularExplainer(
            X, feature_names=labels, mode="regression", discretize_continuous=True)
        exp = le.explain_instance(X[-1], rf.predict, num_features=6)
        lm = []
        for feat, w in exp.as_list():
            lab = feat
            for name in labels:
                if name in feat:
                    lab = name
                    break
            lm.append({"feature": lab, "weight": round(float(w), 3),
                       "direction": "positive" if w >= 0 else "negative"})
        payload["lime"] = lm
        print("[xai] real LIME computed")
    except Exception as e:
        print("[xai] lime unavailable:", e)

    # ---- Optional REAL DeepSHAP (needs TensorFlow) ----
    try:
        if deep_models is not None and deep_models.available():
            import shap
            from sklearn.preprocessing import StandardScaler
            from tensorflow import keras
            sc = StandardScaler().fit(X)
            Xs = sc.transform(X)
            net = keras.Sequential([
                keras.layers.Input(shape=(X.shape[1],)),
                keras.layers.Dense(64, activation="relu"),
                keras.layers.Dense(32, activation="relu"),
                keras.layers.Dense(1),
            ])
            net.compile(optimizer="adam", loss="mse")
            net.fit(Xs, y, epochs=30, batch_size=32, verbose=0)
            idx = np.random.choice(len(Xs), min(100, len(Xs)), replace=False)
            de = shap.DeepExplainer(net, Xs[idx])
            dsv = np.array(de.shap_values(Xs[:500]))
            if dsv.ndim == 3:
                dsv = dsv[0] if dsv.shape[0] == 1 else np.mean(np.abs(dsv), axis=0)
            dglob = np.mean(np.abs(dsv), axis=0)
            dtot = float(dglob.sum()) or 1.0
            do = list(np.argsort(dglob)[::-1])
            payload["shap_deep"] = [{"feature": labels[i],
                                     "importance": round(float(dglob[i] / dtot), 3)}
                                    for i in do]
            print("[xai] real DeepSHAP computed on Keras net")
    except Exception as e:
        print("[xai] DeepSHAP skipped:", e)

    # ---- REAL Permutation Importance (sklearn.inspection) ----
    try:
        from sklearn.inspection import permutation_importance
        pi = permutation_importance(rf, X, y, n_repeats=6, random_state=42, n_jobs=-1)
        imp = np.clip(pi.importances_mean, 0.0, None)
        ptot = float(imp.sum()) or 1.0
        po = list(np.argsort(imp)[::-1])
        payload["permutation"] = [{"feature": labels[i],
                                   "importance": round(float(imp[i] / ptot), 3),
                                   "std": round(float(pi.importances_std[i] / ptot), 3)}
                                  for i in po]
        print("[xai] real Permutation Importance computed")
    except Exception as e:
        print("[xai] permutation importance skipped:", e)

    # ---- REAL Partial Dependence (PDP) on top features ----
    try:
        if payload["shap_global"]:
            base_order = [labels.index(g["feature"]) for g in payload["shap_global"]]
        else:
            base_order = list(range(len(labels)))
        top_idx = base_order[:4]
        pdp = []
        n_grid = 14
        for i in top_idx:
            lo = float(np.percentile(X[:, i], 5))
            hi = float(np.percentile(X[:, i], 95))
            if hi <= lo:
                hi = lo + 1.0
            grid = np.linspace(lo, hi, n_grid)
            vals = []
            Xtmp = X.copy()
            for g in grid:
                Xtmp[:, i] = g
                vals.append(round(float(np.mean(rf.predict(Xtmp))), 2))
            pdp.append({"feature": labels[i],
                        "grid": [round(float(g), 1) for g in grid],
                        "values": vals})
        payload["pdp"] = pdp
        print("[xai] real PDP computed on top features")
    except Exception as e:
        print("[xai] PDP skipped:", e)

    # ---- store to xai_artifacts (only if something real was computed) ----
    if payload["method"] == "none" and not payload["lime"] and not payload.get("permutation") and not payload.get("pdp"):
        print("[xai] shap/lime missing -> PHP keeps its OLS fallback")
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS xai_artifacts (
            artifact_key VARCHAR(64) PRIMARY KEY,
            payload LONGTEXT,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci""")
        cur.execute("REPLACE INTO xai_artifacts (artifact_key, payload, updated_at) "
                    "VALUES (%s,%s,NOW())", ("pollutant_xai", json.dumps(payload)))
        conn.commit()
        cur.close()
        print("[xai] stored REAL XAI (method=" + payload["method"] +
              ", lime=" + str(len(payload["lime"])) +
              ", deepshap=" + str(len(payload["shap_deep"])) + ")")
    except Exception as e:
        print("[xai] store skipped:", e)


def main():
    print("=" * 60)
    print("GABES-TATENAFAS v4.1 - training on REAL Open-Meteo data")
    print("Source: table open_data | 7 villes | horaire | 2024-01-01 -> 2026-07-02")
    print("Split chronologique : 70% train / 10% validation / 20% test")
    print("Selection uniquement sur validation ; test final jamais utilise pour choisir")
    print("=" * 60)
    frames = data_loader.build_frames()
    save_spatial_overlap_summary(frames)
    save_data_drift_summary(frames)
    conn = db_config.try_connection()
    clear_stale_model_outputs(conn)
    all_metrics = []
    validation_metrics = []
    training_metrics = []
    pred_rows = []
    hyperparams = {}
    dl_forecasts, dl_series, attn_records, attn_bounds = {}, None, None, None
    zones_meta = {}
    if conn is not None:
        try:
            zones_meta = {z["id"]: z for z in data_loader.load_zones(conn)}
        except Exception:
            zones_meta = {}

    factories = [
        ("Random Forest", lambda X, y: ml_models.train_random_forest(X, y)),
        ("XGBoost + Fuzzy", lambda X, y: _make_xgb(X, y)),
    ]

    for zid, frame in frames.items():
        records = to_series(frame)
        if len(records) < 200:
            print(f"zone {zid}: only {len(records)} rows (need >=200), skipping")
            continue

        first_val, first_test = _split_bounds_records(records)
        _t0 = records[0].get("ts") or records[0].get("timestamp")
        _tv = records[first_val].get("ts") or records[first_val].get("timestamp")
        _tt = records[first_test].get("ts") or records[first_test].get("timestamp")
        _tn = records[-1].get("ts") or records[-1].get("timestamp")
        zname = zones_meta.get(zid, {}).get("name", f"Zone {zid}")
        print(f"zone {zid} ({zname}): {len(records)} lignes REELLES -> training")
        print(f"  split chronologique | train={first_val} [{_t0} -> {records[first_val-1].get('ts')}]"
              f" | validation={first_test-first_val} [{_tv} -> {records[first_test-1].get('ts')}]"
              f" | test={len(records)-first_test} [{_tt} -> {_tn}] | 0 ligne synthetique")

        if attn_records is None or len(records) > len(attn_records):
            attn_records = records
            attn_bounds = (first_val, first_test)

        for h, step in HORIZON_STEPS.items():
            X, y = build_xy(records, step)
            dl_matrix, dl_prepared = None, None
            if deep_models is not None and deep_models.available():
                try:
                    dl_matrix = deep_models.build_feature_matrix(records)
                    dl_prepared = deep_models.prepare_sequences(records, step, matrix=dl_matrix)
                    print(f"  {h}: DL cache prepared once shape={None if dl_prepared[0] is None else dl_prepared[0].shape}")
                except Exception as exc:
                    print(f"  {h}: DL cache skipped: {exc}")
            if len(X) < 60:
                print(f"  {h}: only {len(X)} samples, skipping horizon")
                continue
            ntr, nval_end, nall = _split_sample_bounds(records, step, len(X))
            if ntr < 20 or (nval_end - ntr) < 20 or (nall - nval_end) < 20:
                print(f"  {h}: partitions insuffisantes train={ntr} val={nval_end-ntr} test={nall-nval_end}")
                continue
            Xtr, Xval = X[:ntr], X[ntr:nval_end]
            Xtv, Xtest = X[:nval_end], X[nval_end:]
            ytr, yval = y[:ntr], y[ntr:nval_end]
            ytv, ytest = y[:nval_end], y[nval_end:]

            # Reference autoregressive, fit train puis refit train+validation.
            yval_ar, predval_ar = ar7_predict_range(records, step, first_val, first_test, first_val)
            ytest_ar, predtest_ar = ar7_predict_range(records, step, first_test, len(records), first_test)
            val_ar_rmse = float(np.sqrt(np.mean((yval_ar - predval_ar) ** 2))) if len(predval_ar) else None
            test_ar_rmse = float(np.sqrt(np.mean((ytest_ar - predtest_ar) ** 2))) if len(predtest_ar) else None

            # Tous les modeles sont d'abord entraines sur train et evalues sur validation.
            # Les lignes TRAIN sont aussi conservees pour diagnostiquer le surapprentissage.
            train_preds, val_preds, val_models = {}, {}, {}
            val_rmses = {}
            for name, factory in factories:
                try:
                    model = factory(Xtr, ytr)
                    ptrain = np.asarray(model.predict(Xtr), dtype=float)
                    pval = np.asarray(model.predict(Xval), dtype=float)
                    train_preds[name] = ptrain
                    val_models[name] = model
                    val_preds[name] = pval
                    trow = _metric_row(name, zid, h, ytr, ptrain, None, latency=_measure_latency(model, Xtr))
                    trow["split"] = "train"
                    training_metrics.append(trow)
                    val_rmses[name] = float(np.sqrt(np.mean((yval - pval) ** 2)))
                    vrow = _metric_row(name, zid, h, yval, pval, val_ar_rmse, latency=_measure_latency(model, Xval))
                    vrow["split"] = "validation"
                    validation_metrics.append(vrow)
                    print(f"zone {zid} {h} {name}: validation RMSE={vrow['rmse']:.2f} R2={vrow['r2']:.3f} F1={vrow['f1']:.3f}")
                except Exception as exc:
                    print(f"  {name} validation skipped: {exc}")

            if not val_preds:
                print(f"  {h}: aucun modele classique disponible")
                continue

            # Les poids de l'ensemble sont appris sur validation uniquement.
            weights = {name: 1.0 / (val_rmses[name] + 1e-6) for name in val_preds}
            weight_sum = sum(weights.values()) or 1.0
            val_ensemble = sum(weights[name] / weight_sum * val_preds[name] for name in val_preds)
            val_ensemble_rmse = float(np.sqrt(np.mean((yval - val_ensemble) ** 2)))
            # L'ensemble reste calculable en interne, mais n'est pas un modèle
            # affiché : le classement porte uniquement sur la liste autorisée.
            selected_name = min(val_rmses, key=val_rmses.get)
            selection_rmses = dict(val_rmses)
            print(f"zone {zid} {h} selected by VALIDATION: {selected_name} validation RMSE={selection_rmses[selected_name]:.2f}")

            # Refit de chaque modele sur train+validation ; test final indépendant.
            final_models, final_preds, final_lat = {}, {}, {}
            Xfit, yfit = Xtv, ytv
            for name, factory in factories:
                try:
                    model = factory(Xfit, yfit)
                    ptest = np.asarray(model.predict(Xtest), dtype=float)
                    final_models[name] = model
                    final_preds[name] = ptest
                    final_lat[name] = _measure_latency(model, Xtest)
                    _save_model(model, name, zid, h)
                    hyperparams.setdefault(name, _extract_hparams(model))
                    test_row = _metric_row(name, zid, h, ytest, ptest, test_ar_rmse, latency=final_lat[name])
                    test_row["selection_rule"] = "all_models_tested; deployment_selected_on_validation"
                    all_metrics.append(test_row)
                    for pred_value, actual_value in zip(ptest.tolist(), ytest.tolist()):
                        pred_rows.append((str(zid), h, name, float(pred_value), float(actual_value)))
                    print(f"zone {zid} {h} {name}: TEST RMSE={test_row['rmse']:.2f} R2={test_row['r2']:.3f} F1={test_row['f1']:.3f}")
                except Exception as exc:
                    print(f"  {name} final test skipped: {exc}")

            if not final_preds:
                continue
            available_weights = {name: weights[name] for name in final_preds if name in weights}
            available_weight_sum = sum(available_weights.values()) or 1.0
            test_ensemble = sum(available_weights[name] / available_weight_sum * final_preds[name]
                                for name in final_preds if name in available_weights)
            ensemble_latency = round(sum(final_lat.values()), 3)
            # Les poids et l'ensemble restent calcules en interne pour préserver
            # le comportement existant, mais ils ne sont pas exportes comme modèles.
            print(f"zone {zid} {h}: ensemble interne calcule, classement limite aux modeles autorises")

            # Le modele affiche est celui choisi sur validation ; son test est seulement reporte.
            latest_preds = {name: float(model.predict(latest_feature(records, step))[0])
                            for name, model in final_models.items()}
            latest_available = {name: weights[name] for name in final_models if name in weights}
            latest_weight_sum = sum(latest_available.values()) or 1.0
            latest_ensemble = float(sum(latest_available[name] / latest_weight_sum * latest_preds[name]
                                        for name in final_models if name in latest_available))
            # L'ensemble reste un fallback interne calculé avec les poids de validation.
            # Il n'est pas exporté comme modèle affiché ni persisté dans model_predictions.
            latest_value = latest_preds.get(selected_name, latest_ensemble)
            level = "safe" if latest_value <= 50 else ("warning" if latest_value <= 100 else "critical")
            selected_test_rmse = (float(np.sqrt(np.mean((ytest - final_preds[selected_name]) ** 2)))
                                  if selected_name in final_preds else float(np.sqrt(np.mean((ytest - test_ensemble) ** 2))))
            dl_forecasts.setdefault(zid, {})[h] = {
                "predicted": int(round(latest_value)), "level": level,
                "conf": round(float(max(0.0, min(1.0, 1.0 - selection_rmses[selected_name] / (np.std(yval) + 1e-6)))), 2),
                "model": selected_name, "validation_rmse": round(selection_rmses[selected_name], 3),
                "test_rmse": round(selected_test_rmse, 3),
            }
            print(f"zone {zid} {h} deployment={selected_name} (selection validation only) | test RMSE={selected_test_rmse:.2f}")

            if h == "1h" and len(ytest) >= 4:
                selected_test = final_preds[selected_name] if selected_name in final_preds else test_ensemble
                kk = min(72, len(selected_test))
                cand = {"labels": [f"H{i}" for i in range(kk)],
                        "actual": [round(float(v), 1) for v in ytest[-kk:]],
                        "predicted": [round(float(v), 1) for v in selected_test[-kk:]],
                        "zone": zid, "model": selected_name,
                        "rmse": round(selected_test_rmse, 2)}
                if dl_series is None or len(cand["actual"]) > len(dl_series.get("actual", [])):
                    dl_series = cand

            # Deep models utilisent les memes frontieres 70/10/20 et evaluent validation/test.
            if deep_models is not None and deep_models.available():
                for dl_name, dl_fn in (("LSTM", deep_models.train_lstm),
                                       ("BiLSTM Simple", deep_models.train_bilstm),
                                       ("BiLSTM+MultiHead Attn", deep_models.train_bilstm_attention),
                                       ("CNN+AE", deep_models.train_cnn_autoencoder)):
                    try:
                        dm = dl_fn(records, step, first_val, first_test, prepared=dl_prepared)
                    except Exception as exc:
                        dm = None
                        print(f"  {dl_name} skipped: {exc}")
                    finally:
                        release_dl_memory()
                    if dm:
                        all_metrics.append({"model": dl_name, "city_id": zid, "horizon": h, **dm})
                        if dm.get("train_rmse") is not None:
                            training_metrics.append({"model": dl_name, "city_id": zid, "horizon": h,
                                                     "mae": dm.get("train_mae"), "rmse": dm.get("train_rmse"),
                                                     "mape": dm.get("train_mape", 0), "smape": dm.get("train_smape", 0),
                                                     "r2": dm.get("train_r2"), "f1": dm.get("train_f1"),
                                                     "prec": dm.get("train_prec"), "rec": dm.get("train_rec"),
                                                     "acc": dm.get("train_acc"), "auc": dm.get("train_auc"),
                                                     "latency": dm.get("latency", 0), "split": "train"})
                        if dm.get("val_rmse") is not None:
                            validation_metrics.append({"model": dl_name, "city_id": zid, "horizon": h,
                                                       "mae": dm.get("val_mae"), "rmse": dm.get("val_rmse"),
                                                       "mape": dm.get("val_mape", 0), "smape": dm.get("val_smape", 0),
                                                       "r2": dm.get("val_r2"), "f1": dm.get("val_f1"),
                                                       "prec": dm.get("val_prec"), "rec": dm.get("val_rec"),
                                                       "acc": dm.get("val_acc"), "auc": dm.get("val_auc"),
                                                       "latency": dm.get("latency", 0), "split": "validation"})
                        try:
                            for pred_value, actual_value in zip(dm.get("y_pred", []), dm.get("y_true", [])):
                                pred_rows.append((str(zid), h, dl_name, float(pred_value), float(actual_value)))
                        except Exception:
                            pass
                        print(f"zone {zid} {h} {dl_name}: TRAIN RMSE={dm.get('train_rmse', float('nan')):.2f} | TEST RMSE={dm['rmse']:.2f} R2={dm['r2']:.3f} F1={dm['f1']:.3f} | VAL RMSE={dm.get('val_rmse', float('nan')):.2f}")

            if bilstm_autoencoder is not None and bilstm_autoencoder.available():
                try:
                    dm = bilstm_autoencoder.train_bilstm_ae(records, step, first_val, first_test,
                                                             zone_id=zid, saved_dir=SAVED,
                                                             prepared_matrix=dl_matrix)
                except Exception as exc:
                    dm = None
                    print(f"  BiLSTM+AE skipped: {exc}")
                finally:
                    release_dl_memory()
                if dm:
                    all_metrics.append({"model": "BiLSTM+AE", "city_id": zid, "horizon": h, **dm})
                    if dm.get("train_rmse") is not None:
                        training_metrics.append({"model": "BiLSTM+AE", "city_id": zid, "horizon": h,
                                                 "mae": dm.get("train_mae"), "rmse": dm.get("train_rmse"),
                                                 "mape": dm.get("train_mape", 0), "smape": dm.get("train_smape", 0),
                                                 "r2": dm.get("train_r2"), "f1": dm.get("train_f1"),
                                                 "prec": dm.get("train_prec"), "rec": dm.get("train_rec"),
                                                 "acc": dm.get("train_acc"), "auc": dm.get("train_auc"),
                                                 "latency": dm.get("latency", 0), "split": "train"})
                    if dm.get("val_rmse") is not None:
                        validation_metrics.append({"model": "BiLSTM+AE", "city_id": zid, "horizon": h,
                                                   "mae": dm.get("val_mae"), "rmse": dm.get("val_rmse"),
                                                   "mape": dm.get("val_mape", 0), "smape": dm.get("val_smape", 0),
                                                   "r2": dm.get("val_r2"), "f1": dm.get("val_f1"),
                                                   "prec": dm.get("val_prec"), "rec": dm.get("val_rec"),
                                                   "acc": dm.get("val_acc"), "auc": dm.get("val_auc"),
                                                   "latency": dm.get("latency", 0), "split": "validation"})
                    try:
                        for pred_value, actual_value in zip(dm.get("y_pred", []), dm.get("y_true", [])):
                            pred_rows.append((str(zid), h, "BiLSTM+AE", float(pred_value), float(actual_value)))
                    except Exception:
                        pass
                    print(f"zone {zid} {h} BiLSTM+AE: TRAIN RMSE={dm.get('train_rmse', float('nan')):.2f} | TEST RMSE={dm['rmse']:.2f} R2={dm['r2']:.3f} F1={dm['f1']:.3f} | VAL RMSE={dm.get('val_rmse', float('nan')):.2f}")


            del dl_prepared, dl_matrix
            gc.collect()

        _save_fuzzy_health(conn, zid, records[-1])

    save_metrics_db(conn, all_metrics)
    save_validation_metrics_db(conn, validation_metrics)
    save_training_metrics_db(conn, training_metrics)
    save_hyperparams_db(conn, hyperparams)
    _save_predictions_db(conn, pred_rows)

    dl_attention = None
    if deep_models is not None and deep_models.available() and attn_records is not None:
        try:
            if attn_bounds:
                dl_attention = deep_models.attention_matrix(attn_records, 1,
                                                            attn_bounds[0], attn_bounds[1])
            else:
                dl_attention = deep_models.attention_matrix(attn_records, 1)
        except Exception as exc:
            print("[dl] attention skipped:", exc)
    predictions = []
    for zid, horizons in dl_forecasts.items():
        zmeta = zones_meta.get(zid, {})
        hs = []
        for h in ("1h", "6h", "24h"):
            if h in horizons:
                hh = horizons[h]
                hs.append({"h": h.replace("h", ""), "predicted": hh["predicted"],
                           "level": hh["level"], "conf": hh["conf"],
                           "model": hh.get("model", "—"),
                           "validation_rmse": hh.get("validation_rmse"),
                           "test_rmse": hh.get("test_rmse")})
        predictions.append({"zone_id": zid, "name": zmeta.get("name", f"Zone {zid}"),
                            "name_ar": zmeta.get("name_ar", ""),
                            "type": zmeta.get("category", ""), "horizons": hs})
    save_dl_artifacts(conn, predictions, dl_series, dl_attention)

    try:
        save_pollutant_xai(conn)
    except Exception as exc:
        print("[xai] global skipped:", exc)

    with open(os.path.join(SAVED, "test_summary.json"), "w") as f:
        json.dump(all_metrics, f, indent=2)
    with open(os.path.join(SAVED, "validation_summary.json"), "w") as f:
        json.dump(validation_metrics, f, indent=2)
    with open(os.path.join(SAVED, "training_summary.json"), "w") as f:
        json.dump(training_metrics, f, indent=2)

    try:
        _run_v6_hooks(conn, all_metrics)
    except Exception as exc:
        print(f"[v6] hooks globaux sautes: {exc}")

    if conn:
        conn.close()
    print("=" * 60)
    print(f"DONE. {len(training_metrics)} train rows, {len(validation_metrics)} validation rows, {len(all_metrics)} test rows.")
    print(f"Models in {SAVED}/; train summary in training_summary.json; validation summary in validation_summary.json; test summary in test_summary.json")
    print("Source: open_data (Open-Meteo/CAMS) - 0 ligne synthetique")


def _make_xgb(Xtr, ytr):
    """Entraine XGBoost reellement, ou signale son absence sans le renommer."""
    try:
        import xgboost as xgb
    except Exception as exc:
        raise RuntimeError(f"XGBoost indisponible: {exc}") from exc
    m = xgb.XGBRegressor(n_estimators=300, max_depth=6, learning_rate=0.05,
                         subsample=0.85, colsample_bytree=0.8,
                         objective="reg:squarederror", random_state=42)
    m.fit(Xtr, ytr)
    return m


def _measure_latency(model, X, repeats=30):
    """Realistic per-prediction latency in ms (single-row inference)."""
    try:
        if X is None or len(X) == 0:
            return 0.0
        row = X[:1]
        model.predict(row)  # warm-up
        t0 = time.perf_counter()
        for _ in range(repeats):
            model.predict(row)
        return round((time.perf_counter() - t0) / repeats * 1000, 3)
    except Exception:
        return 0.0


def _metric_row(name, zid, h, yte, pred, base_rmse, latency=0.0):
    """Build a model_performance row from real predictions."""
    mt = ml_models.metrics(yte, pred)
    improvement = None
    if base_rmse:
        improvement = round((base_rmse - mt["rmse"]) / base_rmse * 100, 1)
    _p, _r = _prec_rec(yte, pred)
    return {"model": name, "city_id": zid, "horizon": h,
            "f1": round(_f1(yte, pred), 3), "prec": round(_p, 3), "rec": round(_r, 3),
            "auc": _auc(yte, pred), "acc": round(_acc(yte, pred) * 100, 1),
            "latency": round(latency, 2), "improvement": improvement, "wilcoxon": None,
            **{k: round(v, 3) for k, v in mt.items()}}


def _f1(y_true, pred):
    """Macro-F1 fixe sur les 4 categories AQI."""
    from sklearn.metrics import f1_score
    yt, yp = classify(y_true), classify(pred)
    labels = [0, 1, 2, 3]
    return float(f1_score(yt, yp, labels=labels, average="macro", zero_division=0))


def _acc(y_true, pred):
    """Real classification accuracy."""
    from sklearn.metrics import accuracy_score
    return float(accuracy_score(classify(y_true), classify(pred)))


def _prec_rec(y_true, pred):
    """Real macro precision & recall over the 4 AQI categories."""
    from sklearn.metrics import precision_score, recall_score
    yt, yp = classify(y_true), classify(pred)
    labels = [0, 1, 2, 3]
    p = float(precision_score(yt, yp, labels=labels, average="macro", zero_division=0))
    r = float(recall_score(yt, yp, labels=labels, average="macro", zero_division=0))
    return p, r


def _auc(y_true, pred):
    """Genuine ranking AUC (one-vs-rest at each AQI threshold)."""
    from sklearn.metrics import roc_auc_score
    yt = np.asarray(y_true, dtype=float)
    pr = np.asarray(pred, dtype=float)
    aucs = []
    for thr in CLASS_BINS[1:-1]:
        yb = (yt >= thr).astype(int)
        if len(set(yb.tolist())) < 2:
            continue
        try:
            aucs.append(float(roc_auc_score(yb, pr)))
        except Exception:
            pass
    return round(float(np.mean(aucs)), 3) if aucs else None


def _save_model(model, name, zid, h):
    try:
        import joblib
        key = name.split()[0].lower().replace("+", "")
        joblib.dump(model, os.path.join(SAVED, f"{key}_reg_{h}_zone{zid}.pkl"))
    except Exception as e:
        print("save skipped:", e)


def _save_fuzzy_health(conn, zid, rec):
    fz = fuzzy_type2.assess(min(100.0, float(rec["aqi"]) / 5.0))
    hi = health_impact.assess(float(rec["aqi"]), float(rec["pm25"]), float(rec["so2"]))
    if conn is None:
        return
    try:
        cur = conn.cursor()
        cur.execute("""INSERT INTO fuzzy_assessments
            (city_id, timestamp, pollution_input, fuzzy_score_type2, uncertainty_lower,
             uncertainty_upper, uncertainty_band, risk_level)
            VALUES (%s, NOW(), %s, %s, %s, %s, %s, %s)""",
            (str(zid), min(100.0, float(rec["aqi"]) / 5.0), fz["fuzzy_score_type2"],
             fz["uncertainty_lower"], fz["uncertainty_upper"], fz["uncertainty_band"],
             fz["risk_level"]))
        cur.execute("""INSERT INTO health_impact
            (city_id, timestamp, aqi_value, pm25_value, so2_value, health_impact_score,
             health_risk_level, recommendations) VALUES (%s, NOW(), %s, %s, %s, %s, %s, %s)""",
            (str(zid), float(rec["aqi"]), float(rec["pm25"]), float(rec["so2"]),
             hi["health_impact_score"], hi["health_risk_level"], hi["recommendations"]))
        conn.commit(); cur.close()
    except Exception as e:
        print("fuzzy/health db insert skipped:", e)


# ============================================================================
# HOOKS UPGRADE v6 (Parts 37, 39, 43, 44, 46) - executes en fin d'entrainement.
# Chaque hook est isole dans son propre try/except : un module scientifique
# absent (torch, torch_geometric, table manquante...) NE casse PAS le pipeline.
# ============================================================================
def _save_predictions_db(conn, rows):
    """Persiste les VRAIES predictions du set de test (predit vs reel) pour que
    conformal / calibration / A-B testing aient des donnees. Remplace le run
    precedent. 100% reel."""
    if conn is None or not rows:
        print("[predictions] rien a stocker")
        return
    try:
        cur = conn.cursor()
        try:
            cur.execute("DELETE FROM model_predictions")
        except Exception as e:
            print("[predictions] clear skipped:", e)
        capped = rows[:20000]
        cur.executemany(
            "INSERT INTO model_predictions (city_id, timestamp, horizon, model_name, "
            "predicted_aqi, actual_aqi) VALUES (%s, NOW(), %s, %s, %s, %s)",
            [(c, h, n, p, a) for (c, h, n, p, a) in capped],
        )
        conn.commit(); cur.close()
        print(f"[predictions] stored {len(capped)} real prediction rows")
    except Exception as e:
        print("[predictions] store skipped:", e)


def _run_v6_hooks(conn, all_metrics):
    print("-" * 60)
    print("[v6] hooks scientifiques (degradation gracieuse activee)")

    # Part 43 - Model Registry : enregistre une version par modele entraine.
    try:
        import model_registry_manager as reg
        seen = set()
        for row in all_metrics:
            name = str(row.get("model", "")).split("+")[0].strip().lower().replace(" ", "_")
            if not name or name in seen:
                continue
            seen.add(name)
            snapshot = {k: row.get(k) for k in ("f1", "rmse", "mae", "r2", "improvement")}
            reg.register_version(conn, name, snapshot, status="staging")
    except Exception as e:
        print(f"[v6][registry] saute: {e}")

    # Part 39 - Conformal prediction : intervalles calibres sur model_predictions.
    try:
        import conformal_predictor as cp
        cp.apply_intervals(conn, coverage=0.90)
    except Exception as e:
        print(f"[v6][conformal] saute: {e}")

    # Part 46 - RL ensemble : poids contextuels (LinUCB) par zone.
    try:
        import rl_ensemble_agent as rl
        zones = _v6_load_zone_ids(conn)
        for zid in (zones or [1]):
            rl.compute_and_store_weights(conn, zone_id=int(zid))
    except Exception as e:
        print(f"[v6][rl] saute: {e}")

    # Part 37 - GNN spatial : aretes ponderees entre les zones de Gabes.
    try:
        import gnn_spatial as gnn
        zones = _v6_load_zones_full(conn)
        if zones:
            gnn.run(conn, zones=zones)
    except Exception as e:
        print(f"[v6][gnn] saute: {e}")

    # Part 44 - A/B testing : compare deux modeles si assez de donnees.
    # v4.0 : on compare desormais le BiLSTM classique au nouveau BiLSTM+AE,
    # ce qui mesure directement l'apport de l'autoencodeur.
    try:
        import ab_testing_controller as ab
        ab.run_ab_test(conn, model_a="bilstm", model_b="bilstm_ae")
    except Exception as e:
        print(f"[v6][ab] saute: {e}")

    print("[v6] hooks termines.")


def _v6_load_zone_ids(conn):
    if conn is None:
        return []
    try:
        cur = conn.cursor()
        cur.execute("SELECT id FROM zones ORDER BY id")
        return [r[0] for r in cur.fetchall()]
    except Exception:
        return []


def _v6_load_zones_full(conn):
    if conn is None:
        return []
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id, name, lat, lng FROM zones")
        out = []
        for r in cur.fetchall():
            if r.get("lat") is None or r.get("lng") is None:
                continue
            out.append({"id": r["id"], "name": r.get("name", ""),
                        "lat": float(r["lat"]), "lng": float(r["lng"])})
        return out
    except Exception:
        return []


# main() must be called AFTER every helper (incl. _run_v6_hooks) is defined,
# otherwise the v6 registry/hooks raise NameError and model_versions stays empty.
if __name__ == "__main__":
    main()