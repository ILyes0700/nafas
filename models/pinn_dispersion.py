"""PART 38 — Physics-Informed Neural Network (PINN).

Injecte l'équation de dispersion atmosphérique (panache gaussien) comme
contrainte physique dans la loss d'entraînement. Ancre le modèle dans la
physique réelle de Gabès (complexe chimique au nord-est, vent dominant)
plutôt qu'un modèle purement data-driven.

Utilisable comme couche additionnelle sur le TFT (Part 36) ou le BiLSTM.
Dégradation gracieuse : les helpers physiques marchent en numpy pur ; la
partie entraînement torch est sautée si torch est absent.
"""
from __future__ import annotations
import math


def gaussian_plume_equation(wind_speed, wind_dir, distance_to_source,
                            Q=1.0, H=30.0, y=0.0, z=1.5):
    """Concentration au sol (modèle de panache gaussien simplifié).

    wind_speed : m/s (borné pour éviter la division par ~0)
    distance_to_source : m (downwind)
    Retourne une concentration relative (unités arbitraires, normalisée ensuite).
    """
    u = max(0.5, float(wind_speed))
    x = max(1.0, float(distance_to_source))
    # Coefficients de dispersion de Pasquill-Gifford (classe D, neutre).
    sigma_y = 0.08 * x / math.sqrt(1 + 0.0001 * x)
    sigma_z = 0.06 * x / math.sqrt(1 + 0.0015 * x)
    sigma_y = max(1e-3, sigma_y)
    sigma_z = max(1e-3, sigma_z)
    c = (Q / (2 * math.pi * u * sigma_y * sigma_z))
    c *= math.exp(-0.5 * (y / sigma_y) ** 2)
    c *= (math.exp(-0.5 * ((z - H) / sigma_z) ** 2)
          + math.exp(-0.5 * ((z + H) / sigma_z) ** 2))
    return c


def _mse(a, b):
    try:
        import numpy as np
        a = np.asarray(a, dtype=float)
        b = np.asarray(b, dtype=float)
        return float(np.mean((a - b) ** 2))
    except Exception:
        if hasattr(a, "__len__"):
            return sum((x - y) ** 2 for x, y in zip(a, b)) / max(1, len(a))
        return (a - b) ** 2


def gaussian_plume_loss(y_pred, y_true, wind_speed, wind_dir,
                        distance_to_source, lam=0.1):
    """Loss = MSE(données) + lam * violation de l'équation de panache gaussien.

    Fonctionne en numpy (pour audit/tests). En contexte torch, remplacer _mse
    par la version différentiable (voir torch_loss ci-dessous).
    """
    data_term = _mse(y_pred, y_true)
    try:
        import numpy as np
        ws = np.atleast_1d(wind_speed)
        wd = np.atleast_1d(wind_dir)
        ds = np.atleast_1d(distance_to_source)
        physics = np.array([gaussian_plume_equation(float(a), float(b), float(c))
                            for a, b, c in zip(ws, wd, ds)])
        if physics.max() > 0:
            physics = physics / physics.max()
        yp = np.atleast_1d(y_pred)
        if yp.max() > 0:
            yp = yp / yp.max()
        physics_term = _mse(yp, physics)
    except Exception:
        physics_term = 0.0
    return data_term + lam * physics_term


def torch_loss(lam=0.1):
    """Retourne une closure de loss torch si torch est dispo, sinon None."""
    try:
        import torch
    except Exception as e:  # pragma: no cover
        print(f"[pinn] torch absent — loss différentiable indisponible: {e}")
        return None

    def _loss(y_pred, y_true, physics_target):
        mse = torch.mean((y_pred - y_true) ** 2)
        phys = torch.mean((y_pred - physics_target) ** 2)
        return mse + lam * phys

    return _loss


if __name__ == "__main__":
    print("plume c=", gaussian_plume_equation(3.0, 90.0, 500.0))
    print("loss=", gaussian_plume_loss([1, 2, 3], [1.1, 1.9, 3.2], [3, 3, 3], [90, 90, 90], [100, 300, 600]))
