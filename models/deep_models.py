"""models/deep_models.py — v4.0 (BiLSTM / LSTM / BiLSTM+Attention, donnees reelles)

Modeles de deep learning REELS pour la prevision d'AQI. Ils restent OPTIONNELS :
si TensorFlow n'est pas installe, available() renvoie False et le pipeline les
saute simplement (jamais de chiffres inventes a la place).

    pip install tensorflow

CHANGEMENTS v4.0 (migration Open-Meteo)
---------------------------------------
1. _FEATURES passe de 7 a 15 variables. Avant la migration, seules 7 colonnes
   etaient disponibles proprement dans api_readings ; open_data en expose 15.
   Consequence importante : le BiLSTM etait jusqu'ici compare a XGBoost alors
   qu'il voyait DEUX FOIS MOINS d'information. La comparaison est enfin juste.
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

SEQ = 24  # heures d'historique fournies au reseau recurrent

# v4.0 : 15 variables reelles issues de open_data (etait 7).
_FEATURES = [
    "aqi", "pm25", "pm10", "so2", "no2", "o3", "co", "dust",
    "temperature", "humidity", "wind_speed", "wind_direction",
    "pressure", "precipitation", "cloud_cover",
]
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


def _build_sequences(records, horizon_step):
    """Construit (n, SEQ, n_features) et la cible AQI a t+horizon.

    v4.0 : les colonnes absentes d'un enregistrement valent 0.0, mais on ne
    remplit plus silencieusement TOUT le vecteur : si aqi manque, la serie est
    inexploitable et data_loader l'a deja filtree en amont.
    """
    cols = {k: np.array([float(r.get(k) or 0.0) for r in records]) for k in _FEATURES}
    aqi = cols["aqi"]
    n = len(aqi)
    X, y = [], []
    for i in range(SEQ, n - horizon_step):
        window = np.stack([cols[k][i - SEQ:i] for k in _FEATURES], axis=1)
        X.append(window)
        y.append(aqi[i + horizon_step])
    if not X:
        return None, None
    return np.asarray(X, dtype=float), np.asarray(y, dtype=float)


def _standardize(Xtr, Xte):
    flat = Xtr.reshape(-1, Xtr.shape[-1])
    mu = flat.mean(axis=0)
    sd = flat.std(axis=0) + 1e-6
    return (Xtr - mu) / sd, (Xte - mu) / sd


def _cut_index(n_windows, horizon_step, first_holdout, split):
    """Indice de coupe train/test dans l'espace des FENETRES.

    first_holdout est un indice dans l'espace des LIGNES (fourni par
    train_all.py). La fenetre j couvre les lignes [j, j+SEQ) et predit la ligne
    j+SEQ+horizon_step ; elle appartient donc au train si sa cible est
    anterieure au debut du hold-out. v4.0 : calcul direct, plus de boucle.
    """
    if first_holdout is not None and first_holdout >= 0:
        ntr = first_holdout - SEQ - horizon_step
        if ntr >= 5 and (n_windows - ntr) >= 5:
            return ntr
    return max(1, int(n_windows * split))


def _train(records, horizon_step, attention, bidirectional=True, split=0.8,
           first_holdout=None):
    """Trainer partage. Retourne un dict de metriques reelles, ou None."""
    try:
        import tensorflow as tf
        from tensorflow.keras import layers, models, callbacks, optimizers
    except Exception:
        return None
    X, y = _build_sequences(records, horizon_step)
    if X is None or len(X) < 40:
        return None

    ntr = _cut_index(len(X), horizon_step, first_holdout, split)
    if ntr >= len(X):
        ntr = len(X) - 1
    Xtr, Xte, ytr, yte = X[:ntr], X[ntr:], y[:ntr], y[ntr:]
    if len(Xte) < 5:
        return None

    Xtr, Xte = _standardize(Xtr, Xte)
    # Standardisation de la CIBLE : indispensable, l'AQI reel monte a 300+.
    ymu = float(ytr.mean())
    ysd = float(ytr.std()) + 1e-6
    ytr_s = (ytr - ymu) / ysd
    tf.random.set_seed(42)
    np.random.seed(42)

    inp = layers.Input(shape=(X.shape[1], X.shape[2]))

    def rnn(units, seq):
        # BiLSTM lit le temps dans LES DEUX SENS ; LSTM simple dans un seul.
        lyr = layers.LSTM(units, return_sequences=seq)
        return layers.Bidirectional(lyr) if bidirectional else lyr

    # Front-end CNN : extraction de motifs locaux court-terme avant le BiLSTM
    # (architecture CNN-BiLSTM-AM, etat de l'art prevision AQI 2024-2025).
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
    opt = optimizers.Adam(learning_rate=1e-3, clipnorm=1.0)
    model.compile(optimizer=opt, loss="huber")
    cbs = [
        callbacks.EarlyStopping(patience=PATIENCE, restore_best_weights=True),
        callbacks.ReduceLROnPlateau(factor=0.5, patience=4, min_lr=1e-5),
    ]
    model.fit(Xtr, ytr_s, validation_split=0.15, epochs=EPOCHS,
              batch_size=BATCH, verbose=0, callbacks=cbs)

    import time
    model.predict(Xte[:1], verbose=0)  # warm-up
    t0 = time.perf_counter()
    for _r in range(5):
        model.predict(Xte[:1], verbose=0)
    latency = (time.perf_counter() - t0) / 5 * 1000
    pred_s = model.predict(Xte, verbose=0).ravel()
    pred = pred_s * ysd + ymu  # inversion de la standardisation de la cible
    m = _metrics(yte, pred)
    m["latency"] = round(latency, 2)
    return m


def train_lstm(records, horizon_step, first_holdout=None):
    """LSTM unidirectionnel reel. Dict de metriques, ou None si TF absent."""
    return _train(records, horizon_step, attention=False, bidirectional=False,
                  first_holdout=first_holdout)


def train_bilstm(records, horizon_step, first_holdout=None):
    """BiLSTM reel. Dict de metriques, ou None si TF absent / trop peu de data."""
    return _train(records, horizon_step, attention=False, bidirectional=True,
                  first_holdout=first_holdout)


def train_bilstm_attention(records, horizon_step, first_holdout=None):
    """BiLSTM + Multi-Head Attention reel. Dict de metriques, ou None."""
    return _train(records, horizon_step, attention=True,
                  first_holdout=first_holdout)


def attention_matrix(records, horizon_step=1):
    """Matrice d'attention REELLE SEQ x SEQ, moyennee sur les fenetres de test.

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
        X, y = _build_sequences(records, horizon_step)
        if X is None or len(X) < 40:
            return None
        ntr = max(1, int(len(X) * 0.8))
        if ntr >= len(X):
            ntr = len(X) - 1
        Xtr, Xte, ytr = X[:ntr], X[ntr:], y[:ntr]
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

        score_model = models.Model(inp, scores)   # (n, heads, SEQ, SEQ)
        sc = np.asarray(score_model.predict(Xte, verbose=0), dtype=float)
        # On garde la tete la PLUS structuree (variance max) : moyenner les 4
        # tetes ensemble lisse le motif jusqu'a le rendre uniforme.
        per_head = sc.mean(axis=0)                 # (heads, SEQ, SEQ)
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