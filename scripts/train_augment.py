"""
train_augment.py — Time-series data augmentation for the Nafass / Gabès
Tatenafas project, "pro" edition.

This script offers TWO equivalent strategies:

  1. TIMEGAN-LITE  (default)   — a generative adversarial network adapted
     to short multivariate time series. Trains a generator G(z) → synthetic
     sequence and a discriminator D(x). At convergence, samples from G
     are statistically indistinguishable from the real risk-score series.

  2. DIFFUSION-LITE             — a 1-D denoising diffusion model that
     learns to reverse a gaussian noise schedule. Closer to TSDiff / CSDI.

Both write augmented rows into `risk_scores_augmented` with method =
'timegan' or 'tsdiff', so the PHP forecaster can mix them with the real
data automatically.

Usage
-----
    python scripts/train_augment.py            # TimeGAN, 200 rows per zone
    python scripts/train_augment.py --diff 500 # Diffusion, 500 rows per zone

Notes
-----
* Even on CPU, a 30-day series fits in seconds — keep epochs reasonable.
* The PHP fallback (backend/lib/data_augment.php) covers students/servers
  without Python; this script is the "wow" version for the jury demo.
"""
from __future__ import annotations
import argparse
import datetime as dt
import os
from typing import List, Tuple

import numpy as np
import pandas as pd
import pymysql

try:
    import tensorflow as tf
    from tensorflow.keras import layers, Model
except ImportError as e:
    raise SystemExit(
        "tensorflow not installed. Run: pip install -r scripts/requirements.txt"
    ) from e


# ────────────────────────────────────────────────────────────────────────
#  DB helpers
# ────────────────────────────────────────────────────────────────────────
def db():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "gabes_tatenafas"),
        charset="utf8mb4",
    )


def load_zone_series(zone_id: int, days: int = 60) -> np.ndarray:
    sql = ("SELECT score FROM risk_scores "
           "WHERE zone_id=%s AND computed_at >= NOW() - INTERVAL %s DAY "
           "ORDER BY computed_at ASC")
    with db() as cx, cx.cursor() as cur:
        cur.execute(sql, (zone_id, days))
        rows = [r[0] for r in cur.fetchall()]
    return np.array(rows, dtype=np.float32)


def list_zones() -> List[Tuple[int, str]]:
    with db() as cx, cx.cursor() as cur:
        cur.execute("SELECT id, name FROM zones")
        return list(cur.fetchall())


def insert_augmented(zone_id: int, samples: np.ndarray, method: str, version: str = "py-1.0"):
    """samples: shape (N,) of generated scores"""
    base = dt.datetime.utcnow() - dt.timedelta(days=30)
    rows = []
    for i, v in enumerate(samples):
        ts = base + dt.timedelta(minutes=i * 30)
        rows.append((zone_id, ts.strftime("%Y-%m-%d %H:%M:%S"),
                     int(max(0, min(100, round(float(v))))), method, version, None))
    with db() as cx, cx.cursor() as cur:
        cur.executemany(
            "INSERT INTO risk_scores_augmented "
            "(zone_id, synthetic_at, score, generation_method, generator_version, fidelity_score) "
            "VALUES (%s,%s,%s,%s,%s,%s)",
            rows,
        )
        cx.commit()


# ────────────────────────────────────────────────────────────────────────
#  TimeGAN-LITE — 1-feature, fixed-length sequences
# ────────────────────────────────────────────────────────────────────────
def build_generator(seq_len: int = 24, latent_dim: int = 16) -> Model:
    inp = layers.Input(shape=(latent_dim,))
    x = layers.Dense(64, activation="relu")(inp)
    x = layers.Dense(seq_len, activation="sigmoid")(x)
    x = layers.Reshape((seq_len, 1))(x)
    return Model(inp, x, name="generator")


def build_discriminator(seq_len: int = 24) -> Model:
    inp = layers.Input(shape=(seq_len, 1))
    x = layers.LSTM(32)(inp)
    x = layers.Dense(1, activation="sigmoid")(x)
    return Model(inp, x, name="discriminator")


def train_timegan(series: np.ndarray, seq_len: int = 24, epochs: int = 200,
                  batch_size: int = 16, latent_dim: int = 16) -> np.ndarray:
    if len(series) < seq_len * 2:
        return np.array([], dtype=np.float32)

    # Normalise to [0,1]
    real = series / 100.0
    windows = np.array(
        [real[i:i + seq_len] for i in range(len(real) - seq_len + 1)],
        dtype=np.float32,
    )[..., None]

    g = build_generator(seq_len, latent_dim)
    d = build_discriminator(seq_len)
    g_opt = tf.keras.optimizers.Adam(2e-4, beta_1=0.5)
    d_opt = tf.keras.optimizers.Adam(2e-4, beta_1=0.5)
    bce = tf.keras.losses.BinaryCrossentropy()

    @tf.function
    def train_step(real_batch):
        bs = tf.shape(real_batch)[0]
        z = tf.random.normal((bs, latent_dim))
        with tf.GradientTape() as gt, tf.GradientTape() as dt_:
            fake = g(z, training=True)
            r_logits = d(real_batch, training=True)
            f_logits = d(fake, training=True)
            d_loss = bce(tf.ones_like(r_logits), r_logits) + \
                     bce(tf.zeros_like(f_logits), f_logits)
            g_loss = bce(tf.ones_like(f_logits), f_logits)
        d_opt.apply_gradients(zip(dt_.gradient(d_loss, d.trainable_variables), d.trainable_variables))
        g_opt.apply_gradients(zip(gt.gradient(g_loss, g.trainable_variables), g.trainable_variables))
        return d_loss, g_loss

    ds = (tf.data.Dataset.from_tensor_slices(windows)
          .shuffle(1024).batch(batch_size).prefetch(tf.data.AUTOTUNE))

    for ep in range(epochs):
        for batch in ds:
            d_l, g_l = train_step(batch)
        if (ep + 1) % 50 == 0:
            print(f"  ep {ep + 1}/{epochs}  d_loss={float(d_l):.3f}  g_loss={float(g_l):.3f}")

    # Sample
    z = tf.random.normal((max(8, len(series) // seq_len + 1), latent_dim))
    fake = g(z).numpy().reshape(-1) * 100.0
    return fake


# ────────────────────────────────────────────────────────────────────────
#  DIFFUSION-LITE — 1-D denoising
# ────────────────────────────────────────────────────────────────────────
def train_diffusion(series: np.ndarray, T: int = 100, epochs: int = 300) -> np.ndarray:
    if len(series) < 12:
        return np.array([], dtype=np.float32)
    x0 = series.astype(np.float32) / 100.0
    betas = np.linspace(1e-4, 0.02, T, dtype=np.float32)
    alphas = 1 - betas
    alpha_bar = np.cumprod(alphas)

    inp = layers.Input(shape=(1,))
    t_in = layers.Input(shape=(1,))
    h = layers.Concatenate()([inp, t_in])
    h = layers.Dense(64, activation="swish")(h)
    h = layers.Dense(64, activation="swish")(h)
    out = layers.Dense(1)(h)
    model = Model([inp, t_in], out)
    model.compile(optimizer=tf.keras.optimizers.Adam(1e-3), loss="mse")

    for ep in range(epochs):
        t_step = np.random.randint(0, T)
        noise = np.random.randn(len(x0)).astype(np.float32)
        x_t = np.sqrt(alpha_bar[t_step]) * x0 + np.sqrt(1 - alpha_bar[t_step]) * noise
        target = noise
        t_arr = np.full((len(x0), 1), t_step / T, dtype=np.float32)
        model.train_on_batch([x_t[:, None], t_arr], target[:, None])

    # Sample N points
    N = max(64, len(series))
    x = np.random.randn(N).astype(np.float32)
    for t_step in reversed(range(T)):
        t_arr = np.full((N, 1), t_step / T, dtype=np.float32)
        eps = model.predict([x[:, None], t_arr], verbose=0).flatten()
        x = (x - betas[t_step] / np.sqrt(1 - alpha_bar[t_step]) * eps) / np.sqrt(alphas[t_step])
        if t_step > 0:
            x += np.sqrt(betas[t_step]) * np.random.randn(N).astype(np.float32)
    return np.clip(x, 0, 1) * 100.0


# ────────────────────────────────────────────────────────────────────────
#  Main
# ────────────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--method", choices=["timegan", "diffusion"], default="timegan")
    parser.add_argument("--per-zone", type=int, default=200)
    parser.add_argument("--epochs", type=int, default=200)
    args = parser.parse_args()

    print(f"[augment] strategy = {args.method.upper()}")
    for zid, zname in list_zones():
        series = load_zone_series(zid)
        if len(series) < 12:
            print(f"  - {zname:25s}  SKIPPED (only {len(series)} real points)")
            continue
        if args.method == "timegan":
            synth = train_timegan(series, epochs=args.epochs)
            method_tag = "timegan"
        else:
            synth = train_diffusion(series, epochs=args.epochs)
            method_tag = "tsdiff"
        synth = synth[: args.per_zone]
        insert_augmented(zid, synth, method_tag)
        print(f"  - {zname:25s}  +{len(synth)} synth  (method={method_tag})")
    print("[augment] done.")


if __name__ == "__main__":
    main()
