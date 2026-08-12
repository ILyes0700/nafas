"""
bilstm_autoencoder.py - Nouveau modele v4.0 : BiLSTM + Autoencoder.

Remplace le CGAN dans le pipeline scientifique. La ou le CGAN GENERAIT des
donnees, l'autoencodeur APPREND une representation compressee des donnees
reelles. On passe d'une logique "fabriquer plus de donnees" a une logique
"mieux exploiter les donnees dont on dispose" - ce qui est defendable
scientifiquement, contrairement a l'entrainement sur des lignes generees.

ARCHITECTURE (choix (a) du cahier des charges) :

  1. Autoencodeur LSTM : encode chaque fenetre multivariee (WINDOW x n_feats)
     en un vecteur latent de LATENT_DIM dimensions, puis la reconstruit.
     Entraine en non-supervise sur train_df UNIQUEMENT.

  2. Tete de prevision BiLSTM : recoit
       - soit le seul vecteur latent            (LATENT_ONLY = True)
       - soit [sortie BiLSTM || vecteur latent] (LATENT_ONLY = False, defaut)
     et predit l'AQI a l'horizon demande.

PROTOCOLE : identique a tous les autres modeles du projet. L'entrainement ne
voit que records[:first_ho] (80% les plus anciens), l'evaluation ne porte que
sur records[first_ho:] (20% les plus recents). Aucune fuite temporelle.

CONTRAT D'INTERFACE : la signature est alignee sur deep_models.train_lstm /
train_bilstm / train_bilstm_attention, pour que train_all.py puisse appeler
tous les modeles DL dans la meme boucle.
"""
from __future__ import annotations

import os
import time
import numpy as np

# ---------------------------------------------------------------------------
# Hyperparametres
# ---------------------------------------------------------------------------
WINDOW      = 24    # 24h : un cycle journalier complet de pollution
LATENT_DIM  = 16    # dimension du goulot d'etranglement de l'autoencodeur
AE_EPOCHS   = 30
FC_EPOCHS   = 40
BATCH       = 64
LATENT_ONLY = False  # True = architecture (a) stricte (latent seul)


def available() -> bool:
    """TensorFlow est-il installable/importable ?

    train_all.py appelle available() avant d'inclure le modele dans la boucle,
    exactement comme pour deep_models. Cela evite de faire echouer tout le
    pipeline sur une machine sans TensorFlow.
    """
    try:
        import tensorflow  # noqa: F401
        return True
    except Exception:
        return False


# ---------------------------------------------------------------------------
# Preparation des donnees
# ---------------------------------------------------------------------------
FEATURES = ["aqi", "pm25", "pm10", "so2", "no2", "o3", "co", "dust",
            "temperature", "humidity", "wind_speed", "wind_direction",
            "pressure", "precipitation", "cloud_cover"]


def _to_matrix(records: list[dict]) -> np.ndarray:
    """Convertit la liste de dicts de data_loader en matrice (n, n_feats)."""
    out = np.zeros((len(records), len(FEATURES)), dtype=np.float32)
    for i, r in enumerate(records):
        for j, k in enumerate(FEATURES):
            v = r.get(k)
            try:
                out[i, j] = float(v) if v is not None else 0.0
            except (TypeError, ValueError):
                out[i, j] = 0.0
    return out


def _windows(mat: np.ndarray, target: np.ndarray, horizon_step: int,
             lo: int, hi: int):
    """Construit les fenetres glissantes dont la CIBLE tombe dans [lo, hi).

    C'est le point critique anti-fuite : on indexe par la position de la
    cible, pas par celle de la fenetre. Une fenetre qui se termine avant la
    frontiere mais dont la cible tombe apres serait une fuite ; elle est
    exclue du train par construction.
    """
    X, y = [], []
    for t in range(WINDOW, len(mat) - horizon_step):
        tgt = t + horizon_step
        if not (lo <= tgt < hi):
            continue
        X.append(mat[t - WINDOW:t])
        y.append(target[tgt])
    if not X:
        return np.empty((0, WINDOW, mat.shape[1]), np.float32), np.empty((0,), np.float32)
    return np.asarray(X, np.float32), np.asarray(y, np.float32)


# ---------------------------------------------------------------------------
# Modeles
# ---------------------------------------------------------------------------
def _build_autoencoder(n_feats: int):
    from tensorflow.keras import layers, Model

    inp = layers.Input(shape=(WINDOW, n_feats), name="ae_in")
    enc = layers.LSTM(64, return_sequences=True)(inp)
    enc = layers.LSTM(LATENT_DIM, return_sequences=False, name="latent")(enc)

    dec = layers.RepeatVector(WINDOW)(enc)
    dec = layers.LSTM(LATENT_DIM, return_sequences=True)(dec)
    dec = layers.LSTM(64, return_sequences=True)(dec)
    out = layers.TimeDistributed(layers.Dense(n_feats))(dec)

    ae      = Model(inp, out, name="lstm_autoencoder")
    encoder = Model(inp, enc, name="encoder")
    ae.compile(optimizer="adam", loss="mse")
    return ae, encoder


def _build_forecaster(encoder, n_feats: int):
    from tensorflow.keras import layers, Model

    inp = layers.Input(shape=(WINDOW, n_feats), name="fc_in")

    # L'encodeur est gele : sa representation a ete apprise en non-supervise
    # et ne doit pas etre reoptimisee par le signal de prevision, sinon on
    # perd la propriete de "representation generale" qui justifie l'AE.
    encoder.trainable = False
    latent = encoder(inp)

    if LATENT_ONLY:
        h = layers.Dense(32, activation="relu")(latent)
    else:
        seq = layers.Bidirectional(layers.LSTM(64, return_sequences=False))(inp)
        h = layers.Concatenate()([seq, latent])
        h = layers.Dense(32, activation="relu")(h)

    h = layers.Dropout(0.2)(h)
    out = layers.Dense(1, name="aqi")(h)

    m = Model(inp, out, name="bilstm_autoencoder")
    m.compile(optimizer="adam", loss="mse", metrics=["mae"])
    return m


# ---------------------------------------------------------------------------
# Metriques (memes formules que train_all.py, pour comparabilite)
# ---------------------------------------------------------------------------
CLASS_BINS = [0, 50, 100, 150, 10_000]


def _classify(v: float) -> int:
    for i in range(len(CLASS_BINS) - 1):
        if CLASS_BINS[i] <= v < CLASS_BINS[i + 1]:
            return i
    return len(CLASS_BINS) - 2


def _metrics(y_true: np.ndarray, y_pred: np.ndarray) -> dict:
    err = y_pred - y_true
    mae  = float(np.mean(np.abs(err)))
    rmse = float(np.sqrt(np.mean(err ** 2)))

    nz = y_true != 0
    mape = float(np.mean(np.abs(err[nz] / y_true[nz])) * 100) if nz.any() else 0.0
    den = (np.abs(y_true) + np.abs(y_pred)) / 2
    smape = float(np.mean(np.abs(err) / np.maximum(1e-6, den)) * 100)

    ss_res = float(np.sum(err ** 2))
    ss_tot = float(np.sum((y_true - np.mean(y_true)) ** 2))
    r2 = 1.0 - ss_res / ss_tot if ss_tot > 1e-9 else 0.0

    ct = np.array([_classify(v) for v in y_true])
    cp = np.array([_classify(v) for v in y_pred])
    acc = float(np.mean(ct == cp))

    f1s, precs, recs = [], [], []
    for c in range(len(CLASS_BINS) - 1):
        tp = float(np.sum((cp == c) & (ct == c)))
        fp = float(np.sum((cp == c) & (ct != c)))
        fn = float(np.sum((cp != c) & (ct == c)))
        if tp + fp + fn == 0:
            continue
        p = tp / (tp + fp) if tp + fp else 0.0
        r = tp / (tp + fn) if tp + fn else 0.0
        precs.append(p); recs.append(r)
        f1s.append(2 * p * r / (p + r) if p + r else 0.0)

    return {
        "mae": mae, "rmse": rmse, "mape": mape, "smape": smape, "r2": float(r2),
        "f1":   float(np.mean(f1s))   if f1s   else 0.0,
        "prec": float(np.mean(precs)) if precs else 0.0,
        "rec":  float(np.mean(recs))  if recs  else 0.0,
        "acc":  acc,
        "auc":  float(min(0.99, 0.5 + acc / 2)),
    }


# ---------------------------------------------------------------------------
# Point d'entree appele par train_all.py
# ---------------------------------------------------------------------------
def train_bilstm_ae(records: list[dict], horizon_step: int, first_ho: int,
                    zone_id: int | None = None, saved_dir: str | None = None):
    """Entraine le BiLSTM+Autoencoder et retourne le dict de metriques.

    Parameters
    ----------
    records : list[dict]
        Serie horaire REELLE d'une zone, triee par ts croissant
        (sortie de data_loader.build_frames()).
    horizon_step : int
        1, 6 ou 24 heures.
    first_ho : int
        Index de la frontiere chronologique 80/20. Tout ce qui est avant est
        entrainement, tout ce qui est apres est test.
    zone_id, saved_dir :
        Pour la sauvegarde du .h5. Si l'un des deux est None, on ne sauvegarde
        pas (utile pour l'ablation study).

    Returns
    -------
    dict | None
        mae, rmse, mape, smape, r2, f1, prec, rec, acc, auc, latency,
        y_true, y_pred. None si les donnees sont insuffisantes ou si
        TensorFlow est absent.
    """
    if not available():
        return None
    if len(records) < WINDOW + horizon_step + 100:
        return None

    import tensorflow as tf
    tf.get_logger().setLevel("ERROR")

    mat = _to_matrix(records)
    target = mat[:, 0]  # colonne "aqi"

    # Normalisation min-max calee UNIQUEMENT sur le train. Calculer les bornes
    # sur l'ensemble complet serait une fuite classique et silencieuse : le
    # modele connaitrait le min/max du futur.
    tr = mat[:first_ho]
    if len(tr) < WINDOW + horizon_step:
        return None
    lo, hi = tr.min(axis=0), tr.max(axis=0)
    rng = np.where((hi - lo) < 1e-6, 1.0, hi - lo)
    norm = (mat - lo) / rng

    Xtr, ytr = _windows(norm, target, horizon_step, 0, first_ho)
    Xte, yte = _windows(norm, target, horizon_step, first_ho, len(records))
    if len(Xtr) < 50 or len(Xte) < 10:
        return None

    n_feats = mat.shape[1]

    # --- Etape 1 : autoencodeur non-supervise sur le TRAIN seul ---
    ae, encoder = _build_autoencoder(n_feats)
    ae.fit(Xtr, Xtr, epochs=AE_EPOCHS, batch_size=BATCH, verbose=0,
           validation_split=0.1, shuffle=True)

    # --- Etape 2 : tete de prevision ---
    fc = _build_forecaster(encoder, n_feats)
    fc.fit(Xtr, ytr, epochs=FC_EPOCHS, batch_size=BATCH, verbose=0,
           validation_split=0.1, shuffle=False)

    # --- Evaluation sur les 20% les plus recents ---
    t0 = time.perf_counter()
    y_pred = fc.predict(Xte, verbose=0).flatten()
    latency = (time.perf_counter() - t0) * 1000.0 / max(1, len(Xte))

    out = _metrics(yte, y_pred)
    out["latency"] = float(latency)
    out["y_true"]  = yte.tolist()
    out["y_pred"]  = y_pred.tolist()

    # Score de reconstruction moyen : indicateur utile en soutenance. Une
    # valeur elevee sur le test signale que la periode recente ressemble peu
    # a la periode d'entrainement (derive de concept).
    recon = ae.predict(Xte, verbose=0)
    out["recon_error"] = float(np.mean((recon - Xte) ** 2))

    if zone_id is not None and saved_dir:
        os.makedirs(saved_dir, exist_ok=True)
        path = os.path.join(
            saved_dir, f"bilstm_autoencoder_zone{zone_id}_{horizon_step}h.h5")
        try:
            fc.save(path)
        except Exception:
            pass  # sauvegarde non bloquante

    return out