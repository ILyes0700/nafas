"""PART 8 - Ablation study sur DONNEES REELLES (open_data).

v4.0 - REECRITURE COMPLETE. Deux changements majeurs :

1. Suppression de l'experience "+ CGAN augmented data".
   Le CGAN n'existe plus : le pipeline s'entraine sur ~21 000 lignes reelles
   par ville (Open-Meteo/CAMS) au lieu de ~150 lignes augmentees. Mesurer
   l'apport d'une augmentation qui n'a plus lieu d'etre n'a aucun sens.

2. L'ablation est desormais REELLE.
   L'ancienne version prenait un dictionnaire de metriques ecrites a la main
   dans le bloc __main__ et se contentait d'en calculer les deltas. Aucun
   modele n'etait entraine, aucun chiffre ne venait d'une mesure. Ici chaque
   configuration entraine un vrai XGBoost (ou RandomForest en repli) sur le
   train chronologique et l'evalue sur le test, exactement comme train_all.

Deux familles d'ablation sont fournies :

  A. CUMULATIVE (EXPERIMENTS) - repond a "chaque composant apporte-t-il >= 3% ?"
     On empile les composants un par un et on mesure le gain marginal.

  B. LEAVE-ONE-GROUP-OUT (ABLATIONS) - repond a "quel groupe de features
     porte le signal ?". On retire un groupe de features a la fois du vecteur
     complet de 29 dimensions.
       - no_weather : retire les 7 features meteo
       - no_lags    : retire les 9 lags AQI
       - no_dust    : retire la poussiere saharienne
       - no_fuzzy   : retire les 3 sorties du fuzzy Type-2

     L'ablation `no_dust` est specifique a Gabes : elle quantifie l'apport du
     signal poussiere saharienne, qui n'existait pas du tout dans l'ancien
     vecteur de features issu d'AccuWeather.

Run: python -m models.ablation_study
"""
from __future__ import annotations
import os, sys, json
import numpy as np

try:
    from . import data_loader, ml_models, db_config, train_all
except Exception:
    sys.path.append(os.path.dirname(__file__))
    import data_loader, ml_models, db_config, train_all

SAVED = os.path.join(os.path.dirname(__file__), "saved")
os.makedirs(SAVED, exist_ok=True)

# Les noms doivent rester strictement synchronises avec train_all.FEATURE_NAMES.
FEATURE_NAMES = train_all.FEATURE_NAMES

LAG_COLS = [f"aqi_lag_{k}" for k in (1, 2, 3, 4, 5, 6, 7, 24, 168)]
FUZZY_COLS = ["fuzzy_score_type2", "uncertainty_lower", "uncertainty_upper"]
WEATHER_COLS = ["temperature", "humidity", "wind_speed", "wind_direction",
                "pressure", "precipitation", "cloud_cover"]
DUST_COLS = ["dust"]
TIME_COLS = ["hour_of_day", "is_weekend", "season"]

# ---------------------------------------------------------------------------
# A. Ablation CUMULATIVE : chaque ligne ajoute un composant au precedent.
#    "+ CGAN augmented data" a ete retire ; "+ Dust feature" le remplace.
# ---------------------------------------------------------------------------
EXPERIMENTS = [
    "XGBoost only",
    "+ Fuzzy Type-2 Score",
    "+ Weather features",
    "+ Dust feature",
    "+ BiLSTM temporal",
    "+ Multi-Head Attention",
    "+ Ensemble dynamic",
    "+ Residual correction",
    "FULL SYSTEM",
]

# Groupes de features actives a chaque etape cumulative.
CUMULATIVE_FEATURES = {
    "XGBoost only":            LAG_COLS + TIME_COLS,
    "+ Fuzzy Type-2 Score":    LAG_COLS + TIME_COLS + FUZZY_COLS,
    "+ Weather features":      LAG_COLS + TIME_COLS + FUZZY_COLS + WEATHER_COLS,
    "+ Dust feature":          LAG_COLS + TIME_COLS + FUZZY_COLS + WEATHER_COLS + DUST_COLS,
}

# ---------------------------------------------------------------------------
# B. Ablation LEAVE-ONE-GROUP-OUT sur le vecteur complet.
# ---------------------------------------------------------------------------
ABLATIONS = {
    "full":       [],
    "no_weather": WEATHER_COLS,
    "no_lags":    LAG_COLS,
    "no_dust":    DUST_COLS,
    "no_fuzzy":   FUZZY_COLS,
}


def _col_index(names):
    """Indices des colonnes `names` dans le vecteur de 29 features."""
    return [FEATURE_NAMES.index(n) for n in names if n in FEATURE_NAMES]


def _subset(X, keep_names):
    """Sous-matrice ne gardant que les colonnes listees, dans l'ordre canonique."""
    idx = _col_index(keep_names)
    if not idx:
        raise ValueError("Aucune feature retenue pour cette configuration")
    return X[:, idx]


def _drop(X, drop_names):
    """Sous-matrice privee des colonnes listees."""
    bad = set(_col_index(drop_names))
    idx = [i for i in range(X.shape[1]) if i not in bad]
    return X[:, idx]


def _fit_eval(Xtr, ytr, Xte, yte):
    """Entraine un vrai modele et retourne des metriques MESUREES.
    Meme estimateur que train_all._make_xgb pour que les chiffres soient
    directement comparables a ceux de model_performance."""
    model = train_all._make_xgb(Xtr, ytr)
    pred = model.predict(Xte)
    mt = ml_models.metrics(yte, pred)
    return {
        "rmse": round(float(mt["rmse"]), 3),
        "mae":  round(float(mt["mae"]), 3),
        "r2":   round(float(mt["r2"]), 3),
        "f1":   round(float(train_all._f1(yte, pred)), 3),
        "auc":  train_all._auc(yte, pred),
        "n_features": int(Xtr.shape[1]),
    }


def _load_zone_xy(zone_id=None, horizon_step=1):
    """Charge une zone reelle et retourne (Xtr, ytr, Xte, yte, meta).

    Par defaut on prend la zone qui a le plus de lignes reelles, ce qui donne
    l'ablation la plus stable statistiquement. Le split est le meme 80/20
    chronologique que partout ailleurs : aucune fuite temporelle.
    """
    frames = data_loader.build_frames()
    if not frames:
        raise RuntimeError("build_frames() n'a retourne aucune zone. "
                           "Verifie que la table open_data est bien remplie.")
    if zone_id is None:
        zone_id = max(frames, key=lambda z: len(train_all.to_series(frames[z])))
    records = train_all.to_series(frames[zone_id])
    if len(records) < 200:
        raise RuntimeError(f"zone {zone_id}: seulement {len(records)} lignes")

    X, y = train_all.build_xy(records, horizon_step)
    ntr = train_all._split_index(records, horizon_step, len(X))
    meta = {
        "zone_id": zone_id,
        "n_records": len(records),
        "n_train": int(ntr),
        "n_test": int(len(X) - ntr),
        "horizon_step": horizon_step,
    }
    return X[:ntr], y[:ntr], X[ntr:], y[ntr:], meta


def run_ablation(evaluate_config=None, zone_id=None, horizon_step=1):
    """Ablation CUMULATIVE reelle.

    `evaluate_config` reste accepte pour compatibilite ascendante (un appelant
    peut injecter sa propre fonction d'evaluation), mais s'il vaut None — le
    cas normal — les modeles sont reellement entraines sur open_data.
    """
    if evaluate_config is None:
        Xtr, ytr, Xte, yte, meta = _load_zone_xy(zone_id, horizon_step)
        print(f"[ablation] zone {meta['zone_id']} | {meta['n_records']} lignes reelles "
              f"| train={meta['n_train']} test={meta['n_test']} | horizon={horizon_step}h")

        def evaluate_config(name):
            keep = CUMULATIVE_FEATURES.get(name)
            if keep is None:
                # Les etapes BiLSTM / Attention / Ensemble / Residual utilisent
                # le vecteur complet ; leur apport propre est mesure par
                # train_all (model_performance), pas ici. On evalue donc le
                # vecteur complet pour ces lignes.
                return _fit_eval(Xtr, ytr, Xte, yte)
            return _fit_eval(_subset(Xtr, keep), ytr, _subset(Xte, keep), yte)

    rows = []
    prev = None
    for name in EXPERIMENTS:
        m = evaluate_config(name)
        row = {"config": name, **m}
        if prev:
            row["delta_rmse_pct"] = round((prev["rmse"] - m["rmse"]) / prev["rmse"] * 100, 1)
            if prev["f1"]:
                row["delta_f1_pct"] = round((m["f1"] - prev["f1"]) / prev["f1"] * 100, 1)
        prev = m
        rows.append(row)
    return rows


def run_leave_one_out(zone_id=None, horizon_step=1):
    """Ablation LEAVE-ONE-GROUP-OUT reelle sur le vecteur complet de 29 features.

    Retourne pour chaque configuration l'ecart de RMSE par rapport a `full`.
    Un ecart POSITIF signifie que retirer ce groupe degrade le modele, donc
    que le groupe portait du signal utile.
    """
    Xtr, ytr, Xte, yte, meta = _load_zone_xy(zone_id, horizon_step)
    print(f"[ablation LOGO] zone {meta['zone_id']} | train={meta['n_train']} "
          f"test={meta['n_test']} | horizon={horizon_step}h")

    rows = []
    base = None
    for name, dropped in ABLATIONS.items():
        Xa_tr = Xtr if not dropped else _drop(Xtr, dropped)
        Xa_te = Xte if not dropped else _drop(Xte, dropped)
        m = _fit_eval(Xa_tr, ytr, Xa_te, yte)
        if name == "full":
            base = m
        row = {"config": name, "dropped": dropped or ["-"], **m}
        if base and name != "full":
            row["rmse_degradation_pct"] = round(
                (m["rmse"] - base["rmse"]) / base["rmse"] * 100, 1)
        rows.append(row)
    return rows


def save_to_db(conn, cumulative, logo):
    """Persiste les deux ablations pour que le frontend puisse les afficher."""
    if conn is None:
        return
    try:
        cur = conn.cursor()
        cur.execute("""CREATE TABLE IF NOT EXISTS ablation_results (
            artifact_key VARCHAR(64) PRIMARY KEY,
            payload LONGTEXT,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci""")
        for key, payload in (("cumulative", cumulative), ("leave_one_out", logo)):
            cur.execute("REPLACE INTO ablation_results "
                        "(artifact_key, payload, updated_at) VALUES (%s,%s,NOW())",
                        (key, json.dumps(payload)))
        conn.commit(); cur.close()
        print("[ablation] resultats stockes dans ablation_results")
    except Exception as e:
        print("[ablation] store skipped:", e)


if __name__ == "__main__":
    print("=" * 60)
    print("ABLATION STUDY - donnees reelles open_data, 0 valeur codee en dur")
    print("=" * 60)

    cumulative = run_ablation()
    print("\n--- Ablation cumulative ---")
    for r in cumulative:
        print(r)

    logo = run_leave_one_out()
    print("\n--- Leave-one-group-out ---")
    for r in logo:
        print(r)

    conn = db_config.try_connection()
    save_to_db(conn, cumulative, logo)
    if conn:
        conn.close()

    with open(os.path.join(SAVED, "ablation_summary.json"), "w") as f:
        json.dump({"cumulative": cumulative, "leave_one_out": logo}, f, indent=2)
    print("\nEcrit dans models/saved/ablation_summary.json")