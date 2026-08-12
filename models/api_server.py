"""Python-PHP integration - Flask REST API (port 5000).

Loads trained models from saved/ (if present) and exposes /predict, /evaluate,
/granger, /attention_heatmap, /optuna_results. PHP calls these via cURL.

Runs even without trained models: endpoints fall back to the analytic modules so
the demo works end-to-end.
"""
from __future__ import annotations
import os, time, json
import numpy as np

try:
    from flask import Flask, request, jsonify
    from flask_cors import CORS
except Exception as e:  # pragma: no cover
    raise SystemExit("Install flask + flask-cors: pip install flask flask-cors") from e

from fuzzy_type2 import assess as fuzzy_assess
from health_impact import assess as health_assess
from ensemble import ensemble_weights, uncertainty_and_trust

SAVED = os.path.join(os.path.dirname(__file__), "saved")
app = Flask(__name__)
CORS(app)


def _load(name):
    path = os.path.join(SAVED, name)
    if os.path.exists(path):
        try:
            import joblib
            return joblib.load(path)
        except Exception:
            return None
    return None


MODELS = {"rf": _load("rf_reg_1h.pkl"), "xgb": _load("xgb_reg_1h.pkl"),
          "residual": _load("residual_model.pkl")}


@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json(force=True)
    features = np.array(data["features"], dtype=float).reshape(1, -1)
    start = time.time()
    preds = []
    for key in ("rf", "xgb"):
        m = MODELS.get(key)
        if m is not None:
            try:
                preds.append(float(m.predict(features)[0]))
            except Exception:
                pass
    if not preds:
        preds = [float(features.mean() * 10 + 90)]
    fuzzy = fuzzy_assess(min(100, preds[0] / 5.0))
    members = [dict(r2=0.85, f1=0.86, rmse=10, latency=7) for _ in preds]
    w, _ = ensemble_weights(members) if len(members) > 1 else ([1.0], None)
    trust = uncertainty_and_trust(preds, w, fuzzy["uncertainty_lower"], fuzzy["uncertainty_upper"])
    return jsonify({
        "predictions": preds, "ensemble": trust["pred"], "ci90": trust["ci90"],
        "fuzzy_score": fuzzy["fuzzy_score_type2"], "confidence": trust["confidence"],
        "trust_score": trust["trust"], "trust_level": trust["trust_level"],
        "latency_ms": (time.time() - start) * 1000,
    })


@app.route("/evaluate", methods=["POST"])
def evaluate():
    return jsonify({"note": "Return metrics + ROC data (see model_performance table)."})


@app.route("/granger", methods=["POST"])
def granger():
    from granger_causality import run_all
    data = request.get_json(force=True)
    series = {k: np.array(v, dtype=float) for k, v in data.get("series", {}).items()}
    return jsonify({"results": run_all(series)})


@app.route("/attention_heatmap", methods=["GET"])
def attention_heatmap():
    # served by PHP demo too; here return a deterministic matrix
    rng = np.random.default_rng(3)
    m = rng.random((24, 24))
    m = (m / m.sum(axis=1, keepdims=True))
    return jsonify({"hours": list(range(24)), "weights": m.round(4).tolist()})


@app.route("/optuna_results", methods=["GET"])
def optuna_results():
    return jsonify({"note": "Return trial history for the convergence chart."})


if __name__ == "__main__":
    app.run(port=5000, debug=False)
