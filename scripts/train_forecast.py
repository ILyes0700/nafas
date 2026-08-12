"""
train_forecast.py - Real ML+DL hybrid pollution forecaster.

v4.0 - MIGRATION DONNEES REELLES OPEN-METEO.
  La source n'est plus `risk_scores` + `risk_scores_augmented` (CGAN) mais la
  table `open_data` : Open-Meteo Air Quality (CAMS Europe) + ERA5, 7 villes du
  gouvernorat de Gabes, granularite horaire, 2024-01-01 -> 2026-07-02.
  Plus aucune ligne generee n'entre dans l'entrainement ni dans l'evaluation.

Builds an ensemble of:

  * XGBoost regressor (gradient-boosted trees, classical ML)
  * LSTM (recurrent deep network)

and combines them via a weighted average whose alpha is chosen on a held-out
validation split by minimising RMSE.

PROTOCOLE : split chronologique 80/20 (80% les plus anciens en train, 20% les
plus recents en test). Identique a models/train_all.py, donc les metriques des
deux pipelines sont directement comparables.

Outputs
-------
  1. forecast_predictions - 6h / 12h / 24h predictions for every zone
  2. forecast_metrics     - MAE, RMSE, MAPE, R2, SMAPE per model

This is the "pro" variant of the PHP implementation in
`backend/lib/forecast_ml.php`. Both read `open_data` and apply the same
AQI -> score conversion, so the rest of the codebase consumes either
transparently.

Usage
-----
  python scripts/train_forecast.py
  python scripts/train_forecast.py --window 24 --days 180 --epochs 60
"""
from __future__ import annotations
import argparse
import os
import datetime as dt
import numpy as np
import pandas as pd
import pymysql

from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

try:
    from xgboost import XGBRegressor
except ImportError as e:
    raise SystemExit("Install: pip install xgboost") from e
try:
    import tensorflow as tf
    from tensorflow.keras import layers, Model
except ImportError as e:
    raise SystemExit("Install: pip install tensorflow") from e

# Colonnes exogenes tirees d'open_data et injectees dans XGBoost en plus des
# lags. Elles n'existaient pas avant : risk_scores ne contenait qu'un score
# agrege, sans aucune information sur les polluants ni sur la meteo.
EXOG_COLS = ["pm25", "pm10", "so2", "dust", "wind_speed", "temperature"]


# ------------------------------------------------------------------------
# Database helpers - v4.0 : lecture de open_data uniquement
# ------------------------------------------------------------------------
def db():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "gabes_tatenafas"),
        charset="utf8mb4",
    )


def load_full_series(zone_id: int, days: int = 180) -> pd.DataFrame:
    """Charge la serie horaire REELLE d'une zone depuis open_data.

    CHANGEMENT v4.0 :
      AVANT : deux SELECT (risk_scores + risk_scores_augmented) concatenes,
              soit un melange de ~150 points reels et de lignes CGAN.
      APRES : un seul SELECT sur open_data, joint aux zones par city_key.

    CONVERSION D'ECHELLE :
      open_data.us_aqi est un AQI 0-500, alors que toute la chaine aval
      (save_predictions, les seuils 70/40, les clip(0, 100)) travaille sur un
      score 0-100. On applique la meme formule que forecast_ml.php et que
      train_all._save_fuzzy_health :

          score = MIN(100, us_aqi / 5)

    BORNE TEMPORELLE :
      Relative a MAX(time) du dataset et non a NOW(). Le dataset s'arrete au
      2026-07-02 ; un NOW() - INTERVAL 180 DAY renverrait zero ligne des que
      l'horloge systeme depasse cette date.
    """
    sql = """
        SELECT o.time                          AS ts,
               LEAST(100, o.us_aqi / 5.0)      AS score,
               o.pm2_5                         AS pm25,
               o.pm10                          AS pm10,
               o.sulphur_dioxide               AS so2,
               o.dust                          AS dust,
               o.wind_speed_10m                AS wind_speed,
               o.temperature_2m                AS temperature
          FROM open_data o
          JOIN zones z ON z.city_key = o.city
         WHERE z.id = %s
           AND o.us_aqi IS NOT NULL
           AND o.time >= DATE_SUB((SELECT MAX(time) FROM open_data),
                                  INTERVAL %s DAY)
         ORDER BY o.time ASC
    """
    cols = ["ts", "score"] + EXOG_COLS
    with db() as cx, cx.cursor() as cur:
        cur.execute(sql, (zone_id, days))
        df = pd.DataFrame(list(cur.fetchall()), columns=cols)

    if df.empty:
        return df

    df["ts"] = pd.to_datetime(df["ts"])
    for c in ["score"] + EXOG_COLS:
        df[c] = pd.to_numeric(df[c], errors="coerce")

    # Interpolation limitee aux VRAIS trous internes (<= 3h). On ne fabrique
    # jamais de nouvelles heures : on comble seulement des NULL isoles.
    df[["score"] + EXOG_COLS] = df[["score"] + EXOG_COLS].interpolate(limit=3)
    df = df.dropna(subset=["score"])
    df = df.sort_values("ts").drop_duplicates("ts").reset_index(drop=True)
    return df


def list_zones():
    with db() as cx, cx.cursor() as cur:
        cur.execute("SELECT id, name FROM zones ORDER BY id")
        return list(cur.fetchall())


# ------------------------------------------------------------------------
# Feature engineering - lags + Fourier temporel + exogenes open_data
# ------------------------------------------------------------------------
def make_features(df: pd.DataFrame, n_lags: int = 7) -> pd.DataFrame:
    df = df.copy()
    for k in range(1, n_lags + 1):
        df[f"lag{k}"] = df["score"].shift(k)
    df["hour_sin"] = np.sin(2 * np.pi * df["ts"].dt.hour / 24)
    df["hour_cos"] = np.cos(2 * np.pi * df["ts"].dt.hour / 24)
    df["dow_sin"]  = np.sin(2 * np.pi * df["ts"].dt.dayofweek / 7)
    df["dow_cos"]  = np.cos(2 * np.pi * df["ts"].dt.dayofweek / 7)
    return df.dropna().reset_index(drop=True)


def feature_columns(window: int) -> list[str]:
    """Ordre canonique des colonnes. Doit rester identique entre l'entrainement
    et la boucle de prevision, sinon XGBoost recoit des features permutees."""
    return ([f"lag{k}" for k in range(1, window + 1)]
            + ["hour_sin", "hour_cos", "dow_sin", "dow_cos"]
            + EXOG_COLS)


# ------------------------------------------------------------------------
# LSTM model
# ------------------------------------------------------------------------
def build_lstm(input_steps: int) -> Model:
    inp = layers.Input(shape=(input_steps, 1))
    x = layers.LSTM(32, return_sequences=False)(inp)
    x = layers.Dense(16, activation="relu")(x)
    out = layers.Dense(1)(x)
    m = Model(inp, out)
    m.compile(optimizer=tf.keras.optimizers.Adam(5e-3), loss="mse")
    return m


def lstm_dataset(series: np.ndarray, window: int = 7):
    X, y = [], []
    for i in range(window, len(series)):
        X.append(series[i - window:i])
        y.append(series[i])
    return np.array(X)[..., None], np.array(y)


# ------------------------------------------------------------------------
# Metrics + persistence
# ------------------------------------------------------------------------
def smape(y_true, y_pred) -> float:
    return float(np.mean(np.abs(y_true - y_pred) /
                 np.maximum(1e-6, (np.abs(y_true) + np.abs(y_pred)) / 2))) * 100


def mape(y_true, y_pred) -> float:
    mask = y_true != 0
    if not mask.any():
        return 0.0
    return float(np.mean(np.abs((y_true[mask] - y_pred[mask]) / y_true[mask]))) * 100


def clear_zone_rows(zone_id: int):
    """v4.0 : purge le run precedent pour cette zone.

    L'ancien code ne supprimait jamais rien : chaque execution empilait de
    nouvelles lignes, si bien que les AVG() calcules cote UI melangeaient tous
    les runs historiques (y compris ceux entraines sur des donnees CGAN).
    """
    with db() as cx, cx.cursor() as cur:
        cur.execute("DELETE FROM forecast_metrics WHERE zone_id=%s", (zone_id,))
        cur.execute("DELETE FROM forecast_predictions WHERE zone_id=%s", (zone_id,))
        cx.commit()


def save_metrics(zone_id, model_name, y_true, y_pred):
    if len(y_true) == 0:
        return
    rmse = float(np.sqrt(mean_squared_error(y_true, y_pred)))
    mae  = float(mean_absolute_error(y_true, y_pred))
    r2   = float(r2_score(y_true, y_pred))
    with db() as cx, cx.cursor() as cur:
        cur.execute(
            "INSERT INTO forecast_metrics "
            "(model_name, zone_id, mae, rmse, mape, r2, smape, sample_size) "
            "VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
            (model_name, zone_id, mae, rmse,
             mape(y_true, y_pred), r2, smape(y_true, y_pred), len(y_true)),
        )
        cx.commit()


def save_predictions(zone_id, predictions, method, confidence):
    """predictions: dict {horizon_h -> score}"""
    rows = []
    for h, s in predictions.items():
        level = "critical" if s >= 70 else ("warning" if s >= 40 else "safe")
        rows.append((zone_id, h, int(round(s)), level, method, confidence))
    with db() as cx, cx.cursor() as cur:
        cur.executemany(
            "INSERT INTO forecast_predictions "
            "(zone_id, horizon_hours, predicted_score, predicted_level, method, confidence) "
            "VALUES (%s,%s,%s,%s,%s,%s)",
            rows,
        )
        cx.commit()


# ------------------------------------------------------------------------
# Main training loop
# ------------------------------------------------------------------------
def train_zone(zone_id: int, zname: str, window: int = 7,
               epochs: int = 100, days: int = 180):
    df = load_full_series(zone_id, days)
    if df.empty:
        print(f"  - {zname:25s} SKIPPED (aucune ligne open_data — city_key ?)")
        return
    if len(df) < window * 4:
        print(f"  - {zname:25s} SKIPPED ({len(df)} pts)")
        return

    clear_zone_rows(zone_id)

    feats = make_features(df, n_lags=window)
    feat_cols = feature_columns(window)
    X = feats[feat_cols].values.astype(float)
    y = feats["score"].values.astype(float)

    # Split chronologique 80/20 : les 80% les plus ANCIENS pour l'entrainement,
    # les 20% les plus RECENTS pour le test. Aucune fuite temporelle.
    split = int(len(X) * 0.8)
    X_tr, X_va = X[:split], X[split:]
    y_tr, y_va = y[:split], y[split:]
    print(f"  - {zname:25s} {len(df)} lignes reelles | "
          f"train={split} [{feats['ts'].iloc[0]} -> {feats['ts'].iloc[split]}] "
          f"| test={len(X)-split}")

    # - XGBoost -
    xgb = XGBRegressor(n_estimators=300, max_depth=6, learning_rate=0.05,
                       subsample=0.85, colsample_bytree=0.8,
                       verbosity=0, n_jobs=-1)
    xgb.fit(X_tr, y_tr)
    y_pred_xgb = xgb.predict(X_va)
    save_metrics(zone_id, "xgboost", y_va, y_pred_xgb)

    # - LSTM -
    series = feats["score"].values.astype(np.float32) / 100.0
    Xs, ys = lstm_dataset(series, window=window)
    split_s = int(len(Xs) * 0.8)
    Xs_tr, Xs_va = Xs[:split_s], Xs[split_s:]
    ys_tr, ys_va = ys[:split_s], ys[split_s:]
    lstm = build_lstm(window)
    lstm.fit(Xs_tr, ys_tr, epochs=epochs, batch_size=32, verbose=0)
    y_pred_lstm = (lstm.predict(Xs_va, verbose=0).flatten() * 100)
    y_va_lstm = ys_va * 100
    save_metrics(zone_id, "lstm", y_va_lstm, y_pred_lstm)

    # - Ensemble alpha search -
    n = min(len(y_pred_xgb), len(y_pred_lstm))
    if n == 0:
        return
    y_xgb_clipped  = np.clip(y_pred_xgb[-n:], 0, 100)
    y_lstm_clipped = np.clip(y_pred_lstm[-n:], 0, 100)
    y_true = y_va_lstm[-n:]

    best = {"alpha": 0.5, "rmse": float("inf"), "yE": y_xgb_clipped}
    for a in np.arange(0, 1.01, 0.05):
        yE = a * y_xgb_clipped + (1 - a) * y_lstm_clipped
        rmse = float(np.sqrt(mean_squared_error(y_true, yE)))
        if rmse < best["rmse"]:
            best = {"alpha": float(a), "rmse": rmse, "yE": yE}

    save_metrics(zone_id, "ensemble", y_true, best["yE"])

    # - Forecast 6/12/24h ahead -
    # v4.0 : CORRECTION D'UNE FUITE. L'ancien code appelait utcnow() a chaque
    # iteration, donc les 24 pas de prevision partageaient la meme heure et le
    # meme jour de semaine : les features temporelles etaient constantes et le
    # modele ne voyait jamais le temps avancer. On fait desormais avancer
    # l'horodatage cible d'une heure a chaque pas, a partir du dernier point
    # REEL de la serie.
    last_ts = feats["ts"].iloc[-1].to_pydatetime()
    last_exog = feats[EXOG_COLS].iloc[-1].values.astype(float)
    last_window = series[-window:].copy()

    preds = {}
    x = last_window.copy()
    for h in range(1, 25):
        target_ts = last_ts + dt.timedelta(hours=h)

        x_lstm = x.reshape(1, window, 1)
        yL = float(lstm.predict(x_lstm, verbose=0)[0][0]) * 100

        x_xgb = np.array([
            *[v * 100 for v in x[::-1]],                       # lag1..lagN
            np.sin(2 * np.pi * target_ts.hour / 24),
            np.cos(2 * np.pi * target_ts.hour / 24),
            np.sin(2 * np.pi * target_ts.weekday() / 7),
            np.cos(2 * np.pi * target_ts.weekday() / 7),
            *last_exog,   # persistance des exogenes : on ne connait pas la
                          # meteo future, on maintient la derniere observation
        ]).reshape(1, -1)
        yX = float(xgb.predict(x_xgb)[0])

        yE = best["alpha"] * yX + (1 - best["alpha"]) * yL
        yE = float(np.clip(yE, 0, 100))
        x = np.append(x[1:], yE / 100.0)
        if h in (6, 12, 24):
            preds[h] = yE

    confidence = max(0.4, min(0.95, 1 - best["rmse"] / 100))
    save_predictions(zone_id, preds, "ensemble_xgb_lstm_opendata", confidence)
    print(f"    alpha={best['alpha']:.2f} RMSE={best['rmse']:.2f} "
          f"| 6h={preds.get(6, 0):.0f} 12h={preds.get(12, 0):.0f} 24h={preds.get(24, 0):.0f}")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--window", type=int, default=24,
                        help="Taille de la fenetre de lags (24 = un jour)")
    parser.add_argument("--epochs", type=int, default=60)
    parser.add_argument("--days", type=int, default=180,
                        help="Profondeur d'historique lue dans open_data")
    args = parser.parse_args()

    print("=" * 60)
    print("[forecast] XGBoost + LSTM ensemble | source: open_data (reel)")
    print(f"[forecast] window={args.window}h days={args.days} epochs={args.epochs}")
    print("[forecast] split chronologique 80/20, 0 donnee synthetique")
    print("=" * 60)
    for zid, zname in list_zones():
        try:
            train_zone(zid, zname, args.window, args.epochs, args.days)
        except Exception as e:  # noqa: BLE001
            print(f"  - {zname:25s} ERROR: {e}")
    print("[forecast] done.")


if __name__ == "__main__":
    main()