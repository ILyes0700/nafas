"""models/deep_models.py — v4.0 (BiLSTM / LSTM / BiLSTM+Attention, donnees reelles)

Modeles de deep learning REELS pour la prevision d'AQI. Ils restent OPTIONNELS :
si TensorFlow n'est pas installe, available() renvoie False et le pipeline les
saute simplement (jamais de chiffres inventes a la place).

    pip install tensorflow

CHANGEMENTS v4.0 (migration Open-Meteo)
---------------------------------------
1. Les modèles profonds partagent désormais 54 features causales réelles avec ML :
   retards AQI, tendances, fuzzy, polluants, météo enrichie et variables temporelles.
   La comparaison avec Random Forest/XGBoost utilise ainsi le même socle d'information.
2. Hyperparametres re-cales pour ~21 000 lignes reelles par ville au lieu de
   2016 lignes tuilees : batch 64 (au lieu de 16), 64/32 unites (au lieu de
   48/24), 60 epochs (au lieu de 120), patience 8 (au lieu de 12).
   Sans ce recalage, une epoch = 1000+ batches et l'entrainement devient
   inutilisable en pratique.
3. La logique de recherche du first_holdout est simplifiee : le split est
   desormais un simple point de coupe chronologique. Le parametre est conserve
   pour ne pas casser l'appel depuis train_all.py.
4. La standardisation de la CIBLE est conservee : elle reste indispensable,
   l'AQI reel de Ghannouche depassant regulierement 300.
"""
from __future__ import annotations
import numpy as np

try:
    from . import fuzzy_type2, feature_engineering
except Exception:
    try:
        import fuzzy_type2  # type: ignore
        import feature_engineering  # type: ignore
    except Exception:
        fuzzy_type2 = None
        feature_engineering = None

SEQ = 24        # contexte pour +1h et +6h
SEQ_LONG = 168  # contexte hebdomadaire complet pour +24h

# ML and DL use exactly the same enriched 54-feature causal schema.
FEATURE_NAMES = list(feature_engineering.FEATURE_NAMES) if feature_engineering is not None else []
assert len(FEATURE_NAMES) == 54, "shared feature schema unavailable or out of sync"
CLASS_BINS = [0, 50, 100, 150, 10_000]

# Hyperparametres re-cales pour des series longues et reelles.
BATCH = 64
EPOCHS = 60
PATIENCE = 8
UNITS_1 = 64
UNITS_2 = 32


def available() -> bool:
    """True uniquement si TensorFlow est importable."""
    try:
        import tensorflow  # noqa: F401
        return True
    except Exception:
        return False


def _sequence_length(horizon_step):
    """Contexte plus long pour l'horizon journalier."""
    return SEQ_LONG if int(horizon_step) >= 24 else SEQ


def _classify(vals):
    return np.digitize(vals, CLASS_BINS[1:-1])


def _metrics(yte, pred):
    from sklearn.metrics import (mean_absolute_error, mean_squared_error,
                                 r2_score, f1_score, accuracy_score,
                                 precision_score, recall_score, roc_auc_score)
    yte = np.asarray(yte, dtype=float)
    pred = np.asarray(pred, dtype=float)
    rmse = float(np.sqrt(mean_squared_error(yte, pred)))
    yt, yp = _classify(yte), _classify(pred)
    labels = [0, 1, 2, 3]  # macro 4 classes fixes (pas d'inflation)
    aucs = []
    for thr in CLASS_BINS[1:-1]:
        yb = (yte >= thr).astype(int)
        if len(set(yb.tolist())) < 2:
            continue
        try:
            aucs.append(float(roc_auc_score(yb, pred)))
        except Exception:
            pass
    auc = round(float(np.mean(aucs)), 3) if aucs else None
    return {
        "mae": round(float(mean_absolute_error(yte, pred)), 3),
        "rmse": round(rmse, 3),
        "mape": round(float(np.mean(np.abs((yte - pred) / (yte + 1e-9))) * 100), 3),
        "smape": round(float(np.mean(2 * np.abs(pred - yte) / (np.abs(yte) + np.abs(pred) + 1e-9)) * 100), 3),
        "r2": round(float(r2_score(yte, pred)), 3),
        "f1": round(float(f1_score(yt, yp, labels=labels, average="macro", zero_division=0)), 3),
        "prec": round(float(precision_score(yt, yp, labels=labels, average="macro", zero_division=0)), 3),
        "rec": round(float(recall_score(yt, yp, labels=labels, average="macro", zero_division=0)), 3),
        "auc": auc,
        "acc": round(float(accuracy_score(yt, yp)) * 100, 1),
    }


def _record_value(record, key):
    value = record.get(key)
    try:
        return float(value) if value is not None else 0.0
    except (TypeError, ValueError):
        return 0.0


def _time_parts(record, index):
    ts = record.get("ts") or record.get("timestamp")
    try:
        if not hasattr(ts, "hour"):
            import pandas as pd
            ts = pd.to_datetime(ts)
        hour = int(ts.hour)
        dow = int(ts.weekday())
        month = int(ts.month)
    except Exception:
        hour, dow, month = index % 24, (index // 24) % 7, ((index // (24 * 90)) % 4) * 3 + 1
    return float(hour), float(dow >= 5), float((month % 12) // 3)


def build_feature_matrix(records):
    """Build the shared enriched causal feature matrix."""
    if feature_engineering is None:
        raise RuntimeError("feature_engineering module is unavailable")
    return np.asarray(feature_engineering.build_feature_matrix(records), dtype=np.float32)


def _build_sequences(records, horizon_step, matrix=None):
    """Construit les séquences avec cible exactement à t+horizon.

    Le dernier élément de chaque fenêtre est t, donc la cible est t+horizon.
    Les anciennes versions commençaient à l'index ``seq`` tout en terminant la
    fenêtre à ``seq-1`` : elles entraînaient donc implicitement un horizon
    décalé d'une heure. Les lags 168 sont aussi disponibles avant la première
    fenêtre afin de ne pas utiliser le remplissage de début de série.
    """
    if matrix is None:
        matrix = build_feature_matrix(records)
    aqi = matrix[:, 0]
    n = len(aqi)
    seq = _sequence_length(horizon_step)
    start = max(seq - 1, 167)
    X, y = [], []
    for last_idx in range(start, n - horizon_step):
        target_idx = last_idx + horizon_step
        window = np.array(matrix[last_idx - seq + 1:last_idx + 1], dtype=np.float32, copy=True)
        # Les trois variables temporelles du dernier pas décrivent l'heure
        # connue de la cible; elles sont connues sans lire sa valeur AQI.
        if target_idx < len(records) and window.shape[1] >= 35:
            hour, weekend, season = _time_parts(records[target_idx], target_idx)
            window[-1, 32:35] = (hour, weekend, season)
            window[-1, 47:49] = (
                np.sin(2.0 * np.pi * hour / 24.0),
                np.cos(2.0 * np.pi * hour / 24.0),
            )
        X.append(window)
        y.append(aqi[target_idx])
    if not X:
        return None, None
    return np.asarray(X, dtype=np.float32), np.asarray(y, dtype=np.float32)


def prepare_sequences(records, horizon_step, matrix=None):
    """Prépare une seule fois les fenêtres réelles d'un couple zone/horizon."""
    return _build_sequences(records, horizon_step, matrix=matrix)


def build_latest_sequence(records, horizon_step, matrix=None):
    """Build the real production-origin window ending at the last observed t."""
    if matrix is None:
        matrix = build_feature_matrix(records)
    seq = _sequence_length(horizon_step)
    if len(records) < seq:
        return None
    window = np.asarray(matrix[-seq:], dtype=np.float32).copy()
    if feature_engineering is not None:
        hour, weekend, season = feature_engineering.time_parts(records[-1], len(records) - 1, horizon_step)
    else:
        hour, weekend, season = _time_parts(records[-1], len(records) - 1)
    window[-1, 32:35] = (hour, weekend, season)
    window[-1, 47:49] = (
        np.sin(2.0 * np.pi * hour / 24.0),
        np.cos(2.0 * np.pi * hour / 24.0),
    )
    return window[None, ...]


def _standardize(Xtr, Xte):
    flat = Xtr.reshape(-1, Xtr.shape[-1])
    mu = flat.mean(axis=0)
    sd = flat.std(axis=0) + 1e-6
    return (Xtr - mu) / sd, (Xte - mu) / sd


def _window_split_bounds(records, horizon_step, n_windows, first_val, first_test):
    """Bornes des fenetres train/validation/test selon la cible future."""
    seq = _sequence_length(horizon_step)
    n = len(records)
    start = max(seq - 1, 167)
    targets = np.arange(start, n - horizon_step, dtype=int) + horizon_step
    targets = targets[:n_windows]
    if first_val is None or first_val < 0:
        first_val = int(n * 0.70)
    if first_test is None or first_test < 0:
        first_test = int(n * 0.80)
    ntr = int(np.sum(targets < first_val))
    nval = int(np.sum((targets >= first_val) & (targets < first_test)))
    train_end = max(1, min(ntr, n_windows - 2))
    val_end = max(train_end + 1, min(train_end + nval, n_windows - 1))
    return train_end, val_end


def _make_network(layers, models, optimizers, input_shape, attention, bidirectional,
                  architecture="rnn"):
    inp = layers.Input(shape=input_shape)

    if architecture == "cnn_ae":
        # Encodeur convolutionnel causal + reconstruction de la fenêtre.
        # La sortie forecast est séparée de la reconstruction pour éviter toute
        # valeur fabriquée : les deux sorties sont apprises sur les mêmes fenêtres.
        c = layers.Conv1D(64, 5, padding="causal", activation="relu")(inp)
        c = layers.BatchNormalization()(c)
        c = layers.Conv1D(32, 3, padding="causal", activation="relu")(c)
        latent = layers.Conv1D(16, 1, padding="same", activation="relu", name="cnn_latent")(c)
        recon = layers.Conv1D(input_shape[-1], 1, padding="same", name="reconstruction")(latent)
        pooled = layers.GlobalAveragePooling1D()(latent)
        pooled = layers.Dense(32, activation="relu")(pooled)
        forecast = layers.Dense(1, name="forecast")(pooled)
        model = models.Model(inp, [forecast, recon])
        model.compile(optimizer=optimizers.Adam(learning_rate=1e-3, clipnorm=1.0),
                      loss=["huber", "mse"], loss_weights=[1.0, 0.1])
        return model

    def rnn(units, seq):
        lyr = layers.LSTM(units, return_sequences=seq)
        return layers.Bidirectional(lyr) if bidirectional else lyr

    c = layers.Conv1D(32, 3, padding="causal", activation="relu")(inp)
    c = layers.BatchNormalization()(c)
    x = rnn(UNITS_1, True)(c)
    x = layers.Dropout(0.2)(x)
    if attention:
        att = layers.MultiHeadAttention(num_heads=4, key_dim=16)(x, x)
        x = layers.Add()([x, att])
        x = layers.LayerNormalization()(x)
        x = rnn(UNITS_2, False)(x)
    else:
        x = rnn(UNITS_2, False)(x)
    x = layers.Dropout(0.2)(x)
    x = layers.Dense(32, activation="relu")(x)
    out = layers.Dense(1)(x)
    model = models.Model(inp, out)
    model.compile(optimizer=optimizers.Adam(learning_rate=1e-3, clipnorm=1.0), loss="huber")
    return model


def _scale_windows(Xfit, Xother):
    flat = Xfit.reshape(-1, Xfit.shape[-1])
    mu = flat.mean(axis=0)
    sd = flat.std(axis=0) + 1e-6
    return (Xfit - mu) / sd, (Xother - mu) / sd


def _train(records, horizon_step, attention, bidirectional=True, split=0.7,
           first_holdout=None, first_test=None, architecture="rnn", prepared=None,
           matrix=None):
    """Train/validation/test temporel, sans choix base sur le test."""
    try:
        import tensorflow as tf
        from tensorflow.keras import layers, models, callbacks, optimizers
    except Exception:
        return None
    X, y = prepared if prepared is not None else _build_sequences(records, horizon_step, matrix=matrix)
    if X is None or len(X) < 60:
        return None
    ntr, nval_end = _window_split_bounds(records, horizon_step, len(X), first_holdout, first_test)
    if ntr < 20 or nval_end - ntr < 10 or len(X) - nval_end < 10:
        return None
    Xtr, Xval, Xfit, Xtest = X[:ntr], X[ntr:nval_end], X[:nval_end], X[nval_end:]
    ytr, yval, yfit, ytest = y[:ntr], y[ntr:nval_end], y[:nval_end], y[nval_end:]
    Xtr_s, Xval_s = _scale_windows(Xtr, Xval)
    Xfit_s, Xtest_s = _scale_windows(Xfit, Xtest)
    ymu, ysd = float(ytr.mean()), float(ytr.std()) + 1e-6
    ytr_s, yval_s = (ytr - ymu) / ysd, (yval - ymu) / ysd
    tf.random.set_seed(42); np.random.seed(42)

    cbs = [callbacks.EarlyStopping(patience=PATIENCE, restore_best_weights=True),
           callbacks.ReduceLROnPlateau(factor=0.5, patience=4, min_lr=1e-5)]
    selection_model = _make_network(layers, models, optimizers, X.shape[1:], attention, bidirectional, architecture)
    selection_targets = [ytr_s, Xtr_s] if architecture == "cnn_ae" else ytr_s
    selection_val_targets = [yval_s, Xval_s] if architecture == "cnn_ae" else yval_s
    selection_history = selection_model.fit(
        Xtr_s, selection_targets,
        validation_data=(Xval_s, selection_val_targets),
        epochs=EPOCHS, batch_size=BATCH, verbose=0, callbacks=cbs, shuffle=False
    )
    val_history = selection_history.history.get("val_loss", selection_history.history.get("loss", []))
    best_epochs = max(1, int(np.argmin(val_history) + 1)) if len(val_history) else EPOCHS
    selection_train_output = selection_model.predict(Xtr_s, verbose=0)
    selection_output = selection_model.predict(Xval_s, verbose=0)
    if architecture == "cnn_ae":
        selection_train_output, selection_output = selection_train_output[0], selection_output[0]
    train_pred_selection = np.asarray(selection_train_output).ravel() * ysd + ymu
    val_pred = np.asarray(selection_output).ravel() * ysd + ymu
    train_metrics = _metrics(ytr, train_pred_selection)
    val_metrics = _metrics(yval, val_pred)

    final_ymu, final_ysd = float(yfit.mean()), float(yfit.std()) + 1e-6
    yfit_s = (yfit - final_ymu) / final_ysd
    final_model = _make_network(layers, models, optimizers, X.shape[1:], attention, bidirectional, architecture)
    final_targets = [yfit_s, Xfit_s] if architecture == "cnn_ae" else yfit_s
    # The final fit uses Train+Validation rows. Its epoch count was selected
    # using Validation only; Test is not used for tuning or early stopping.
    final_model.fit(Xfit_s, final_targets, epochs=best_epochs,
                    batch_size=BATCH, verbose=0, shuffle=False)
    import time
    final_model.predict(Xtest_s[:1], verbose=0)
    t0 = time.perf_counter()
    for _r in range(5): final_model.predict(Xtest_s[:1], verbose=0)
    latency = (time.perf_counter() - t0) / 5 * 1000
    test_output = final_model.predict(Xtest_s, verbose=0)
    if architecture == "cnn_ae":
        test_output = test_output[0]
    test_pred = np.asarray(test_output).ravel() * final_ysd + final_ymu
    latest_sequence = build_latest_sequence(records, horizon_step, matrix=matrix)
    if latest_sequence is None:
        raise RuntimeError("latest DL sequence unavailable")
    latest_scaled, _ = _scale_windows(Xfit, latest_sequence)
    latest_output = final_model.predict(latest_scaled, verbose=0)
    if architecture == "cnn_ae":
        latest_output = latest_output[0]
    latest_pred = float(np.asarray(latest_output).ravel()[0] * final_ysd + final_ymu)
    metrics = _metrics(ytest, test_pred)
    for key in ("mae", "rmse", "mape", "smape", "r2", "f1", "prec", "rec", "auc", "acc"):
        metrics[f"train_{key}"] = train_metrics.get(key)
        metrics[f"val_{key}"] = val_metrics.get(key)
    metrics["fit_rmse"] = round(float(np.sqrt(np.mean((yfit - final_ymu) ** 2))), 3)
    metrics["latency"] = round(latency, 2)
    metrics["latest_pred"] = latest_pred
    metrics["best_epochs"] = int(best_epochs)
    return metrics


def train_lstm(records, horizon_step, first_holdout=None, first_test=None, prepared=None, matrix=None):
    return _train(records, horizon_step, attention=False, bidirectional=False,
                  first_holdout=first_holdout, first_test=first_test, prepared=prepared, matrix=matrix)


def train_bilstm(records, horizon_step, first_holdout=None, first_test=None, prepared=None, matrix=None):
    return _train(records, horizon_step, attention=False, bidirectional=True,
                  first_holdout=first_holdout, first_test=first_test, prepared=prepared, matrix=matrix)


def train_bilstm_attention(records, horizon_step, first_holdout=None, first_test=None, prepared=None, matrix=None):
    """BiLSTM + Multi-Head Attention reel avec validation/test separes."""
    return _train(records, horizon_step, attention=True,
                  first_holdout=first_holdout, first_test=first_test, prepared=prepared, matrix=matrix)


def train_cnn_autoencoder(records, horizon_step, first_holdout=None, first_test=None, prepared=None, matrix=None):
    """CNN + Autoencoder reel, avec forecast + reconstruction de fenetre."""
    return _train(records, horizon_step, attention=False, bidirectional=False,
                  first_holdout=first_holdout, first_test=first_test,
                  architecture="cnn_ae", prepared=prepared, matrix=matrix)


def attention_matrix(records, horizon_step=1, first_val=None, first_test=None, prepared=None):
    """Matrice d'attention reelle, de taille sequence x sequence selon l'horizon.

    Ce sont les VRAIS scores softmax de la couche Multi-Head Attention : il n'y
    a aucun nombre aleatoire. Si le calcul est impossible, on retourne None et
    l'UI affiche un message honnete plutot qu'une fausse heatmap.
    """
    try:
        import tensorflow as tf
        from tensorflow.keras import layers, models, optimizers, callbacks
    except Exception:
        return None
    try:
        X, y = prepared if prepared is not None else _build_sequences(records, horizon_step)
        if X is None or len(X) < 40:
            return None
        ntr, nval_end = _window_split_bounds(records, horizon_step, len(X), first_val, first_test)
        if nval_end >= len(X):
            nval_end = len(X) - 1
        Xtr, Xte, ytr = X[:nval_end], X[nval_end:], y[:nval_end]
        if len(Xte) < 5:
            return None
        Xtr, Xte = _standardize(Xtr, Xte)
        ymu = float(ytr.mean()); ysd = float(ytr.std()) + 1e-6
        ytr_s = (ytr - ymu) / ysd
        tf.random.set_seed(42); np.random.seed(42)

        inp = layers.Input(shape=(X.shape[1], X.shape[2]))
        x = layers.Bidirectional(layers.LSTM(UNITS_1, return_sequences=True))(inp)
        x = layers.Dropout(0.2)(x)
        mha = layers.MultiHeadAttention(num_heads=4, key_dim=16)
        att_out, scores = mha(x, x, return_attention_scores=True)
        x2 = layers.Add()([x, att_out])
        x2 = layers.LayerNormalization()(x2)
        x2 = layers.Bidirectional(layers.LSTM(UNITS_2))(x2)
        x2 = layers.Dropout(0.2)(x2)
        x2 = layers.Dense(32, activation="relu")(x2)
        out = layers.Dense(1)(x2)
        model = models.Model(inp, out)
        model.compile(optimizer=optimizers.Adam(learning_rate=1e-3, clipnorm=1.0),
                      loss="huber")
        model.fit(Xtr, ytr_s, validation_split=0.15, epochs=40, batch_size=BATCH,
                  verbose=0,
                  callbacks=[callbacks.EarlyStopping(patience=6,
                                                     restore_best_weights=True)])

        score_model = models.Model(inp, scores)   # (n, heads, sequence, sequence)
        sc = np.asarray(score_model.predict(Xte, verbose=0), dtype=float)
        # On garde la tete la PLUS structuree (variance max) : moyenner les 4
        # tetes ensemble lisse le motif jusqu'a le rendre uniforme.
        per_head = sc.mean(axis=0)                 # (heads, sequence, sequence)
        if per_head.ndim == 3 and per_head.shape[0] > 1:
            variances = [float(per_head[h].var()) for h in range(per_head.shape[0])]
            mat = per_head[int(np.argmax(variances))]
        else:
            mat = per_head.reshape(per_head.shape[-2], per_head.shape[-1])
        rows = []
        for i in range(mat.shape[0]):
            s = float(mat[i].sum()) or 1.0
            rows.append([round(float(v) / s, 4) for v in mat[i]])
        return {"hours": list(range(mat.shape[0])), "weights": rows}
    except Exception:
        return None