"""PART 2 - Conditional GAN generating 24h synthetic AQI sequences conditioned
on a 12-dim context vector (season one-hot, pollution one-hot, industrial/coastal
flags, cyclic hour). Requires TensorFlow.

References: Goodfellow (2014); Mirza & Osindero (2014); Yoon (2019).
"""
from __future__ import annotations
import numpy as np

Z_DIM, C_DIM, SEQ = 100, 12, 24


def build_generator():
    from tensorflow import keras
    z = keras.Input(shape=(Z_DIM,)); c = keras.Input(shape=(C_DIM,))
    x = keras.layers.Concatenate()([z, c])
    for u in (256, 512, 256):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LayerNormalization()(x)
        x = keras.layers.LeakyReLU(0.2)(x)
    # FIX: linear output (was tanh). Inputs are z-score standardised, so peaks
    # reach +/-2..3 sigma; tanh saturates at +/-1 and clips real peaks -> flat
    # amplitude. Linear lets the generator reproduce the full real range.
    out = keras.layers.Dense(SEQ)(x)
    return keras.Model([z, c], out, name="generator")


def build_discriminator():
    from tensorflow import keras
    s = keras.Input(shape=(SEQ,)); c = keras.Input(shape=(C_DIM,))
    x = keras.layers.Concatenate()([s, c])
    for u in (256, 512, 256):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LeakyReLU(0.2)(x)
        x = keras.layers.Dropout(0.3)(x)
    out = keras.layers.Dense(1, activation="sigmoid")(x)
    return keras.Model([s, c], out, name="discriminator")


def train(real_seqs, conds, epochs=1000, batch=32):
    import tensorflow as tf
    from tensorflow import keras
    G, D = build_generator(), build_discriminator()
    opt_g = keras.optimizers.Adam(2e-4, beta_1=0.5)
    opt_d = keras.optimizers.Adam(1e-4, beta_1=0.5)
    bce = keras.losses.BinaryCrossentropy(label_smoothing=0.1)
    hist = {"g": [], "d": []}
    n = len(real_seqs)
    real_seqs = np.asarray(real_seqs, dtype=np.float32)
    conds = np.asarray(conds, dtype=np.float32)
    for ep in range(epochs):
        idx = np.random.randint(0, n, batch)
        # Keep EVERY model input as a tf.Tensor: mixing a tf.Tensor (the
        # generator output `fake`) with numpy arrays inside a nested D([...])
        # call raises "cannot mix tensors and non-tensors" on recent Keras/TF.
        rs = tf.convert_to_tensor(real_seqs[idx], dtype=tf.float32)
        cs = tf.convert_to_tensor(conds[idx], dtype=tf.float32)
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as td:
            fake = G([z, cs], training=True)
            d_real = D([rs, cs], training=True); d_fake = D([fake, cs], training=True)
            d_loss = bce(tf.ones_like(d_real), d_real) + bce(tf.zeros_like(d_fake), d_fake)
        gd = td.gradient(d_loss, D.trainable_variables)
        opt_d.apply_gradients(zip(gd, D.trainable_variables))
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as tg:
            fake = G([z, cs], training=True)
            d_fake = D([fake, cs], training=True)
            g_loss = bce(tf.ones_like(d_fake), d_fake)
        gg = tg.gradient(g_loss, G.trainable_variables)
        opt_g.apply_gradients(zip(gg, G.trainable_variables))
        if ep % 10 == 0:
            hist["g"].append(float(g_loss)); hist["d"].append(float(d_loss))
    return G, D, hist


def coverage_score(real, generated, bins=10):
    lo, hi = min(real.min(), generated.min()), max(real.max(), generated.max())
    edges = np.linspace(lo, hi, bins + 1)
    hr, _ = np.histogram(real, edges); hg, _ = np.histogram(generated, edges)
    return float(np.mean((hr > 0) == (hg > 0)))


# ------------------------------------------------------------------
# MULTIVARIATE CGAN — generate (SEQ, F) synthetic pollutant+weather windows.
# Used by models/augment_db.py to GROW api_readings so ML *and* BiLSTM/DL train
# on more data. Univariate helpers above stay for the risk-score augmentation.
# ------------------------------------------------------------------
def build_generator_mv(n_features, seq=SEQ, z_dim=Z_DIM, c_dim=C_DIM):
    from tensorflow import keras
    z = keras.Input(shape=(z_dim,)); c = keras.Input(shape=(c_dim,))
    x = keras.layers.Concatenate()([z, c])
    for u in (256, 512, 512):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LayerNormalization()(x)
        x = keras.layers.LeakyReLU(0.2)(x)
    # FIX: linear output (was tanh) so standardised peaks are not clipped to +/-1.
    x = keras.layers.Dense(seq * n_features)(x)
    out = keras.layers.Reshape((seq, n_features))(x)
    return keras.Model([z, c], out, name="generator_mv")


def build_discriminator_mv(n_features, seq=SEQ, c_dim=C_DIM):
    from tensorflow import keras
    s = keras.Input(shape=(seq, n_features)); c = keras.Input(shape=(c_dim,))
    f = keras.layers.Flatten()(s)
    x = keras.layers.Concatenate()([f, c])
    for u in (512, 256, 128):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LeakyReLU(0.2)(x)
        x = keras.layers.Dropout(0.3)(x)
    out = keras.layers.Dense(1, activation="sigmoid")(x)
    return keras.Model([s, c], out, name="discriminator_mv")


def train_mv(real_seqs, conds, epochs=400, batch=16):
    """Train a multivariate CGAN on (n, SEQ, F) real windows. Returns (G, hist).
    real_seqs must already be standardised per feature."""
    import tensorflow as tf
    from tensorflow import keras
    n, seq, F = real_seqs.shape
    G = build_generator_mv(F, seq)
    D = build_discriminator_mv(F, seq)
    opt_g = keras.optimizers.Adam(2e-4, beta_1=0.5)
    opt_d = keras.optimizers.Adam(1e-4, beta_1=0.5)
    bce = keras.losses.BinaryCrossentropy(label_smoothing=0.1)
    hist = {"g": [], "d": []}
    real_seqs = np.asarray(real_seqs, dtype=np.float32)
    conds = np.asarray(conds, dtype=np.float32)
    for ep in range(epochs):
        idx = np.random.randint(0, n, batch)
        # Cast inputs to tf.Tensors so nested D([...]) calls never mix a
        # tf.Tensor (generator output) with numpy arrays.
        rs = tf.convert_to_tensor(real_seqs[idx], dtype=tf.float32)
        cs = tf.convert_to_tensor(conds[idx], dtype=tf.float32)
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as td:
            fake = G([z, cs], training=True)
            d_real = D([rs, cs], training=True); d_fake = D([fake, cs], training=True)
            d_loss = bce(tf.ones_like(d_real), d_real) + bce(tf.zeros_like(d_fake), d_fake)
        gd = td.gradient(d_loss, D.trainable_variables)
        opt_d.apply_gradients(zip(gd, D.trainable_variables))
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as tg:
            fake = G([z, cs], training=True)
            d_fake = D([fake, cs], training=True)
            g_loss = bce(tf.ones_like(d_fake), d_fake)
        gg = tg.gradient(g_loss, G.trainable_variables)
        opt_g.apply_gradients(zip(gg, G.trainable_variables))
        if ep % 10 == 0:
            hist["g"].append(float(g_loss)); hist["d"].append(float(d_loss))
    return G, hist


# ------------------------------------------------------------------
# AE-CGAN -- Autoencoder + conditional GAN in the latent space.
# 1) An autoencoder (encoder/decoder) learns the real 24h sequence shape.
# 2) A conditional GAN learns to generate NEW latent codes (conditioned on the
#    12-dim context) that the decoder turns into realistic synthetic windows.
# Handles univariate (F=1, risk scores) and multivariate (F>1, api_readings).
# Used by models/augment_db.py. Contract:
#     enc, dec, G, latent_dim, hist = train_ae_cgan(real_seqs, conds, batch=..)
#     lat = G.predict([z(1,Z_DIM), c(1,C_DIM)])  -> (1, latent_dim)
#     seq = dec.predict(lat)[0]                   -> (SEQ, F)
# ------------------------------------------------------------------
def build_encoder_mv(n_features, seq=SEQ, latent_dim=32):
    from tensorflow import keras
    s = keras.Input(shape=(seq, n_features))
    x = keras.layers.Conv1D(32, 3, padding="causal", activation="relu")(s)
    x = keras.layers.LSTM(48)(x)
    x = keras.layers.Dense(64, activation="relu")(x)
    out = keras.layers.Dense(latent_dim, name="latent")(x)
    return keras.Model(s, out, name="encoder_mv")


def build_decoder_mv(n_features, seq=SEQ, latent_dim=32):
    from tensorflow import keras
    z = keras.Input(shape=(latent_dim,))
    x = keras.layers.Dense(64, activation="relu")(z)
    x = keras.layers.RepeatVector(seq)(x)
    x = keras.layers.LSTM(48, return_sequences=True)(x)
    # FIX: linear reconstruction (was tanh). The AE is trained on z-scored data;
    # tanh capped reconstructions at +/-1 sigma and flattened the day/night peaks.
    out = keras.layers.TimeDistributed(keras.layers.Dense(n_features))(x)
    return keras.Model(z, out, name="decoder_mv")


def build_latent_generator(latent_dim, z_dim=Z_DIM, c_dim=C_DIM):
    from tensorflow import keras
    z = keras.Input(shape=(z_dim,)); c = keras.Input(shape=(c_dim,))
    x = keras.layers.Concatenate()([z, c])
    for u in (128, 256, 128):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LayerNormalization()(x)
        x = keras.layers.LeakyReLU(0.2)(x)
    out = keras.layers.Dense(latent_dim)(x)
    return keras.Model([z, c], out, name="latent_generator")


def build_latent_discriminator(latent_dim, c_dim=C_DIM):
    from tensorflow import keras
    l = keras.Input(shape=(latent_dim,)); c = keras.Input(shape=(c_dim,))
    x = keras.layers.Concatenate()([l, c])
    for u in (256, 128):
        x = keras.layers.Dense(u)(x)
        x = keras.layers.LeakyReLU(0.2)(x)
        x = keras.layers.Dropout(0.3)(x)
    out = keras.layers.Dense(1, activation="sigmoid")(x)
    return keras.Model([l, c], out, name="latent_discriminator")


def train_ae_cgan(real_seqs, conds, latent=32, ae_epochs=300, gan_epochs=600, batch=16):
    """AE-CGAN. real_seqs: (n, SEQ, F) standardised. conds: (n, C_DIM).
    Returns (encoder, decoder, latent_generator, latent_dim, hist)."""
    import tensorflow as tf
    from tensorflow import keras
    real_seqs = np.asarray(real_seqs, dtype=np.float32)
    conds = np.asarray(conds, dtype=np.float32)
    n, seq, F = real_seqs.shape
    latent_dim = min(latent, max(8, seq * F // 4))
    batch = max(2, min(batch, n))

    # 1) Autoencoder: learn the real sequence shape.
    enc = build_encoder_mv(F, seq, latent_dim)
    dec = build_decoder_mv(F, seq, latent_dim)
    inp = keras.Input(shape=(seq, F))
    ae = keras.Model(inp, dec(enc(inp)), name="autoencoder")
    ae.compile(optimizer=keras.optimizers.Adam(1e-3), loss="mse")
    ae.fit(real_seqs, real_seqs, epochs=ae_epochs, batch_size=batch, verbose=0)

    # 2) Encode reals to the latent space (targets for the latent GAN).
    real_lat = enc.predict(real_seqs, verbose=0).astype(np.float32)

    # 3) Conditional GAN in the latent space.
    G = build_latent_generator(latent_dim)
    D = build_latent_discriminator(latent_dim)
    opt_g = keras.optimizers.Adam(2e-4, beta_1=0.5)
    opt_d = keras.optimizers.Adam(1e-4, beta_1=0.5)
    bce = keras.losses.BinaryCrossentropy(label_smoothing=0.1)
    hist = {"g": [], "d": []}
    for ep in range(gan_epochs):
        idx = np.random.randint(0, n, batch)
        rl = tf.convert_to_tensor(real_lat[idx], dtype=tf.float32)
        cs = tf.convert_to_tensor(conds[idx], dtype=tf.float32)
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as td:
            fake = G([z, cs], training=True)
            d_real = D([rl, cs], training=True); d_fake = D([fake, cs], training=True)
            d_loss = bce(tf.ones_like(d_real), d_real) + bce(tf.zeros_like(d_fake), d_fake)
        gd = td.gradient(d_loss, D.trainable_variables)
        opt_d.apply_gradients(zip(gd, D.trainable_variables))
        z = tf.random.normal((batch, Z_DIM))
        with tf.GradientTape() as tg:
            fake = G([z, cs], training=True)
            d_fake = D([fake, cs], training=True)
            g_loss = bce(tf.ones_like(d_fake), d_fake)
        gg = tg.gradient(g_loss, G.trainable_variables)
        opt_g.apply_gradients(zip(gg, G.trainable_variables))
        if ep % 10 == 0:
            hist["g"].append(float(g_loss)); hist["d"].append(float(d_loss))
    return enc, dec, G, latent_dim, hist


if __name__ == "__main__":
    print("cgan_trainer: Conditional GAN (needs TensorFlow to train).")
