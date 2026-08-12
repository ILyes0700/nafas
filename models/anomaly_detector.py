"""PART 5.5 - Anomaly detection: Autoencoder reconstruction error combined with
Isolation Forest. Trains on normal days only.

v4.0 (refactor Open-Meteo / suppression CGAN)
---------------------------------------------
- n_features passe de 27 a 29 pour coller au nouveau vecteur de features
  defini dans models/train_all.py (FEATURE_NAMES, 29 entrees : 9 lags
  + 3 fuzzy + 7 polluants + 7 meteo + 3 temps).

- VERIFICATION demandee par le prompt (section 5.1) : cet autoencodeur
  est DENSE et travaille sur UNE seule ligne (un instant t, entree 2D
  (batch, n_features)). Il NE PEUT DONC PAS servir de base au
  BiLSTM+Autoencoder de prevision (models/bilstm_autoencoder.py) qui,
  lui, encode des FENETRES de 24 h (entree 3D (batch, 24, n_feats))
  avec des couches LSTM. Les deux coexistent volontairement :
      * anomaly_detector.AnomalyDetector  -> detection d'anomalies ponctuelles
      * bilstm_autoencoder.train_bilstm_ae -> prevision H+1 / H+6 / H+24
  C'est pour cela que le prompt disait "sauf si anomaly_detector.py utilise
  deja un AE, alors le reutiliser" : il en utilise un, mais d'une topologie
  incompatible. Reutilisation impossible -> fichier separe conserve.

- Plus aucune donnee CGAN / augmentee. X_normal doit desormais venir de
  open_data via models.data_loader.build_frames(), portion TRAIN uniquement
  (les 80 % chronologiques les plus anciens), jamais du test.

References: Liu et al. (2008) Isolation Forest; Goodfellow et al. (2016).
"""
from __future__ import annotations
import numpy as np
from sklearn.ensemble import IsolationForest

# Doit rester aligne avec len(FEATURE_NAMES) dans models/train_all.py
N_FEATURES = 29


def build_autoencoder(n_features: int = N_FEATURES):
    """Autoencodeur dense symetrique n -> 32 -> 16 -> 8 -> 16 -> 32 -> n.

    Le goulot (8) force le reseau a apprendre la structure des journees
    NORMALES ; une journee anormale se reconstruit mal => erreur elevee.
    """
    from tensorflow import keras
    inp = keras.Input(shape=(n_features,))
    e = keras.layers.Dense(32, activation="relu")(inp)
    e = keras.layers.Dense(16, activation="relu")(e)
    z = keras.layers.Dense(8, activation="relu")(e)          # latent
    d = keras.layers.Dense(16, activation="relu")(z)
    d = keras.layers.Dense(32, activation="relu")(d)
    out = keras.layers.Dense(n_features, activation="linear")(d)
    ae = keras.Model(inp, out, name="dense_autoencoder_anomaly")
    ae.compile(optimizer=keras.optimizers.Adam(1e-3), loss="mse")
    return ae


def classify_anomaly(feat):
    """feat: dict avec so2, pm10, pm25, no2, wind. Retourne un type d'anomalie.

    Ordre du plus specifique au moins specifique : on teste d'abord la tempete
    de sable et l'evenement chimique multi-polluants, puis seulement apres le
    pic industriel (SO2). Sinon un SO2 eleve -- frequent a Ghannouche -- ferait
    classer TOUTES les anomalies comme "pic industriel" et masquerait les
    tempetes de sable et les rejets chimiques.
    """
    so2 = feat.get("so2", 0)
    pm10 = feat.get("pm10", 0)
    wind = feat.get("wind", 0)
    high = sum(1 for k in ("so2", "pm25", "pm10", "no2") if feat.get(k, 0) > 150)
    # 1) Tempete de sable : PM10 tres eleve porte par un vent fort.
    #    Avec open_data on dispose en plus de la colonne "dust" (CAMS) :
    #    si elle est presente et forte, elle confirme le diagnostic.
    if pm10 > 300 and wind > 35:
        return "sandstorm"
    if feat.get("dust", 0) > 200 and pm10 > 200:
        return "sandstorm"
    # 2) Evenement chimique : au moins 3 polluants eleves simultanement.
    if high >= 3:
        return "chemical_event"
    # 3) Pic industriel : SO2 dominant (signature de Ghannouche / Chott Salem).
    if so2 > 200:
        return "industrial_spike"
    return "data_error"


class AnomalyDetector:
    def __init__(self, n_features: int = N_FEATURES):
        self.n_features = n_features
        self.ae = None
        self.threshold = None
        self.iso = IsolationForest(
            n_estimators=100, contamination=0.05, random_state=42
        )

    def fit(self, X_normal, epochs: int = 200):
        """X_normal : uniquement la portion TRAIN (80 % anciens) de open_data.

        Ne jamais passer ici des lignes du test set : cela creerait une fuite
        de donnees (data leakage) et fausserait toutes les metriques.
        """
        X_normal = np.asarray(X_normal, dtype="float32")
        if X_normal.ndim != 2 or X_normal.shape[1] != self.n_features:
            raise ValueError(
                f"X_normal doit etre 2D (n_samples, {self.n_features}), "
                f"recu {X_normal.shape}. Verifie FEATURE_NAMES dans train_all.py."
            )
        self.ae = build_autoencoder(self.n_features)
        self.ae.fit(X_normal, X_normal, epochs=epochs, batch_size=32, verbose=0)
        recon = self.ae.predict(X_normal, verbose=0)
        errs = np.mean((X_normal - recon) ** 2, axis=1)
        self.threshold = float(errs.mean() + 3 * errs.std())
        self.iso.fit(X_normal)
        return self

    def score(self, X):
        if self.ae is None or self.threshold is None:
            raise RuntimeError("AnomalyDetector.fit() doit etre appele avant score().")
        X = np.asarray(X, dtype="float32")
        recon = self.ae.predict(X, verbose=0)
        ae_err = np.mean((X - recon) ** 2, axis=1)
        iso_score = self.iso.decision_function(X)
        detected = ae_err > self.threshold
        anomaly_score = ae_err / (self.threshold + 1e-9)
        return {
            "ae_error": ae_err,
            "iso_score": iso_score,
            "detected": detected,
            "anomaly_score": anomaly_score,
        }


if __name__ == "__main__":
    print(
        "anomaly_detector v4.0 : Autoencoder dense (29 features) + IsolationForest.\n"
        "Source de donnees : open_data (portion train 80%). TensorFlow requis."
    )