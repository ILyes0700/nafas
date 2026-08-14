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

PROTOCOLE : identique a tous les autres modeles du projet. L'entrainement de
selection voit les 70% les plus anciens, la validation les 10% suivants et le
test final les 20% les plus recents. Le modele final est refit sur train+validation
avant une seule mesure Test. Aucune fuite temporelle.

CONTRAT D'INTERFACE : la signature est alignee sur deep_models.train_lstm /
train_bilstm / train_bilstm_attention, pour que train_all.py puisse appeler
tous les modeles DL dans la meme boucle.
"""
from __future__ import annotations

import os
import time
import numpy as np

try:
    from . import deep_models as shared_dl_features
except Exception:
    try:
        import deep_models as shared_dl_features
    except Exception:
        shared_dl_features = None

# ---------------------------------------------------------------------------
# Hyperparametres
# ---------------------------------------------------------------------------
WINDOW      = 24     # contexte pour +1h et +6h
WINDOW_LONG = 168    # contexte hebdomadaire pour +24h
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


def _window_length(horizon_step: int) -> int:
    """Contexte etendu pour le cas +24h."""
    return WINDOW_LONG if int(horizon_step) >= 24 else WINDOW


# ---------------------------------------------------------------------------
# Preparation des donnees
# ---------------------------------------------------------------------------
FEATURES = list(getattr(shared_dl_features, "FEATURE_NAMES", [
    "aqi", "pm25", "pm10", "so2", "no2", "o3", "co", "dust",
    "temperature", "humidity", "wind_speed", "wind_direction",
    "pressure", "precipitation", "cloud_cover"
]))


def _to_matrix(records: list[dict]) -> np.ndarray:
    """Convertit les enregistrements en matrice des 35 features réelles partagées."""
    if shared_dl_features is not None and hasattr(shared_dl_features, "build_feature_matrix"):
        return np.asarray(shared_dl_features.build_feature_matrix(records), dtype=np.float32)
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
             lo: int, hi: int, window: int | None = None):
    """Construit les fenetres glissantes dont la CIBLE tombe dans [lo, hi).

    C'est le point critique anti-fuite : on indexe par la position de la
    cible, pas par celle de la fenetre. Une fenetre qui se termine avant la
    frontiere mais dont la cible tombe apres serait une fuite ; elle est
    exclue du train par construction.
    """
    window = int(window or _window_length(horizon_step))
    X, y = [], []
    for t in range(window, len(mat) - horizon_step):
        tgt = t + horizon_step
        if not (lo <= tgt < hi):
            continue
        X.append(mat[t - window:t])
        y.append(target[tgt])
    if not X:
        return np.empty((0, window, mat.shape[1]), np.float32), np.empty((0,), np.float32)
    return np.asarray(X, np.float32), np.asarray(y, np.float32)


# ---------------------------------------------------------------------------
# Modeles
# ---------------------------------------------------------------------------
def _build_autoencoder(n_feats: int, window: int):
    from tensorflow.keras import layers, Model

    inp = layers.Input(shape=(window, n_feats), name="ae_in")
    enc = layers.LSTM(64, return_sequences=True)(inp)
    enc = layers.LSTM(LATENT_DIM, return_sequences=False, name="latent")(enc)

    dec = layers.RepeatVector(window)(enc)
    dec = layers.LSTM(LATENT_DIM, return_sequences=True)(dec)
    dec = layers.LSTM(64, return_sequences=True)(dec)
    out = layers.TimeDistributed(layers.Dense(n_feats))(dec)

    ae      = Model(inp, out, name="lstm_autoencoder")
    encoder = Model(inp, enc, name="encoder")
    ae.compile(optimizer="adam", loss="mse")
    return ae, encoder


def _build_forecaster(encoder, n_feats: int, window: int):
    from tensorflow.keras import layers, Model

    inp = layers.Input(shape=(window, n_feats), name="fc_in")

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
    from sklearn.metrics import roc_auc_score
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

    aucs = []
    for threshold in CLASS_BINS[1:-1]:
        binary_true = (y_true >= threshold).astype(int)
        if len(np.unique(binary_true)) < 2:
            continue
        try:
            aucs.append(float(roc_auc_score(binary_true, y_pred)))
        except Exception:
            pass
    return {
        "mae": mae, "rmse": rmse, "mape": mape, "smape": smape, "r2": float(r2),
        "f1":   float(np.mean(f1s))   if f1s   else 0.0,
        "prec": float(np.mean(precs)) if precs else 0.0,
        "rec":  float(np.mean(recs))  if recs  else 0.0,
        "acc":  acc,
        "auc":  float(np.mean(aucs)) if aucs else None,
    }


# ---------------------------------------------------------------------------
# Point d'entree appele par train_all.py
# ---------------------------------------------------------------------------
def train_bilstm_ae(records: list[dict], horizon_step: int, first_ho: int,
                    first_test: int | None = None,
                    zone_id: int | None = None, saved_dir: str | None = None,
                    prepared_matrix: np.ndarray | None = None):
    """Entraine et evalue l'AE avec un split temporel 70/10/20.

    Le premier modele sert a la selection sur validation. Un second AE/forecast
    est ensuite refit sur train+validation et mesure une seule fois sur test.
    Le test ne controle ni les epochs ni la selection.
    """
    if not available():
        return None
    window = _window_length(horizon_step)
    if first_test is None:
        first_test = int(len(records) * 0.80)
    if len(records) < window + horizon_step + 100 or first_ho < window + horizon_step:
        return None

    import tensorflow as tf
    from tensorflow.keras import callbacks
    tf.get_logger().setLevel("ERROR")
    mat = (np.asarray(prepared_matrix, dtype=np.float32)
           if prepared_matrix is not None else _to_matrix(records))
    target = mat[:, 0]
    n_feats = mat.shape[1]

    # Selection model: normalisation calculee sur train seulement.
    train_mat = mat[:first_ho]
    lo, hi = train_mat.min(axis=0), train_mat.max(axis=0)
    rng = np.where((hi - lo) < 1e-6, 1.0, hi - lo)
    norm_train = (mat - lo) / rng
    Xtr, ytr = _windows(norm_train, target, horizon_step, 0, first_ho, window)
    Xval, yval = _windows(norm_train, target, horizon_step, first_ho, first_test, window)
    if len(Xtr) < 50 or len(Xval) < 10:
        return None

    ae_sel, enc_sel = _build_autoencoder(n_feats, window)
    stop = callbacks.EarlyStopping(patience=8, restore_best_weights=True)
    ae_sel.fit(Xtr, Xtr, epochs=AE_EPOCHS, batch_size=BATCH, verbose=0,
               validation_data=(Xval, Xval), shuffle=False, callbacks=[stop])
    fc_sel = _build_forecaster(enc_sel, n_feats, window)
    fc_sel.fit(Xtr, ytr, epochs=FC_EPOCHS, batch_size=BATCH, verbose=0,
               validation_data=(Xval, yval), shuffle=False, callbacks=[stop])
    train_pred_selection = fc_sel.predict(Xtr, verbose=0).flatten()
    val_pred = fc_sel.predict(Xval, verbose=0).flatten()
    train_metrics = _metrics(ytr, train_pred_selection)
    val_metrics = _metrics(yval, val_pred)

    # Final model: min/max appris sur train+validation, puis test totalement inconnu.
    fit_mat = mat[:first_test]
    flo, fhi = fit_mat.min(axis=0), fit_mat.max(axis=0)
    frng = np.where((fhi - flo) < 1e-6, 1.0, fhi - flo)
    norm_fit = (mat - flo) / frng
    Xfit, yfit = _windows(norm_fit, target, horizon_step, 0, first_test, window)
    Xtest, ytest = _windows(norm_fit, target, horizon_step, first_test, len(records), window)
    if len(Xfit) < 60 or len(Xtest) < 10:
        return None
    ae, encoder = _build_autoencoder(n_feats, window)
    ae.fit(Xfit, Xfit, epochs=AE_EPOCHS, batch_size=BATCH, verbose=0,
           validation_split=0.1, shuffle=False)
    fc = _build_forecaster(encoder, n_feats, window)
    fc.fit(Xfit, yfit, epochs=FC_EPOCHS, batch_size=BATCH, verbose=0,
           validation_split=0.1, shuffle=False)

    t0 = time.perf_counter()
    y_pred = fc.predict(Xtest, verbose=0).flatten()
    train_pred = fc.predict(Xfit, verbose=0).flatten()
    latency = (time.perf_counter() - t0) * 1000.0 / max(1, len(Xtest))
    out = _metrics(ytest, y_pred)
    for key in ("mae", "rmse", "mape", "smape", "r2", "f1", "prec", "rec", "auc", "acc"):
        out[f"train_{key}"] = train_metrics.get(key)
        out[f"val_{key}"] = val_metrics.get(key)
    out["fit_rmse"] = float(np.sqrt(np.mean((yfit - train_pred) ** 2)))
    out["latency"] = float(latency)
    out["y_true"] = ytest.tolist()
    out["y_pred"] = y_pred.tolist()
    recon = ae.predict(Xtest, verbose=0)
    out["recon_error"] = float(np.mean((recon - Xtest) ** 2))

    if zone_id is not None and saved_dir:
        os.makedirs(saved_dir, exist_ok=True)
        path = os.path.join(saved_dir, f"bilstm_autoencoder_zone{zone_id}_{horizon_step}h.h5")
        try:
            fc.save(path)
        except Exception:
            pass
    return out