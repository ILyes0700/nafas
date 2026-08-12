"""PART 5 - Deep Learning: BiLSTM and BiLSTM + Multi-Head Attention + XGBoost
hybrid. Includes attention-weight extraction for the heatmap visualisation.

Requires TensorFlow/Keras. Import is lazy so the file can be inspected without
TF installed.

References: Schuster & Paliwal (1997); Vaswani et al. (2017).

NOTE: this module is the STANDALONE RESEARCH variant of the deep models
(hyper-parameter tuning playground). The PRODUCTION deep trainer used by
train_all.py is models/deep_models.py. Keep changes here in sync when relevant.
"""
from __future__ import annotations
import numpy as np

TIMESTEPS, N_FEATURES = 24, 27
CLASS_BINS = [0, 50, 100, 150, 10_000]


def _classify(vals):
    """Fix (L1): 4-class AQI category (SAFE/WARNING/CRITICAL/HAZARDOUS) for the
    softmax clf head. The old code fed a BINARY (aqi>100) label into a 4-way
    softmax, so classes 2 and 3 could never be learned."""
    return np.digitize(vals, CLASS_BINS[1:-1])


def build_bilstm(units_1=128, units_2=64, dropout=0.2, lr=1e-3):
    from tensorflow import keras
    inp = keras.Input(shape=(TIMESTEPS, N_FEATURES))
    x = keras.layers.Bidirectional(keras.layers.LSTM(units_1, return_sequences=True))(inp)
    x = keras.layers.Dropout(dropout)(x)
    x = keras.layers.Bidirectional(keras.layers.LSTM(units_2))(x)
    x = keras.layers.Dropout(dropout)(x)
    x = keras.layers.Dense(32, activation="relu")(x)
    out_reg = keras.layers.Dense(1, name="reg")(x)
    out_clf = keras.layers.Dense(4, activation="softmax", name="clf")(x)
    model = keras.Model(inp, [out_reg, out_clf])
    model.compile(optimizer=keras.optimizers.Adam(lr),
                  loss={"reg": "mse", "clf": "sparse_categorical_crossentropy"})
    return model


def build_bilstm_attention(num_heads=8, key_dim=32):
    """Stage 1: BiLSTM + Multi-Head Attention. Returns (feature_model, attn_model)."""
    from tensorflow import keras
    inp = keras.Input(shape=(TIMESTEPS, N_FEATURES))
    x = keras.layers.Bidirectional(keras.layers.LSTM(128, return_sequences=True))(inp)
    x = keras.layers.Dropout(0.2)(x)
    x = keras.layers.Bidirectional(keras.layers.LSTM(64, return_sequences=True))(x)
    attn = keras.layers.MultiHeadAttention(num_heads=num_heads, key_dim=key_dim, dropout=0.1)
    attn_out, attn_scores = attn(x, x, return_attention_scores=True)
    x = keras.layers.LayerNormalization()(x + attn_out)
    x = keras.layers.GlobalAveragePooling1D()(x)
    temporal = keras.layers.Dense(64, activation="tanh", name="temporal")(x)
    feature_model = keras.Model(inp, temporal)
    attn_model = keras.Model(inp, attn_scores)  # [batch, heads, 24, 24]
    return feature_model, attn_model


def extract_attention(attn_model, X):
    """Average attention over heads -> [24, 24] matrix for the heatmap."""
    scores = attn_model.predict(X)          # [batch, heads, 24, 24]
    avg = scores.mean(axis=1).mean(axis=0)   # over heads then batch
    return avg


def tune_bilstm(X_tr, y_tr, X_val, y_val, n_trials=100):
    try:
        import optuna
    except Exception as e:  # pragma: no cover
        print("optuna missing:", e); return {"units_1": 128, "units_2": 64, "dropout": 0.2, "lr": 1e-3}
    from tensorflow import keras

    def objective(trial):
        p = dict(units_1=trial.suggest_int("units_1", 64, 256),
                 units_2=trial.suggest_int("units_2", 32, 128),
                 dropout=trial.suggest_float("dropout", 0.1, 0.5),
                 lr=trial.suggest_float("lr", 1e-4, 1e-2, log=True))
        m = build_bilstm(**p)
        es = keras.callbacks.EarlyStopping(patience=8, restore_best_weights=True)
        m.fit(X_tr, {"reg": y_tr, "clf": _classify(y_tr)},
              validation_data=(X_val, {"reg": y_val, "clf": _classify(y_val)}),
              epochs=20, verbose=0, callbacks=[es])
        pred = m.predict(X_val, verbose=0)[0].ravel()
        return float(np.sqrt(np.mean((y_val - pred) ** 2)))

    study = optuna.create_study(direction="minimize")
    study.optimize(objective, n_trials=n_trials)
    return study.best_params


if __name__ == "__main__":
    print("dl_models: build BiLSTM + Multi-Head Attention (needs TensorFlow to run).")
